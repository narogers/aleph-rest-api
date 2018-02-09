<?php

namespace App\Http\Controllers;

use App\Services\CitationInterface;
use App\Services\ILSInterface;
use App\Services\OCLCInterface;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;

class OPACApiController extends Controller {
  protected $ils_client;

  public function __construct(ILSInterface $ils_client) {
    Config::set('session.driver', 'array');
    $this->ils_client = $ils_client;
  }

  /**
   * Return a formatted link with an embedded backlink into the library
   * catalog. Results are tabulated based on keyword match against the
   * string (or artist). 
   */
  public function getLinkForArtist(string $artist) {
    $link_details = $this->ils_client->link_for($artist, "artist");
    if (!empty($link_details)) {
      $link_details['query'] = $artist;
      return view("opac.backlink", 
        ["link" => $link_details[$artist],
         "query" => $artist]);
    }
  }

  /**
   * Return a formatted link with an embedded backlink into the library
   * catalog. Results are tabulated based on keyword match against the
   * object which should be formatted as an accession number (1960.81,
   * 2004.53, etc) 
   */
  public function getLinkForObject(string $accession_number) {
    $link_details = $this->ils_client->link_for($accession_number, "accession_number");
    if (!empty($link_details)) {
      $link_details['query'] = $accession_number;
      return view("opac.backlink", 
        ["link" => $link_details[$accession_number],
         "query" => $accession_number]);
    }
  }

  /**
   * Return a list of new titles as either JSON or RSS depending on the
   * Accept header. Titles are pulled from the OPAC - see the implementation
   * beneath ILSInterface to see how results are processed
   */
  public function getRecentTitles(Request $request) {
    $recent_titles = $this->ils_client->recent_titles();

    if ($request->wantsJson()) {
      return json_encode($recent_titles, JSON_FORCE_OBJECT);
    } else {
      return view('opac.rssfeed', [
        "feed" => [
          "title" => "Recent additions at Ingalls Library",
          "uri" => "http://opac.clevelandart.org",
          "description" => "Weekly catalog updates for " .
            today()->startOfWeek()->toFormattedDateString(),
        ],
        "records" => $recent_titles]);
    }
  }
}
