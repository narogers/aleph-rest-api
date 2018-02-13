<?php
/**
 * Implementation of OCLCInterface for interacting with Worldcat
 */
namespace App\Services;

use App\Exceptions\ConfigurationException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WorldcatService implements OCLCInterface {
  protected $worldcat_key, $worldcat_base;
  protected $client;

  /**
   * Instantiate a new connection to Worldcat. Be sure that you provide
   * a valid Worldcat API key or you may get unexpected results from
   * Guzzle
   */
  public function __construct(Client $client) {
    $this->worldcat_key = config("apis.oclc.key", "changeMe");
    $this->worldcat_base = "http://www.worldcat.org/webservices";
    $this->client = $client;
  }

  /**
   * Queries Worldcat for citations based on the type of identifier provided
   *
   * @param string $identifier
   * @param string $format
   * @return string 
   */
 public function citation_for(string $identifier, string $format = "oclc") {
   switch($format) {
    case "isbn":
       return $this->citation_for_isbn($identifier);
     case "issn":
       return $this->citation_for_issn($identifier);
     case "lc":
       return $this->citation_for_lc($identifier);
     case "oclc":
       return $this->citation_for_oclc($identifier);
    }
 }
 
  /**
   * Query Worldcat for a citation if it does not already exist in the
   * current cache. If no citation is found the function will return a
   * a NULL value which can be used downstream in error handling
   */
  public function citation_for_oclc(string $oclc_number) {
    $citation = Cache::get("oclc:$oclc_number");
    if (null !== $citation) {
      return $citation;
    }

    $citation = $this->query_worldcat("catalog/content/citations/$oclc_number");
    if (null !== $citation) {  
      $this->cache_citation("oclc:$oclc_number", $citation);
    }  
    return $citation;
  }

  /**
   * Query Worldcat for a citation based of Library of Congress call number
   */
  public function citation_for_lc(string $call_number) {
    $citation = Cache::get("lc:$call_number");
    if (null !== $citation) {
      return $citation;
    }

    $citation = $this->query_worldcat("catalog/content/citations/sn/$call_number");
    if (null !== $citation) {  
      $this->cache_citation("lc:$call_number", $citation);
    }  
    return $citation;
  }

  /**
   * Query Worldcat for a citation based on ISSN
   */
  public function citation_for_issn(string $issn) {
    $citation = Cache::get("issn:$issn");
    if (null !== $citation) {
      return $citation;
    }

    $citation = $this->query_worldcat("catalog/content/citations/issn/$issn");
    if (null !== $citation) {  
      $this->cache_citation("issn:$issn", $citation);
    }  
    return $citation;
  }

  /**
   * Query Worldcat for a citation based on ISBN
   */
  public function citation_for_isbn(string $isbn) {
    $citation = Cache::get("isbn:$isbn");
    if (null !== $citation) {
      return $citation;
    }

    $citation = $this->query_worldcat("catalog/content/citations/isbn/$isbn");
    if (null !== $citation) {  
      $this->cache_citation("isbn:$isbn", $citation);
    }  
    return $citation;
  }

  protected function query_worldcat(string $endpoint, array $parameters = []) {
    try {
      $query_parameters = array_merge($parameters,
        ["cformat" => "chicago", "wskey" => $this->worldcat_key]);
      
      Log::info("Querying " . $this->worldcat_base . "/$endpoint");
      
      $response = $this->client->get($this->worldcat_base . "/$endpoint",
        ["query" => $query_parameters]);
    } catch (ClientException $api_error) {
      throw new ConfigurationException("Could not contact OCLC Worldcat API services", 400,
        $api_error);
    }

    if (Str::endsWith($response->getBody(), "Record does not exist")) {
      return null;
    } 
    
    $citation = strip_tags($response->getBody(), "<i><u><span>");
    $citation = trim($citation);
    return $citation;  
  }

  /**
   * Adds the citation to Laravel's underlying caching mechanism with an expiration time of
   * 30 days
   */
  protected function cache_citation(string $key, string $citation) {
    Cache::add($key, $citation, 60*24*30);
  }
}
?>
