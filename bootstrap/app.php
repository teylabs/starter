<?php

use App\Providers\AppServiceProvider;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Tey\Kit\Features\SessionUi\Http\Middleware\HandleInertiaRequests;

require dirname(__DIR__).'/vendor/autoload.php';
// tey/kit's auto-discovered FeaturesServiceProvider registers the adopted features' routes and error pages. The HTTP
// kernel replaces the web group when it resolves, after the kit has booted, so session-ui's middleware is appended here.
// TLS ends at the platform's proxy (Laravel Cloud), so the forwarded scheme is trusted: the app is never reachable
// except through that proxy. The host is pinned to APP_URL's, so a forged forwarded host cannot become the URL that
// reset and invitation links are built from. Trusted hosts are regular expressions, so it is anchored and escaped.
// Laravel skips the check locally and in unit tests. `/up` is the monitoring check (the kit adds its cheap production checks to it); nothing deploys on it.
$app = Application::configure(basePath: dirname(__DIR__))->withRouting(web: dirname(__DIR__).'/routes/web.php', commands: dirname(__DIR__).'/routes/console.php', health: '/up')->withMiddleware(fn ($middleware) => $middleware->trustProxies(at: '*')->trustHosts(at: fn () => ['^'.preg_quote((string) parse_url((string) config('app.url'), PHP_URL_HOST)).'$'], subdomains: false)->web(append: [HandleInertiaRequests::class]))->withExceptions()->withProviders([AppServiceProvider::class])->create();
$app->make(Kernel::class)->bootstrap();

return $app;
