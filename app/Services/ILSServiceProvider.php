<?php
  /**
   * Service provider for retrieving instances of OCLC Worldcat clients
   * using Laravel's contracts
   */
  namespace App\Services;

  use Illuminate\Support\ServiceProvider;

  class ILSServiceProvider extends ServiceProvider {
    /**
     * Loading will be deferred until needed
     */
    protected $defer = true;

    /**
     * Registers the implementation which will be returned when you request
     * an instance of the OCLC Interface
     */
    public function register() {
      $this->app->bind(ILSInterface::class, AlephService::class);
    }

    public function provides() {
      return [ILSInterface::class];
    }
  } 
?>
