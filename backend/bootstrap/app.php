<?php

use App\Exceptions\InventoryException;
use App\Http\Middleware\EnsureActiveUser;
use App\Http\Middleware\RequestContext;
use App\Http\Responses\ApiError;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(RequestContext::class);
        $middleware->alias(['active' => EnsureActiveUser::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (QueryException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }
            $code = (int) ($exception->errorInfo[1] ?? 0);
            if (in_array($code, [1205, 1213], true)) {
                return (new InventoryException('INVENTORY_BUSY', 'Inventory is busy. Retry with the same idempotency key.', 503))->render();
            }
            if ($code === 1062) {
                return (new InventoryException('DUPLICATE_RECORD', 'A record with this unique value already exists.', 422))->render();
            }

            return null;
        });
        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $request->is('api/*'));
        $exceptions->respond(fn (Response $response) => ApiError::fromResponse($response));
    })->create();
