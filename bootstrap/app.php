<?php

use App\Providers\AppServiceProvider;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Tey\Kit\Features\SessionUi\Http\Middleware\HandleInertiaRequests;

require dirname(__DIR__).'/vendor/autoload.php';
// tey/kit's auto-discovered FeaturesServiceProvider registers the adopted features' routes and error pages. The HTTP
// kernel replaces the web group when it resolves, after the kit has booted, so session-ui's middleware is appended here.
$app = Application::configure(basePath: dirname(__DIR__))->withRouting(web: dirname(__DIR__).'/routes/web.php')->withMiddleware(fn ($middleware) => $middleware->web(append: [HandleInertiaRequests::class]))->withExceptions()->withProviders([AppServiceProvider::class])->create();
$app->make(Kernel::class)->bootstrap();

return $app;
