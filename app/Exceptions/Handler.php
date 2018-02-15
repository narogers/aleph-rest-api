<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Support\Facades\Log;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array
     */
    protected $dontFlash = [
        'password',
        'password_confirmation',
    ];

    /**
     * Report or log an exception.
     *
     * @param  \Exception  $exception
     * @return void
     */
    public function report(Exception $exception)
    {
      if ($this->isHttpException($exception)) {
        $request = request();
        $status = $exception->getStatusCode();
        $ip = $request->getClientIp();
        $method = $request->getMethod();
        $path = $request->path();       

        $log = "[$ip] $status {$method} {$path}";
        Log::error($log);
      } else { 
        parent::report($exception);
      }
    }

    /**
     * Render an exception into an HTTP response. As this API has no web
     * facing content return a 500 error instead of the default Laravel
     * response(s)
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Exception  $exception
     * @return \Illuminate\Http\Response
     */
    public function render($request, Exception $exception)
    {
      $response = ($this->isHttpException($exception)) ?
        response("Resource not available", 404) :
        response("Server could not handle request", 500);
      return $response;
    }
}
