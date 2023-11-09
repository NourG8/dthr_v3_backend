<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use App\Traits\ApiResponser;
use Illuminate\Support\Str;
use Throwable;

class Handler extends ExceptionHandler
{
    use ApiResponser;

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
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        $this->reportable(function (Throwable $exception) {

        });

    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Throwable  $exception
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws \Throwable
     */
    public function render($request, Throwable $exception)
    {
        if ($exception instanceof HttpException) {
            $code = $exception->getStatusCode();
            $message = Response::$statusTexts[$code];
            return $this->errorResponse($message, $code);
        }

        if ($exception instanceof ModelNotFoundException) {
            return $this->errorResponse('internal.error_not_found', 404);
        }

        if ($exception instanceof ValidationException) {
            $errors = $exception->validator->errors()->first();
            return $this->errorResponse($errors, 422);
        }

        if ($exception instanceof AuthorizationException) {
            return $this->errorResponse('internal.error_unauthorized', 403);
        }

        if ($exception instanceof AuthenticationException) {
            return $this->errorResponse('internal.error_authentication', 401);
        }

        $message = $exception->getMessage();

        if (Str::contains($message, '[login]')) {
            $message = 'internal.error_token';
        }

        return $this->errorResponse($message, 500);
        // return $this->errorResponse('internal.error_general', 500);
    }
}
