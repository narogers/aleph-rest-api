<?php
  /**
   * Interface for interacting with OCLC Worldcat RESTful services
   */
  namespace App\Services;

  interface OCLCInterface {
    /**
     * Look up a citation in Worldcat OCLC
     *
     * @param OCLC Call Number
     */
      public function citation_for(string $identifier, string $type, 
        string $citation_style); 
  }
 ?>
