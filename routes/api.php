<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/
Route::prefix("citation")->group(function() {
  Route::get("aleph/{alephId}", "CitationApiController@getAlephCitation");
  Route::get("isbn/{isbn}/{style?}", "CitationApiController@getISBNCitation");
  Route::get("issn/{issn}/{style?}", "CitationApiController@getISSNCitation");
  Route::get("lc/{call_number}/{style?}", "CitationApiController@getLCCitation");
  Route::get("oclc/{call_number}/{style?}", "CitationApiController@getOCLCCitation");
});

Route::prefix("opac")->group(function() {
  Route::get("artist/{artist}", "OPACApiController@getLinkForArtist");
  Route::get("object/{id}", "OPACApiController@getLinkForObject");
  Route::get("recent_titles", "OPACApiController@getRecentTitles");
});
