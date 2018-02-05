<?php

return [
  'aleph' => [
    'library' => env("aleph.library", "default"),
    'uri' => env("aleph.uri", "http://localhost:8991/X/")
  ],

  'marc' => [
    'stylesheet' => env("marc.stylesheet", "oai-to-marc.xsl")
  ],

  'opac' => [
    'base_uri' => env("opac.base_uri", "http://localhost")
  ],

  'oclc' => [
    'key' => env("oclc.key", "changeMe")
  ] 
];

?>
