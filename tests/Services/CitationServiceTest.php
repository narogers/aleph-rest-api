<?php
namespace Tests\Services;

use App\Services\CitationService;
use Tests\TestCase;

class CitationServiceTest extends TestCase {
  protected $citation_client;

  public function setUp() {
    parent::setUp();
    $this->citation_client = new CitationService();
  }

  /**
   * Smith, John. <i>Great American Novel.</i>
   */
  public function testAuthorAndTitle() {
    $properties = [
      'author' => "Smith, John,",
      'title' => "Great American Novel.",
      'publication' => []
    ];

    $citation = $this->citation_client->format($properties, "chicago");
    
    $this->assertEquals("Smith, John. <i>Great American Novel.</i>",
      $citation);
  }

  /**
   * Smith, John. <i>Great American Novel.</i> Anytown, USA: Amazon, 2018
   */
  public function testAuthorTitleAndPublication() {
    $properties = [
      'author' => "Smith, John,",
      'title' => "Great American Novel.",
      'publication' => [
        'location' => "Anytown, USA",
        'publisher' => "Amazon",
        'year' => "2018"
      ]
    ];

    $citation = $this->citation_client->format($properties, "chicago");
    
    $this->assertEquals(
      "Smith, John. <i>Great American Novel.</i> Anytown, USA: Amazon, 2018",
      $citation);
  }

  /**
   * <i>Great American Novel.</i> Anytown, USA: Amazon, 2018
   */
  public function testTitleAndPublication() {
    $properties = [
      'title' => "Great American Novel.",
      'publication' => [
        'location' => "Anytown, USA",
        'publisher' => "Amazon",
        'year' => "2018"
      ]
    ];

    $citation = $this->citation_client->format($properties, "chicago");
    
    $this->assertEquals(
      "<i>Great American Novel.</i> Anytown, USA: Amazon, 2018", $citation);
  } 
}
?>
