<?php

use App\Http\Middleware\ForceHttpsForPublicHost;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\AuthenticateCoreApi;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->trustProxies(at: '*');
        $middleware->append(ForceHttpsForPublicHost::class);
        $middleware->append(SecurityHeaders::class);
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'core.auth' => AuthenticateCoreApi::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->respond(function (Response $response, $exception, Request $request) {
            if ($response->getStatusCode() === 419 && $request->is('login')) {
                return redirect()->route('login')->with(
                    'status',
                    'Sesi login telah diperbarui. Silakan masuk kembali.'
                );
            }

            return $response;
        });
    })->create();
