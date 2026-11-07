<?php

namespace App\Support;

use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Fortify;
use Tey\Kit\Features\SessionUi\PageShell;

/** After sign-in, a user with exactly one reachable workspace lands in it; two or more (or none) see the chooser. An intended URL still wins. */
final class LoginResponse implements LoginResponseContract
{
    public function toResponse($request)
    {
        // inertia.pure: package Fortify's LoginResponse contract answers a JSON sign-in (an API client, the tests' postJson) as Fortify's own response does.
        if ($request->wantsJson()) {
            // inertia.pure: package the same Fortify contract's JSON body.
            return response()->json(['two_factor' => false]);
        }
        $reachable = array_values(array_filter(app(PageShell::class)->choices($request), fn (array $workspace) => $workspace['url'] !== null));

        return redirect()->intended(count($reachable) === 1 ? $reachable[0]['url'] : Fortify::redirects('login'));
    }
}
