<?php
/**
 * Service provider which allows for different types of citation formats
 */
namespace App\Services;

use Illuminate\Support\ServiceProvider;

/**
 * @codeCoverageIgnore
 */
class CitationServiceProvider extends ServiceProvider {
  /**
   * Defer loading until needed
   */
  protected $defer = true;

  /**
   * Registers which implementation will be returned when you request
   * an instance of the service
   */
  public function register() {
    $this->app->bind(CitationInterface::class, CitationService::class);
  }

  public function provides() {
    return [CitationInterface::class];
  }
}
?>
