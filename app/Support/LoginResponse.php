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
        if ($request->wantsJson()) {
            return response()->json(['two_factor' => false]);
        }
        $reachable = array_values(array_filter(app(PageShell::class)->forRequest($request)->switcher['choices'], fn (array $workspace) => $workspace['url'] !== null));

        return redirect()->intended(count($reachable) === 1 ? $reachable[0]['url'] : Fortify::redirects('login'));
    }
}
