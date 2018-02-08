<?php
/**
 * Implementation for citations which defaults to Chicago style. Additional
 * formats can be added by writing a new format_as_[style] method and
 * updating th interface to delegate appropriately
 */
namespace App\Services;

class CitationService implements CitationInterface {
  /**
   * Basic constructor where any initialization can take place
   */
  public function __construct() {}

  /**
   * Basic implementation which delegates all citation formatting requests
   * to Chicago style or an empty string
   */
  public function format(array $metadata, string $format="chicago") {
    switch ($format) {
      case "chicago":
        return $this->format_as_chicago($metadata);
    }
  }

  /**
   * Takes properties from the metadata and creates a Chicago compliant
   * citation
   */
  public function format_as_chicago(array $metadata) {
    $citation = "";
    
    if (array_key_exists("author", $metadata)) {
      $citation .= $this->strip_punctuation($metadata['author']);
      $citation .= ". ";
    }

    $citation .= "<i>";
    $citation .= $this->strip_punctuation($metadata['title']);
    $citation .= ".</i>";
   
    if (isset($metadata['publication']['location'])) {
      $citation .= " ";
      $citation .= $this->strip_punctuation($metadata['publication']['location']);
      $citation .= ": ";
      $citation .= $this->strip_punctuation($metadata['publication']['publisher']);
      $citation .= ", ";
      $citation .= $metadata['publication']['year'];
    }
    
    return $citation; 
  }

  /**
   * Helper method to strip punctuation from the end of a string including
   * periods, commas, and semicolons
   */
  protected function strip_punctuation(string $value) {
    $value = preg_replace("/[,.;:]$/", "", trim($value));
    return trim($value);
  }
}
?>
