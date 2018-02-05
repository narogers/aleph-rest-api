<?php
/**
 * Contract for RESTful services on top of the library ILS. For a reference
 * implementation look at AlephService
 */
namespace App\Services;

interface ILSInterface {
  /**
   * Retrieves the MARC record from the ILS and extracts a number of fields
   * which can be used for downstream processing
   */
  public function metadata_for(string $call_number, string $type);
  
  /**
   * Generate a back link into the catalog based on different attributes of
   * the catalog. For additional information see the implementation in the
   * AlephService 
   */
  public function link_for(string $property, string $type);

  /**
   * Retrieve a list of recent titles from the ILS
   */
  public function recent_titles();
}
?>
