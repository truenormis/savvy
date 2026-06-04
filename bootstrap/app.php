<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO,
        );
        $middleware->preventRequestsDuringMaintenance(except: [
            'livez',
        ]);
        $middleware->encryptCookies(except: [
            'svy_session',
            '__Host-svy_session',
            'svy_csrf',
        ]);
        $middleware->alias([
            'session' => \App\Http\Middleware\AuthenticateSession::class,
            'csrf' => \App\Http\Middleware\VerifyCsrfToken::class,
            'role' => \App\Http\Middleware\RoleMiddleware::class,
            'write' => \App\Http\Middleware\WriteAccessMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\App\Services\Upload\UploadException $e) {
            return response()->json(['message' => $e->getMessage()], $e->status());
        });
        $exceptions->render(function (\App\Services\Auth\Webauthn\WebauthnException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        });
    })->create();
