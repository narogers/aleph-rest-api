<?php
/**
 * Implementation for talking to Aleph through the X web service interface
 * to retrieve information on holdings
 */
namespace App\Services;

use App\Models\CatalogItem;
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
  public function __construct(Client $client) {
    $this->client = $client;

    $this->aleph_endpoint = config("apis.aleph.uri", 
      "http://localhost:8991/X/");
    $this->opac_uri = config("apis.opac.base_uri", "http://localhost");
    if (!Str::endsWith($this->opac_uri, "/")) {
      $this->opac_uri .= "/";
    }
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
    $aleph_document = null;
    switch ($type) {
      case "accession_number":
        $aleph_document = $this->query_aleph($this->query_parameters_for_accessionNumber($query)); 
        break;
      case "artist":
        $aleph_document = $this->query_aleph($this->query_parameters_for_artist($query));
    }

    if (null == $aleph_document) {
      return []; 
    } 

    $record_count = $aleph_document->getElementsByTagName("no_records");
    $record_count = (integer)$record_count->item(0)->nodeValue; 
    $opac_backlinks = [];
    if ($record_count > 0) {
      $opac_parameters = [];
      switch ($type) {
        case "accession_number":
          $opac_parameters = $this->link_parameters_for_accessionNumber($query);
          break;
        case "artist":
          $opac_parameters = $this->link_parameters_for_artist($query);
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
    $marc_document = null;
    $marc_wrapper = "";
 
    if ("oclc" == $type) {
       $set_document = $this->query_aleph($this->query_parameters_for_callNumber($identifier));
       if ($set_document !== null) {
         $set_number = $set_document->getElementsByTagName("set_number")[0]->nodeValue;
         $marc_document = $this->query_aleph(["op" => "present",
           "set_entry" => 1,
           "set_number" => $set_number]);
         $marc_wrapper = "metadata";
       } 
    } else if ("aleph_id" == $type) {
       $marc_document = $this->query_aleph($this->query_parameters_for_alephId($identifier));
       $marc_wrapper = "metadata";
     } 

     if (null == $marc_document) {
       return null;
     }

     $marc_fragment = $marc_document->getElementsByTagName($marc_wrapper)[0];
     $marc = $this->normalize_marc($marc_fragment);

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

     /**
      * ^ indicates that the 008 field was used and you should follow the
      * MARC specification to get the value at the position for language
      */
     if (str_contains($properties['language'], '^')) {
       $properties['language'] = substr($properties['language'], 35, 3); 
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
    $aleph_document = $this->query_aleph(["op" => "find", 
      "request" => "WIPC=MT"]);
    if (null == $aleph_document) {
      return []; 
    }

    $set_number = $aleph_document->getElementsByTagName("set_number")[0]; 
    $record_count = $aleph_document->getElementsByTagName("no_records")[0];
    $records = [];

    $marc_document = $this->query_aleph(["op" => "present",
      "set_entry" => "001-" . $record_count->nodeValue,
      "set_number" => $set_number->nodeValue]);
    $marc_records = $marc_document->getElementsByTagName("metadata");   
 
    foreach ($marc_records as $aleph_record) {
      $marc = $this->normalize_marc($aleph_record);

      $aleph_id = $this->process_marc($marc, ['001']);
      $title = $this->process_marc($marc, ['245$a']);
      $subtitle = $this->process_marc($marc, ['245$b']);
      $callNumber = $this->process_marc($marc, 
        ['852$h', '852$i', '050$a', '050$b']);
      $location = $this->process_marc($marc, ['852$b', '050$b']);

      $properties = [
        'title' => trim("$title$subtitle"),
        "link" => 
          $this->opac_uri . "F/?func=find-c&ccl_term=sys%3D$aleph_id",
        "description" => "Location: $location $callNumber"
      ];
      $catalog_entry = new CatalogItem($properties);
      array_push($records, $catalog_entry);
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
  protected function normalize_marc(\DOMElement $oai_marc) {
    $xslt_stylesheet = config("apis.marc.stylesheet", "oai-to-marc.xsl");
   
    $oai_document = new DOMDocument();
    $oai_node = $oai_document->importNode($oai_marc, true);
    $oai_document->appendChild($oai_node);    
  
    $xslt = new DOMDocument();
    $xslt->load($xslt_stylesheet);
    $processor = new \XSLTProcessor();
    $processor->importStylesheet($xslt);

    $marc_xml = $processor->transformToXML($oai_document);
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
   
    if (false !== $marc_value) {
      return $marc_value->getData();
    } else {
      return null;
    }
  }

  /**
   * Abstraction for querying the Aleph X service in a robust way which
   * handles most common errors. 
   *
   * If no errors occur it will return a DOM representation of the XML 
   * response. In cases where the error state might be meaningful use a 
   * custom query instead.
   */
  protected function query_aleph(array $parameters) {
    $query_parameters = array_merge($parameters, $this->default_parameters());
    $response = $this->client->get($this->aleph_endpoint,
      ['query' => $query_parameters]);
    
    $aleph_dom = new DOMDocument();
    $aleph_dom->loadXML((string)$response->getBody());
    
    $errors = $aleph_dom->getElementsByTagName("error");
    if ($errors->length > 0) {
      Log::warning("Unable to process Aleph request for " . 
        $this->aleph_endpoint . "?" .
        http_build_query($query_parameters));
      Log::warning("Aleph returned message - " . $errors->item(0)->nodeValue);
      
      return null;
    }

    return $aleph_dom;
  }
}
?>
