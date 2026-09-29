<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SoloBeneficiario;
use App\Http\Middleware\SoloFuncionario;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['apariencia']);

        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'rol' => RoleMiddleware::class,
            'permiso' => PermissionMiddleware::class,
            'rol_o_permiso' => RoleOrPermissionMiddleware::class,
            'funcionario' => SoloFuncionario::class,
            'beneficiario' => SoloBeneficiario::class,
        ]);

        // Sin sesión en /mi-cuenta va al login del portal, no al de funcionarios.
        $middleware->redirectGuestsTo(
            fn (Request $request) => $request->is('mi-cuenta', 'mi-cuenta/*') ? route('portal.ingresar') : route('login'),
        );
        $middleware->redirectUsersTo(
            fn (Request $request) => $request->user()?->esBeneficiario() ? route('portal.inicio') : route('dashboard'),
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
