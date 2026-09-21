<?php

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(SecurityHeaders::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $isApi = fn (Request $request): bool => $request->is('api/*');

        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $isApi($request) || $request->expectsJson());

        // Model-not-found also ends up here; the default message would reveal
        // the model class name.
        $exceptions->render(fn (NotFoundHttpException $e, Request $request) => $isApi($request)
            ? response()->json(['message' => 'El recurso solicitado no existe.'], 404)
            : null);

        $exceptions->render(fn (MethodNotAllowedHttpException $e, Request $request) => $isApi($request)
            ? response()->json(['message' => 'Método HTTP no permitido para esta ruta.'], 405, $e->getHeaders())
            : null);

        $exceptions->render(fn (ThrottleRequestsException $e, Request $request) => $isApi($request)
            ? response()->json(
                ['message' => 'Has realizado demasiadas solicitudes. Espera un momento y vuelve a intentarlo.'],
                429,
                $e->getHeaders(),
            )
            : null);

        // Unexpected errors: generic message only, details stay in the logs.
        $exceptions->respond(function (Response $response, Throwable $e, Request $request) use ($isApi) {
            if ($response->getStatusCode() >= 500 && $isApi($request) && ! config('app.debug')) {
                return response()->json(
                    ['message' => 'Se ha producido un error interno. Inténtalo de nuevo más tarde.'],
                    $response->getStatusCode(),
                );
            }

            return $response;
        });
    })->create();
