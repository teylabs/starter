<?php

use App\Starter\Core\StarterRegistry;
use App\Starter\FeatureProvider;
use App\Starter\SessionUi\Http\Middleware\HandleInertiaRequests;
use App\Support\InstallProvider;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Inertia\Inertia;

require dirname(__DIR__).'/vendor/autoload.php';
$app = Application::configure(basePath: dirname(__DIR__))->withMiddleware(fn ($middleware) => $middleware->web(append: [HandleInertiaRequests::class]))->withRouting(web: dirname(__DIR__).'/routes/web.php', then: fn () => app(StarterRegistry::class)->loadRoutes())->withExceptions(function ($exceptions): void {
    $exceptions->render(function (RuntimeException $e, Request $request) {
        $registry = app(StarterRegistry::class);
        $status = $registry->status($e->getMessage());
        if ($status !== null) {
            if ($request->expectsJson() && ! $request->header('X-Inertia')) {
                return response()->json(['error' => $e->getMessage()], $status);
            }

            return Inertia::render('Error', ['status' => $status, 'message' => $registry->message($e->getMessage()) ?? 'This request is unavailable. Your access or the resource may have changed.', 'recovery_url' => '/workspaces', 'recovery_label' => 'Return to your workspaces'])->toResponse($request)->setStatusCode($status);
        }
    });
})->withProviders([FeatureProvider::class, InstallProvider::class])->create();
$app->make(Kernel::class)->bootstrap();

return $app;
