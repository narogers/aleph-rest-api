<?php

namespace App\Http\Controllers;

use App\Services\CitationInterface;
use App\Services\ILSInterface;
use App\Services\OCLCInterface;
use Illuminate\Support\Facades\Config;
use Illuminate\Http\Request;

class CitationApiController extends Controller {
  protected $citation_service, $ils_client, $oclc_client;

  public function __construct(CitationInterface $citation_service, 
     ILSInterface $ils_client, OCLCInterface $oclc_client) {
    Config::set('session.driver', 'array');
    $this->citation_service = $citation_service;
    $this->ils_client = $ils_client;
    $this->oclc_client = $oclc_client;
  }

  /**
   * Pulls record information from the local ILS and formats it into a
   * Chicago style citation
   */
  public function getAlephCitation(string $alephId) {
    $attributes = $this->ils_client->metadata_for($alephId);
    if (null == $attributes) {
      return response("Invalid Aleph identifier : $alephId", 400);
    } else {
      return $this->citation_service->format($attributes);
    }
  }

  /**
   * Provided an ISBN will return a string containing the well formatted 
   * citation from OCLC. If invalid an error will be thrown instead
   */
  public function getISBNCitation(string $isbn) {
    return $this->oclc_client->citation_for($isbn, "isbn");
  }

  public function getISSNCitation(string $issn) {
    return $this->oclc_client->citation_for($issn, "issn");
  }

  /**
   * Retrieve a citation by Library of Congress call number
   */
  public function getLCCitation(string $lc_call_number) {
    return $this->oclc_client->citation_for($lc_call_number, "lc");
  }

  /**
   * Retrieves a citation from Worldcat based on OCLC call number. If the
   * record cannot be found it will fall back to using the local catalog
   * and attempt to construct a citation from that instead
   */
  public function getOCLCCitation(string $oclc) {
    $citation = $this->oclc_client->citation_for($oclc, "oclc");
    if (null == $citation) {
      $attributes = $this->ils_client->metadata_for($oclc, "oclc");
      if (null == $attributes) {
        return response("Invalid OCLC identifer : $oclc", 400);
      } else {
        $citation = $this->citation_service->format($attributes);
      }
    }
    
    return $citation;
  }

}
