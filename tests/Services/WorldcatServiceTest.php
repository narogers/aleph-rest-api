<?php
namespace Tests\Services;

use App\Exceptions\ConfigurationException;
use App\Services\WorldcatService;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Tests\TestCase;

class WorldcatServiceTest extends TestCase {
  protected $mock_responses, $oclc_client, $http_client;

  public function setUp() {
    parent::setUp();
    $this->mock_responses = new MockHandler();
    $handler = HandlerStack::create($this->mock_responses);
    $this->http_client = new Client(['handler' => $handler]);
    $this->oclc_client = new WorldcatService($this->http_client);
  }

  /**
   * Mock a request without providing the right WSKey in anticipation
   * that the application will gracefully return an error
   */
  public function testInvalidWsKeyParameter() {
    $html_stub = file_get_contents(__DIR__ . "/../Fixtures/wskey-error.html");
    $this->mock_responses->append(
      new Response(400, ["Content-Type" => "text/html"], $html_stub)
    );
 
    $this->expectException(ConfigurationException::class);
    $this->oclc_client->citation_for("777777777");
  }

  /**
   * Expect that if you request an OCLC call number which does not exist
   * you get an empty response
   */
  public function testCitationForInvalidOCLCNumber() {
    $this->mock_responses->append(
      new Response(200, ["Content-Type" => "text/plain"], 
        "info:srw/diagnostic/1/65Record does not exist"));

    $citation = $this->oclc_client->citation_for("6666666666");
   
    $this->assertNull($citation);
  }

  /**
   * Finally assert that a valid request generates a well formated Chicago
   * style citation
   */
  public function testValidOCLCCitation() {
    $this->mock_responses->append(
      new Response(200, ["Content-Type" => "text/plain"],
        '<p class="citation_style_CHICAGO">Lipsey, Roger, and Thomas Merton. 2006. <i>Angelic mistakes: the art of Thomas Merton</i>. Boston, Mass: New Seeds. </p>'));

    $citation = $this->oclc_client->citation_for("4444444444");

    $this->assertEquals('Lipsey, Roger, and Thomas Merton. 2006. <i>Angelic mistakes: the art of Thomas Merton</i>. Boston, Mass: New Seeds.', $citation);
  }

  public function testValidLCCitation() {
    $this->mock_responses->append(
      new Response(200, ["Content-Type" => "text/plain"],
        '<p class="citation_style_CHICAGO">Lipsey, Roger, and Thomas Merton. 2006. <i>Angelic mistakes: the art of Thomas Merton</i>. Boston, Mass: New Seeds. </p>'));

    $citation = $this->oclc_client->citation_for("11111111", "lc");

    $this->assertEquals('Lipsey, Roger, and Thomas Merton. 2006. <i>Angelic mistakes: the art of Thomas Merton</i>. Boston, Mass: New Seeds.', $citation);
  }

  public function testValidISBNCitation() {
    $this->mock_responses->append(
      new Response(200, ["Content-Type" => "text/plain"],
        '<p class="citation_style_CHICAGO">Lipsey, Roger, and Thomas Merton. 2006. <i>Angelic mistakes: the art of Thomas Merton</i>. Boston, Mass: New Seeds. </p>'));

    $citation = $this->oclc_client->citation_for("1231231234X", "isbn");

    $this->assertEquals('Lipsey, Roger, and Thomas Merton. 2006. <i>Angelic mistakes: the art of Thomas Merton</i>. Boston, Mass: New Seeds.', $citation);
  }

  public function testValidISSNCitation() {
    $this->mock_responses->append(
      new Response(200, ["Content-Type" => "text/plain"],
        '<p class="citation_style_CHICAGO">Lipsey, Roger, and Thomas Merton. 2006. <i>Angelic mistakes: the art of Thomas Merton</i>. Boston, Mass: New Seeds. </p>'));

    $citation = $this->oclc_client->citation_for("987651234", "issn");

    $this->assertEquals('Lipsey, Roger, and Thomas Merton. 2006. <i>Angelic mistakes: the art of Thomas Merton</i>. Boston, Mass: New Seeds.', $citation);
  }

  public function testTurabianFormattedCitation() {
    $this->mock_responses->append(
      new Response(200, ["Content-Type" => "text/plain"],
        '<p class="citation_style_TURABIAN">Lipsey, Roger, and Thomas Merton. <i>Angelic mistakes: the art of Thomas Merton</i>. Boston, Mass: New Seeds, 2006. </p>'));

    $citation = $this->oclc_client->citation_for("987651234", "oclc");

    $this->assertEquals('Lipsey, Roger, and Thomas Merton. <i>Angelic mistakes: the art of Thomas Merton</i>. Boston, Mass: New Seeds, 2006.', $citation);
  }
}
?>
