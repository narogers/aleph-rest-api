<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request; 
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class ApiLogger {
  protected $start_time_in_ms, $end_time_in_ms;

  public function handle(Request $request, Closure $next) {
    $this->start_time_in_ms = microtime(true);
    return $next($request);
  }

  public function terminate(Request $request, Response $response) {
    $this->end_time_in_ms = microtime(true);
    $this->log($request, $response);
  }

  public function log(Request $request, Response $response) {
    $duration = $this->end_time_in_ms - $this->start_time_in_ms;
    $path = $request->path();
    $method = $request->getMethod();
    $ip = $request->getClientIp();
    $status = $response->getStatusCode();

    $log = "[$ip] $status {$method} {$path} - {$duration}ms";
    Log::info($log);
  }
}

