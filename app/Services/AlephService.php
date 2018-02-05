<?php
/**
 * Implementation for talking to Aleph through the X web service interface
 * to retrieve information on holdings
 */
namespace App\Services;

use DOMDocument;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AlephService implements ILSInterface {
  protected $aleph_endpoint, $opac_uri;
  protected $client;

  /**
   * Instantiate a new client to talk to Aleph through the web API. Ensure
   * that the IP address is allowed to connect or you may see unexpected
   * responses
   */
  public function __construct() {
    $this->aleph_endpoint = config("apis.aleph.uri", 
      "http://localhost:8991/X/");
    $this->client = new Client(['base_uri' => $this->aleph_endpoint]);

    $this->opac_uri = config("apis.opac.base_uri",
      "http://localhost");
  }

  /**
   * Query Aleph to retrieve the record count and a back link into the catalog
   * for different types of materials. Currently the service supports two
   * different queries
   *
   * artist - Subjects relating to an artist (MARC field 700$a)
   * accession number - Content which has been cataloged with a local
   *                    accession number (MARC field 905)
   */
  public function link_for(string $query, string $type) {
    $query_parameters = $this->default_parameters();
    switch ($type) {
      case "accession_number":
        $query_parameters = array_merge($query_parameters,
          $this->query_parameters_for_accessionNumber($query));
        break;
      case "artist":
        $query_parameters = array_merge($query_parameters,
          $this->query_parameters_for_artist($query));
        break;
    }

    $response = $this->client->get('', ['query' => $query_parameters]);
    $aleph_document = new DOMDocument();
    $aleph_document->loadXML((string)$response->getBody());

    $errors = $aleph_document->getElementsByTagName("error");
    if ($errors->length > 0) {
      print $response->getBody();
      if ("empty set" == (string) $errors->item(0)->nodeValue) {
        return []; 
      } else {
        return null;
      }
    } 

    $record_count = $aleph_document->getElementsByTagName("no_records");
    $record_count = (integer) $record_count->item(0)->nodeValue; 
    $opac_backlinks = [];
    if ($record_count > 0) {
      $opac_parameters = [];
      switch ($type) {
        case "accession_number":
          $opac_parameters = $this->link_parameters_for_accessionNumber($query);
          break;
        case "artist":
          $opac_parameters = $this->link_parameters_for_artist($query);
          break;
      }
      $opac_backlinks[$query] = [
        "uri" => "$this->opac_uri?" . http_build_query($opac_parameters),
        "count" => $record_count
      ];
    } 

    return $opac_backlinks;
  }

  /**
   * Creates a citation based on either an Aleph identifier or OCLC
   * call number. The Chicago format will be used in line with the
   * WorldcatService. 
   *
   * For example 62093054 (OCLC) or 000039785 (Aleph) would return
   * 
   * Lipsey, Roger. <i>Art of Thomas Merton.</i> Boston, Mass.: New Seeds ;, 
   * 2006.
   */
  public function metadata_for(string $identifier, string $type = "aleph_id") {
    $marc_fragment = null;
    $parser = new DOMDocument();
 
    if ("oclc" == $type) {
       $query_parameters = array_merge(
         $this->query_parameters_for_callNumber($identifier),
         $this->default_parameters());
       $response = $this->client->get('', ['query' => $query_parameters]);
 
       $parser->loadXML((string)$response->getBody());
       $set_element = $parser->getElementsByTagName("set_number");
       if (0 < $set_element->length) {
         $marc_parameters = array_merge($this->default_parameters(),
           ["op" => "present",
            "set_entry" => 1,
            "set_number" => $set_element->item(0)->nodeValue]);
         $marc_response = $this->client->get('', ['query' => $marc_parameters]);
         $parser->loadXML((string)$marc_response->getBody());
         $marc_fragment = $parser->getElementsByTagName("metadata");
       } 
    } else if ("aleph_id" == $type) {
       $query_parameters = array_merge($this->default_parameters(),
         $this->query_parameters_for_alephId($identifier));
       $marc_response = $this->client->get('', ['query' => $query_parameters]);
       $parser->loadXML((string)$marc_response->getBody());
       $marc_fragment = $parser->getElementsByTagName("record"); 
     } 

     if ((null == $marc_fragment) ||
         (0 == $marc_fragment->length)) {
       return null;
     }

     $marc_fragment = $marc_fragment->item(0);
     $marc_document = new DOMDocument();
     $marc_node = $marc_document->importNode($marc_fragment, true);
     $marc_document->appendChild($marc_node);
     $marc = $this->normalize_marc($marc_document);

     $properties['author'] = $this->process_marc($marc, 
       ['100$a', '110$a', '130$a', '700$a', '710$a']);
     $properties['title'] = $this->process_marc($marc,
       ['246$a', '245$a', '001']);   
     $properties['publication']['location'] = $this->process_marc($marc,
       ['260$a', '261$a', '262$a', '264$a']);
     $properties['publication']['publisher'] = $this->process_marc($marc,
       ['260$b', '261$b', '262$b', '264$b']);
     $properties['publication']['year'] = $this->process_marc($marc,
       ['260$c', '261$c', '262$c', '264$c']);
     $properties['identifier']['aleph'] = $this->process_marc($marc, ['001']);
     $properties['identifier']['oclc'] = $this->process_marc($marc,['035$a']);
     $properties['identifier']['isbn'] = $this->process_marc($marc, ['020$a']);
     $properties['language'] = $this->process_marc($marc, ['041$a', '008']);

     if (array_key_exists("isbn", $properties['identifier'])) {
       $value = $properties['identifier']['isbn'];
       $value = explode("(", $value)[0];
       $properties['identifier']['isbn'] = trim($value);
     }
     if (array_key_exists("oclc", $properties['identifier'])) {
       $value = $properties['identifier']['oclc'];
       $value = str_replace("(OCoLC)", "", $value);
       $properties['identifier']['oclc'] = trim($value);
     }

     if (str_contains($properties['language'], '^')) {
       $language_code = [];
       preg_match("/\^(\w{3})\^$/",
         $properties['language'], $language_code);
       $properties['language'] = $language_code[1]; 
     }

     return $properties; 
  }

  /**
   * Query Aleph for a list of recent titles which are returned as an
   * associative array containing location, title, and a link back into
   * the library catalog
   *
   * '000383470' =>
   *    'title' => 'My First Art Book',
   *    'description' => 'Location: .Z47 2014 NE1183.3',
   *    'link' => 'localhost/opac/234.564'
   */
  public function recent_titles() {
    $set_query_parameters = array_merge($this->default_parameters(), [
      "op" => "find",
      "request" => urlencode("WIPC=MT")
    ]);
    $response = $this->client->get('', ['query' => $set_query_parameters]);

    $parser = new DOMDocument();
    $parser->loadXML((string)$response->getBody());
    $set_number = $parser->getElementsByTagName("set_number")[0];
    $record_count = $parser->getElementsByTagName("no_records")[0];
    /**
     * If no set number can be found terminate processing and return an
     * empty list
     *
     * TODO: Handle <error> response with empty set by doing some logging.
     *       Better yet abstract Aleph querying into a helper method to DRY
     *       up entire service
     */
    if (null == $set_number) {
      return []; 
    }

    $records = [];
    $marc_query_parameters = array_merge($this->default_parameters(), [
      "op" => "present",
      "set_entry" => "001-" . $record_count->nodeValue,
      "set_number" => $set_number->nodeValue  
    ]);
    $marc_response = $this->client->get('', 
      ['query' => $marc_query_parameters]);
    $marc_records = $parser->loadXML((string)$marc_response->getBody());
    
    print($parser->getElementsByTagName('metadata')->length);

    foreach ($marc_records as $aleph_record) {
      $marc = $this->normalize_marc($aleph_record);
      
      $aleph_id = $this->process_marc($marc, ['001']);
      $title = $this->process_marc($marc, ['245$a']);
      $subtitle = $this->process_marc($marc, ['245$b']);
      $callNumber = $this->process_marc($marc, 
        ['852$h', '852$i', '050$a', '050$b']);
      $location = $this->process_marc($marc, ['852$b', '050$b']);

      $records[$aleph_id] = [
        ['title'] => trim("$title$subtitle"),
        ["link"] => 
          $this->opac_uri . "/F/?func=find-c&ccl_term=sys%3D$aleph_id",
        ["description"] => "Location: $location $callNumber"
      ];
    } 

    return $records;
  }

  /**
   * Default query parameters to append to all service calls against the
   * X service
   */
  protected function default_parameters() {
    return [
      "library" => config("apis.aleph.library", "default"),
      "base" => "STACKS"
    ];
  }

  /**
   * Constructs custom X query parameters based on an Aleph ID
   */
  protected function query_parameters_for_alephId(string $aleph_id) {
    return ["op" => "find_doc",
      "doc_num" => $aleph_id];
  }  
  
  /**
   * Constructs custom X query parameters based on OCLC call number
   */
  protected function query_parameters_for_callNumber(string $oclc_callnumber) {
    return ["op" => "find",
      "code" => "OCL",
      "request" => $oclc_callnumber];
  }

  /**
   * Query parameters for retrieving information on an object by its
   * accession number
   */
  protected function query_parameters_for_accessionNumber(string $accession_number) {
    $escaped_accession_number = str_replace(".", "*", $accession_number);
    return [
      "op" => "find",
      "code" => "cmaan",
      "request" => $escaped_accession_number
    ];
  }

  /**
   * Additional parameters to append to the catalog URI when constructing
   * a backlink for records associated with an accession number
   */
  protected function link_parameters_for_accessionNumber(string $accession_number) {
    $escaped_accession_number = str_replace(".", "*", $accession_number);
    return [
      "func" => "find-c",
      "ccl_term" => "cmaan=$escaped_accession_number"
    ];
  }

  /**
   * Query parameters for retrieving information based on artist name
   */
  protected function query_parameters_for_artist(string $artist) {
    return [
      "op" => "find",
      "code" => "wsu",
      "request" => urlencode($artist)
    ];
  }

  /**
   * Link parameters for building a query string that will link back into
   * the library cataog with a canned search
   */
  protected function link_parameters_for_artist(string $artist) {
    return [
      "func" => "find-c",
      "ccl_term" => "wsu=$artist"
    ];
  }

  /**
   * Normalizes OAI MARC output from Aleph into standard MARC XML which can
   * be processed by the File_MARC library and then returns the first record
   * (or null if the set was empty)
   *
   * @param string $oai_marc
   * @return File_MARC_Record
   */
  protected function normalize_marc(DOMDocument $oai_marc) {
    $xslt_stylesheet = config("apis.marc.stylesheet", "oai-to-marc.xsl");
    
    $xslt = new DOMDocument();
    $xslt->load($xslt_stylesheet);
    $processor = new \XSLTProcessor();
    $processor->importStylesheet($xslt);

    $marc_xml = $processor->transformToXML($oai_marc);
    $marc = new \File_MARCXML($marc_xml, \File_MARC::SOURCE_STRING);

    if ($marc_record = $marc->next()) {
      return $marc_record;
    } else {
      return null;
    }
  }

  /**
   * Extracts relevant fields from the MARC based on priority to create
   * a summarized record. For instance
   *
   * parse_marc_fields($marc, ["100\$a", "110\$a"])
   *
   * @param File_MARC_Record $marc
   * @param Array of MARC fields with optional subfield
   * @return Associative array with values
   */
  public function process_marc(\File_MARC_Record $marc, array $fields) {
    $marc_value = null;

    foreach($fields as $marc_field) {
      $field_properties = explode("$", $marc_field);
      $field = $field_properties[0];
      $subfield = (array_key_exists(1, $field_properties)) ?
        $field_properties[1] : null;
      
      if ($marc_value = $marc->getField($field)) {
        if (null !== $subfield) {
          $marc_value = $marc_value->getSubfield($subfield);
        }

        if ($marc_value) { break; }
      }
    }

    return $marc_value->getData();
  }
}
?>
