<?php

namespace Tests\Features;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Tests\TestCase;

/**
 * Ensures that the services are wired correctly through the routes so that
 * you can properly call them and get the expected results
 */
class CatalogEndpointsTest extends TestCase {
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
   * /api/opac/recent_titles
   */
  public function testRecentTitlesEndpoint() {
    $xml_stubs = [
      file_get_contents(__DIR__ . "/../Fixtures/recenttitles-set.xml"),
      file_get_contents(__DIR__ . "/../Fixtures/recenttitles-marc.xml")
    ];
    $this->mock_responses->append(
      new Response(200, ["Content-Type" => "text/xml"], $xml_stubs[0]),
      new Response(200, ["Content-Type" => "text/xml"], $xml_stubs[1])
    );

    $response = $this->get("/api/opac/recent_titles");
    $data = $response->getOriginalContent()->getData();

    $response->assertStatus(200);
    $response->assertViewHas("feed");
    $this->assertCount(5, $data["records"]);
  }

  /**
   * /api/opac/artist/{artist}
   */
  public function testBacklinkForUnknownArtist() {
    $xml_stub = file_get_contents(__DIR__ . "/../Fixtures/empty-set.xml");
    $this->mock_responses->append(
      new Response(200, ["Content-Type" => "text/xml"], $xml_stub)
    );

    $response = $this->get("/api/opac/artist/Auguste+Renoir");

    $response->assertStatus(204);
    $this->assertEquals("", $response->getContent());
  }
 
  public function testBacklinkForArtist() {
    $xml_stub = file_get_contents(__DIR__ . "/../Fixtures/artist-set.xml");
    $this->mock_responses->append(
      new Response(200, ["Content-Type" => "text/xml"], $xml_stub)
    );

    $response = $this->get("/api/opac/artist/Auguste+Renoir");
    $data = $response->getOriginalContent()->getData();

    $response->assertStatus(200);
    $this->assertEquals('<a href="http://localhost/opac/?func=find-c&ccl_term=wsu%3DAuguste%2BRenoir" target="_blank" rel="nofollow">Library materials about Auguste Renoir (274)</a>', trim($response->getContent()));
  }

  /**
   * /api/opac/object/{accession_number}
   */
  public function testBacklinkForObject() {
    $xml_stub = file_get_contents(__DIR__ . "/../Fixtures/accessionnumber-set.xml");
    $this->mock_responses->append(
      new Response(200, ["Content-Type" => "text/xml"], $xml_stub)
    );

    $response = $this->get("/api/opac/object/1935.280");
    $data = $response->getOriginalContent()->getData();

    $response->assertStatus(200);
    $this->assertEquals('<a href="http://localhost/opac/?func=find-c&ccl_term=cmaan%3D1935%2A280" target="_blank" rel="nofollow">Library materials about 1935.280 (214)</a>', trim($response->getContent()));
  }

  public function testBacklinkForUnknownObject() {
    $xml_stub = file_get_contents(__DIR__ . "/../Fixtures/empty-set.xml");
    $this->mock_responses->append(
      new Response(200, ["Content-Type" => "text/xml"], $xml_stub)
    );

    $response = $this->get("/api/opac/object/9999.99");

    $response->assertStatus(204);
    $this->assertEquals("", $response->getContent());
  }
}
