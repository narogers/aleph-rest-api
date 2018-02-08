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
     * Query Worldcat for a citation if it does not already exist in the
     * current cache. If no citation is found the function will return a
     * a NULL value which can be used downstream in error handling
     */
    public function citation_for(string $oclc_number) {
      $citation = Cache::get($oclc_number);
      if (null !== $citation) {
        return $citation;
      }

      try {
        $response = $this->client->get(
          $this->worldcat_base . "_base/catalog/content/citations/$oclc_number",
          ["query" => [
            "cformat" => "chicago", 
            "wskey" => $this->worldcat_key]]);
      } catch (ClientException $api_error) {
        throw new ConfigurationException("Could not contact OCLC Worldcat API services", 400, $api_error);
      }

      if (Str::endsWith($response->getBody(), "Record does not exist")) {
        $citation = null;
      } else {
        $citation = strip_tags($response->getBody(), "<i><u><span>");
        $citation = trim($citation);
        // Cached citations should expire in a month
        Cache::add($oclc_number, $citation, 60*24*30);
      }  
      return $citation;
    }
  }
 ?>

