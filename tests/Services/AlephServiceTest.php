<?php
namespace Tests\Services;

use App\Services\AlephService;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Tests\TestCase;

class AlephServiceTest extends TestCase {
   protected $mock_responses, $http_client, $aleph_client;

  /**
   * Set up a persistant Guzzle client and handler so that test cases simply
   * need to inject their own responses by calling $mock->append
   */
  public function setUp() {
    parent::setUp();
    $this->mock_responses = new MockHandler();
    $handler = HandlerStack::create($this->mock_responses);
    $this->http_client = new Client(['handler' => $handler]);
    $this->aleph_client = new AlephService($this->http_client);
  }

  /**
   * Test whether link_for handles a search for an artist with known
   * results
   */
  public function testLinkForValidArtist() {
    $xml_stub = file_get_contents(__DIR__ . "/../Fixtures/artist-set.xml");
    $this->mock_responses->append(new Response(200, ['Content-Type' => 'text/xml'], $xml_stub));

    $backlink = $this->aleph_client->link_for("Doctor Who", "artist");
    
    $this->assertArrayHasKey('Doctor Who', $backlink);
    $this->assertArrayHasKey('uri', $backlink['Doctor Who']);
    $this->assertArrayHasKey('count', $backlink['Doctor Who']);
    $this->assertEquals(274, $backlink['Doctor Who']['count']);
  }

  /**
   * Ensure that if no results are found for an artist search you get back
   * an empty response as per the previous implementation
   */
  public function testLinkForBadArtist() {
    $xml_stub = file_get_contents(__DIR__ . "/../Fixtures/empty-set.xml");
    $this->mock_responses->append(new Response(200, ['Content-Type' => 'text/xml'], $xml_stub));
    
    $backlink = $this->aleph_client->link_for("The Doctor", "artist");

    $this->assertNotNull($backlink);
    $this->assertEmpty($backlink);
  }

  public function testLinkForValidAccessionNumber() {
    $xml_stub = file_get_contents(__DIR__ . "/../Fixtures/accessionnumber-set.xml");
    $this->mock_responses->append(new Response(200, ["Content-Type" => "text/xml"], $xml_stub));
    
    $backlink = $this->aleph_client->link_for("1943.244", "accession_number");

    $this->assertArrayHasKey('1943.244', $backlink);
    $this->assertArrayHasKey('uri', $backlink['1943.244']);
    $this->assertArrayHasKey('count', $backlink['1943.244']);
    $this->assertEquals(214, $backlink['1943.244']['count']);
  }

  /**
   * Ensure that if no results are found for an artist search you get back
   * an empty response as per the previous implementation
   */
  public function testLinkForBadAccessionNumber() {
    $xml_stub = file_get_contents(__DIR__ . "/../Fixtures/empty-set.xml");
    $this->mock_responses->append(new Response(200, ['Content-Type' => 'text/xml'], $xml_stub));
    
    $backlink = $this->aleph_client->link_for("2903.333", "accession_number");

    $this->assertNotNull($backlink);
    $this->assertEmpty($backlink);
  }

  /**
   * Given a valid OCLC number ensure that it extract well formed metadata
   * from MARC
   */
  public function testMetadataForValidOCLCNumber() {
    $xml_stubs = [
      file_get_contents(__DIR__ . "/../Fixtures/oclc-set.xml"),
      file_get_contents(__DIR__ . "/../Fixtures/oclc-marc.xml")
    ];
    $this->mock_responses->append(
      new Response(200, ["Content-Type" => "text/xml"], $xml_stubs[0]),
      new Response(200, ["Content-Type" => "text/xml"], $xml_stubs[1])
    );

    $metadata = $this->aleph_client->metadata_for("000002", "oclc");
    $this->assertEquals("Lipsey, Roger,", $metadata['author']);
    $this->assertEquals("Art of Thomas Merton", $metadata['title']);
    $this->assertEquals("Boston, Mass. :", $metadata['publication']['location']);
    $this->assertEquals("New Seeds ;", $metadata['publication']['publisher']);
    $this->assertEquals("2006.", $metadata['publication']['year']);
    $this->assertEquals("000001", $metadata['identifier']['aleph']);
    $this->assertEquals("000002", $metadata['identifier']['oclc']);
    $this->assertEquals("000003", $metadata['identifier']['isbn']);
  }

  /**
   * Should return an empty record due to bad OCLC identifier
   */
  public function testMetadataForBadOCLCNumber() {
    $xml_stub = file_get_contents(__DIR__ . "/../Fixtures/empty-set.xml");
    $this->mock_responses->append(
      new Response(200, ["Content-Type" => "text/xml"], $xml_stub));
    
    $metadata = $this->aleph_client->metadata_for("99999999", "oclc");
    
    $this->assertNull($metadata);
  }

  /**
   * Should return a valid record based on the Aleph identifier present
   * in the ILS. An invalid Aleph identifer is already covered by the same
   * test case as OCLC call numbers
   */
  public function testMetadataForValidAlephId() {
    $xml_stub = file_get_contents(__DIR__ . "/../Fixtures/alephid-marc.xml");
    $this->mock_responses->append(
      new Response(200, ["Content-Type" => "text/xml"], $xml_stub)
    );

    $metadata = $this->aleph_client->metadata_for("999999", "aleph_id");

    $this->assertEquals("Lipsey, Roger,", $metadata['author']);
    $this->assertEquals("Art of Thomas Merton", $metadata['title']);
    $this->assertEquals("Boston, Mass. :", $metadata['publication']['location']);
    $this->assertEquals("New Seeds ;", $metadata['publication']['publisher']);
    $this->assertEquals("2006.", $metadata['publication']['year']);
    $this->assertEquals("999999", $metadata['identifier']['aleph']);
    $this->assertEquals("222222", $metadata['identifier']['oclc']);
    $this->assertEquals("333333X", $metadata['identifier']['isbn']);
  }

  /**
   * Should return a list of the five most recent titles added to the
   * catalog based on date
   */
  public function testRecentTitles() {
    $xml_stubs = [
      file_get_contents(__DIR__ . "/../Fixtures/recenttitles-set.xml"),
      file_get_contents(__DIR__ . "/../Fixtures/recenttitles-marc.xml")
    ];
    $this->mock_responses->append(
      new Response(200, ["Content-Type" => "text/xml"], $xml_stubs[0]),
      new Response(200, ["Content-Type" => "text/xml"], $xml_stubs[1])
    );

    $titles = $this->aleph_client->recent_titles();
    $summary = $titles[0];
 
    $this->assertCount(5, $titles);
    $this->assertEquals("Shinpan sekai jinmei jiten.", $summary->getTitle());
    $this->assertStringEndsWith("/F/?func=find-c&ccl_term=sys%3D123454321",
      $summary->getLink());
    $this->assertEquals("Location: .S55 1973 DS32", $summary->getDescription());
  } 

  /**
   * Should return an empty set indicating no new records found
   */
  public function testRecentTitlesWithEmptyResult() {
    $xml_stub = file_get_contents(__DIR__ . "/../Fixtures/empty-set.xml");
    $this->mock_responses->append(
      new Response(200, ["Content-Type" => "text/xml"], $xml_stub));
    
    $titles = $this->aleph_client->recent_titles();
    
    $this->assertEmpty($titles);
  }
}
?>
