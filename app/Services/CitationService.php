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
        break;
      default:
        return null;
    }
  }

  /**
   * Takes properties from the metadata and creates a Chicago compliant
   * citation
   */
  public function format_as_chicago(array $metadata) {
    $author = $this->strip_punctuation($metadata['author']);
    $title = $this->strip_punctuation($metadata['title']);
    
    $publication = "";
    if (isset($metadata['publication']['location'])) {
      $publication .= $this->strip_punctuation($metadata['publication']['location']);
      $publication .= ": ";
      $publication .= $this->strip_punctuation($metadata['publication']['publisher']);
      $publication .= ", ";
      $publication .= $metadata['publication']['year'];
    }
    
    $citation = "$author. <i>$title.</i> $publication";
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
