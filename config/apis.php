<?php

return [
  /**
   * library should be set to the Aleph library that you wish to perform
   * queries against (pwd20, pwd50, etc)
   *
   * uri should point to the endpoint from which X service queries can be
   * performed
   */
  'aleph' => [
    'library' => env("aleph.library", "default"),
    'uri' => env("aleph.uri", "http://localhost:8991/X/"),
  ],

  /**
   * The stylesheet path should be configured relative to the base of
   * the application. For instance the default below might expand to
   * /var/www/html/oai-to-marc.xsl
   */
  'marc' => [
    'stylesheet' => base_path(env("marc.stylesheet", "oai-to-marc.xsl")),
  ],

  /**
   * Base path for the library catalog which will be used to construct
   * links back into the OPAC
   */
  'opac' => [
    'base_uri' => env("opac.base_uri", "http://localhost"),
  ],

  /**
   * Worldcat API key provided by OCLC. One can be requested at 
   * https://platform.worldcat.org/api-explorer/apis if not already
   * available
   */
  'oclc' => [
    'key' => env("oclc.key"),
  ], 
];
