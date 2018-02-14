<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request; 
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class ApiLogger {
  public function terminate(Request $request, Response $response) {
    $this->log($request, $response);
  }

  public function log(Request $request, Response $response) {
    $path = $request->path();
    $method = $request->getMethod();
    $ip = $request->getClientIp();
    $status = $response->getStatusCode();

    $log = "[$ip] $status {$method} {$path}";
    Log::info($log);
  }
}

