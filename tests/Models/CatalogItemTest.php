<?php

namespace Tests\Models;

use App\Models\CatalogItem;
use Tests\TestCase;

class CatalogItemTest extends TestCase {
  /**
   * Tests that toJSON is empty by default
   */
  public function testEmptyJSONResponse() {
    $catalog_item = new CatalogItem();
    $json = $catalog_item->toJSON();

    $this->assertEquals('{"title":"","link":"","description":""}', $json);
  }

  public function testPartiallyEncodedJSONResponse() {
    $catalog_item = new CatalogItem(["title" => "JSON Record",
      "description" => "Missing a URI"]);
    $json = $catalog_item->toJSON();

    $this->assertEquals('{"title":"JSON Record","link":"","description":"Missing a URI"}', $json);
  }

  public function testCompleteJSONResponse() {
    $catalog_item = new CatalogItem([
      "title" => "JSON Record",
      "description" => "Missing a URI",
      "link" => "https://www.example.com"]);
    $json = $catalog_item->toJSON();

    $this->assertEquals('{"title":"JSON Record","link":"https:\/\/www.example.com","description":"Missing a URI"}', $json);
  }
}
