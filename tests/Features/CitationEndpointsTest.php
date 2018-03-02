<?php

namespace Tests\Features;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Mockery;
use Tests\TestCase;

/**
 * Ensures that the services are wired correctly through the routes so that
 * you can properly call them and get the expected results
 */
class CitationEndpointsTest extends TestCase {
  protected $mock_responses;

  public function setUp() {
    parent::setUp();
    $this->mock_responses = new MockHandler();
    $this->app->bind('GuzzleHttp\Client', function($app) {
      $handler = HandlerStack::create($this->mock_responses);
      return new Client(['handler' => $handler]);
    });
  }

  /**
   * /api/citation/aleph/{aleph_id}
   */
  public function testAlephCitation() {
    $xml_stub = file_get_contents(__DIR__ . "/../Fixtures/oclc-marc.xml");
    $this->mock_responses->append(
      new Response(200, ["Content-Type" => "text/xml"], $xml_stub)
    );

    $response = $this->get("/api/citation/aleph/123454321");
    $citation = "Lipsey, Roger. <i>Art of Thomas Merton.</i> Boston, Mass.: New Seeds, 2006.";

    $response->assertStatus(200);
    $this->assertEquals($citation, $response->getContent());
  }

  public function testNullAlephCitation() {
    $xml_stub = file_get_contents(__DIR__ . "/../Fixtures/empty-set.xml");
    $this->mock_responses->append(
      new Response(200, ["Content-Type" => "text/xml"], $xml_stub)
    );

    $response = $this->get("/api/citation/aleph/123454321");

    $response->assertStatus(400);
  }

  /**
   * /api/citation/isbn/{isbn}
   */
  public function testISBNCitation() {
    $citation = "Smith, John. <i>My Life as the Doctor.</i> London, England: Virgin.";
    $this->mock_responses->append(
      new Response(200, ["Content-Type" => "text/xml"], "<p>$citation</p>")
    );

    $response = $this->get("/api/citation/isbn/123454321X/chicago");

    $response->assertStatus(200);
    $this->assertEquals($citation, $response->getContent());
  }

  /**
   * /api/citation/issn/{issn}
   */
  public function testISSNCitation() {
    $citation = "Smith, John. <i>My Life as the Doctor.</i> London, England: Virgin.";
    $this->mock_responses->append(
      new Response(200, ["Content-Type" => "text/xml"], "<p>$citation</p>")
    );

    $response = $this->get("/api/citation/issn/123454321X");

    $response->assertStatus(200);
    $this->assertEquals($citation, $response->getContent());
  }

  /**
   * /api/citation/lc/{call_number}
   */
  public function testLCCitation() {
    $citation = "Smith, John. <i>My Life as the Doctor.</i> London, England: Virgin.";
    $this->mock_responses->append(
      new Response(200, ["Content-Type" => "text/xml"], "<p>$citation</p>")
    );

    $response = $this->get("/api/citation/lc/123454321X");

    $response->assertStatus(200);
    $this->assertEquals($citation, $response->getContent());
  }

  /**
   * /api/citation/oclc/{call_number}
   */
  public function testOCLCCitation() {
    $xml_stub = ["info:srw/diagnostic/1/65Record does not exist",
      file_get_contents(__DIR__ . "/../Fixtures/oclc-set.xml"),
      file_get_contents(__DIR__ . "/../Fixtures/oclc-marc.xml")];
    $this->mock_responses->append(
      new Response(200, ["Content-Type" => "text/plain"], $xml_stub[0]),
      new Response(200, ["Content-Type" => "text/xml"], $xml_stub[1]),
      new Response(200, ["Content-Type" => "text/xml"], $xml_stub[2])
    );

    $response = $this->get("/api/citation/oclc/0864213579");
    $citation = "Lipsey, Roger. <i>Art of Thomas Merton.</i> Boston, Mass.: New Seeds, 2006.";

    $response->assertStatus(200);
    $this->assertEquals($citation, $response->getContent());
  }

  public function testNullOCLCCitation() {
    $xml_stub = file_get_contents(__DIR__ . "/../Fixtures/empty-set.xml");
    $this->mock_responses->append(
      new Response(200, ["Content-Type" => "text/xml"], $xml_stub)
    );

    $response = $this->get("/api/citation/aleph/123454321");

    $response->assertStatus(400);
  }

  public function testTurabianFormattedCitation() {
    $citation = "Lipsey, Roger, and Thomas Merton. <i>Angelic Mistakes: The Art of Thomas Merton</i>. Boston, Mass: New Seeds, 2006.";
    $client_mock = Mockery::mock('App\Services\OCLCInterface');
    $client_mock->shouldReceive("citation_for")
      ->once()
      ->with('123454321X', 'oclc', 'turabian')
      ->andReturn($citation);
    $this->app->instance('App\Services\OCLCInterface', $client_mock);

    $response = $this->get("/api/citation/oclc/123454321X/turabian");

    $response->assertStatus(200);
    $this->assertEquals($citation, $response->getContent());
  }

}


