<?php
/**
 * Service provider for retrieving instances of OCLC Worldcat clients
 * using Laravel's contracts
 */
namespace App\Services;

use Illuminate\Support\ServiceProvider;

/**
 * @codeCoverageIgnore
 */
class OCLCServiceProvider extends ServiceProvider {
  /**
   * Loading will be deferred until needed
   */
  protected $defer = true;

  /**
   * Registers the implementation which will be returned when you request
   * an instance of the OCLC Interface
   */
  public function register() {
    $this->app->bind(OCLCInterface::class, WorldcatService::class);
  }

  public function provides() {
    return [OCLCInterface::class];
  }
} 
 ?>
