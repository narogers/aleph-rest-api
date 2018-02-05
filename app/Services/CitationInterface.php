<?php
/**
 * Contract for formatting citations into different styles
 */
namespace App\Services;

interface CitationInterface {
  /**
   * Formats a citation according to a specific standard using the
   * values defined in the associative array
   *
   * @param array $metadata
   * @param string $format
   * @return string 
   */

  public function format(array $metadata, string $format);
}
?>
