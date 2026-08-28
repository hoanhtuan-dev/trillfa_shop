<?php

use App\Console\Commands\ProcessStudioGenerations;
use App\Http\Middleware\EnsureUserIsAdmin;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withCommands([ProcessStudioGenerations::class])
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();

// Trillfa Fa uses 'public_html' as the web root (Hostinger shared hosting).
// Rebinding public_path() keeps public_path(), secure asset resolution,
// 'php artisan serve' and 'storage:link' all pointing at public_html/.
$app->usePublicPath(base_path('public_html'));

return $app;
