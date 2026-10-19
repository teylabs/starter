<?php

namespace App\Starter\SessionUi\Http\Middleware;

use App\Starter\SessionUi\PageShell;
use Illuminate\Http\Request;
use Inertia\Middleware;

final class HandleInertiaRequests extends Middleware
{
    public function share(Request $request): array
    {
        return [...parent::share($request), 'shell' => fn () => app(PageShell::class)->forRequest($request)->toArray()];
    }
}
