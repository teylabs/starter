<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Support\FoundationProvider;
use App\Support\InstallProvider;
use App\Support\TransportProvider;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Inertia\Inertia;

require dirname(__DIR__).'/vendor/autoload.php';
$app = Application::configure(basePath: dirname(__DIR__))->withMiddleware(fn ($middleware) => $middleware->web(append: [HandleInertiaRequests::class]))->withRouting(web: dirname(__DIR__).'/routes/web.php', then: fn () => require dirname(__DIR__).'/routes/mcp.php')->withExceptions(function ($exceptions): void {
    $exceptions->render(function (RuntimeException $e, Request $request) {
        $status = match ($e->getMessage()) {
            'workspace_not_found', 'workspace_mismatch', 'note_not_found' => 404,
            'account_inactive', 'verification_required', 'admission_required', 'permission_denied', 'connection_denied', 'capability_unavailable', 'entitlement_required' => 403,
            'stale_settings', 'intent_conflict', 'stale_note', 'note_intent_conflict' => 409,
            'unknown_arguments' => 422,
            'connection_intent_conflict', 'connection_revoked', 'connection_active' => 409,
            default => null,
        };
        if ($status !== null) {
            if ($request->expectsJson() && ! $request->header('X-Inertia')) {
                return response()->json(['error' => $e->getMessage()], $status);
            }
            $messages = ['stale_settings' => 'These settings changed since you opened the form. Reload and review the current values.', 'stale_note' => 'This note changed since you opened the form. Reload and review it before trying again.'];

            return Inertia::render('Error', ['status' => $status, 'message' => $messages[$e->getMessage()] ?? 'This request is unavailable. Your access or the resource may have changed.', 'recovery_url' => '/workspaces', 'recovery_label' => 'Return to your workspaces'])->toResponse($request)->setStatusCode($status);
        }
    });
})->withProviders([InstallProvider::class, FoundationProvider::class, TransportProvider::class])->create();
$app->make(Kernel::class)->bootstrap();

return $app;
