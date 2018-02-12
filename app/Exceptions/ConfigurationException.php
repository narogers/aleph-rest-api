<?php
namespace App\Exceptions;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * @codeCoverageIgnore
 */
class ConfigurationException extends Exception {
  /**
   * Raise a 500 error and pass it along to the client to inform them that
   * the API is broken
   */
  public function render(Request $request) {
    return response()->view('errors.fatal', [], 500);
  }

  /**
   * Log the exception to the application so administrators can do
   * additional troubleshooting
   */
  public function report(Exception $e) {
    Log::critical("The application could not process a request due to an error with the internal configuration");
    Log::critical($e);
  }
}
?>
