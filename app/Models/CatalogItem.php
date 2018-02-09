<?php

namespace App\Models;

/**
 * Object representation of a catalog record for working with RSS and JSON
 * feeds
 */
class CatalogItem {
  protected $title, $link, $description, $identifiers;

  public function __construct(array $properties = null) {
    $attributes = ['identifiers', 'description', 'link', 'title'];
    if (!$properties == null) {
      foreach($attributes as $key) {
        if (array_key_exists($key, $properties)) {
          $this->$key = $properties[$key];
        }
      }
    }
  }

  public function getTitle() { return $this->title; }
  public function setTitle(string $title) { $this->title = $title; } 
  
  public function getLink() { return $this->link; }
  public function setLink(string $link) { $this->link = $link; }

  public function getIdentifiers() { return $this->identifiers; }
  public function setIdentifiers(array $identifiers) { $this->identifier = $identifiers; }

  public function getDescription() { return $this->description; }
  public function setDescription(string $description) { $this->description = $description; }

  public function toJSON() {
    return json_encode([
      "title" => $this->title ?? "",
      "link" => $this->link ?? "",
      "description" => $this->description ?? ""
    ]);
  }
} 
