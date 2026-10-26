<?php

require_once __DIR__.'/../Concerns/FreshClone.php';

use Illuminate\Contracts\Console\Kernel;
use Tests\Concerns\FreshClone;
use Tey\Concierge\Actions\InviteMember;
use Tey\Concierge\LocalAuth\LocalAuthServiceProvider;
use Tey\Concierge\Support\ConciergeModels;
use Tey\Concierge\Support\GrantSet;
use Tey\Kit\Features\Workspaces\Models\Workspace;

// The guest cards (sign-in, the invitation link) are not settings forms: no sticky FormActions bar, one full-width primary button on its own line, the
// remember-me row above it, and everything aligned to the card's left edge, at phone and desktop widths.
function cardLayout(mixed $page): array
{
    return $page->script('() => { const card = document.querySelector("[data-guest-card]"); const box = card.getBoundingClientRect(); const cs = getComputedStyle(card); const inner = box.width - parseFloat(cs.paddingLeft) - parseFloat(cs.paddingRight); const buttons = [...card.querySelectorAll("form button[type=submit], form button:not([type])")].filter((b) => !b.closest("[data-slot=\"checkbox\"]") && b.getAttribute("role") !== "checkbox" && !b.hasAttribute("aria-label")); return { bar: !!card.querySelector("[data-form-actions]"), inner: Math.round(inner), widths: buttons.map((b) => Math.round(b.getBoundingClientRect().width)), lines: buttons.map((b) => b.getClientRects().length + ":" + Math.round(b.getBoundingClientRect().height)), heading: card.querySelector("h1") ? getComputedStyle(card.querySelector("h1")).textAlign : null, scroll: document.documentElement.scrollWidth <= document.documentElement.clientWidth }; }');
}

it('lays the sign-in and invitation cards out as full-width guest forms', function (): void {
    $environment = getenv('APP_ENV');
    try {
        putenv('APP_ENV=local');
        app()['env'] = 'local';
        config(['concierge.local_login' => FreshClone::localLogin('local')]);
        app()->getProvider(LocalAuthServiceProvider::class)->registerRoutes();
        app('router')->getRoutes()->refreshNameLookups();
        expect(app(Kernel::class)->call('migrate', ['--force' => true]))->toBe(0);
        expect(app(Kernel::class)->call('starter:first-owner', ['--no-interaction' => true]))->toBe(0);
        $workspace = Workspace::query()->sole();
        $owner = ConciergeModels::user()::query()->where('email', config('concierge.local_login.account.email'))->firstOrFail();
        $invite = InviteMember::run($owner, $workspace, 'newcomer@example.test', new GrantSet(['viewer']));

        foreach ([[1440, 800], [390, 844]] as [$width, $height]) {
            $page = visit('/login');
            $page->resize($width, $height)->assertSee('Sign in to your account')->assertPresent('[data-local-login]');
            $layout = cardLayout($page);
            // No settings bar; every submit fills the card's content width on one line; the heading is on the left edge like the fields; nothing scrolls sideways.
            expect($layout['bar'])->toBeFalse();
            expect($layout['widths'])->toHaveCount(2);
            foreach ($layout['widths'] as $buttonWidth) {
                expect(abs($buttonWidth - $layout['inner']))->toBeLessThanOrEqual(1);
            }
            expect($layout['lines'])->each->toStartWith('1:');
            expect($layout['heading'])->toBeIn(['start', 'left']);
            expect($layout['scroll'])->toBeTrue();
            // Remember me sits on its own row above the Sign in button.
            expect($page->script('() => { const box = document.querySelector("#remember").getBoundingClientRect(); const button = document.querySelector("[data-test=login-button]").getBoundingClientRect(); return box.bottom <= button.top && Math.abs(box.left - document.querySelector("#email").getBoundingClientRect().left) < 2; }'))->toBeTrue();
            settled($page)->screenshot(filename: 'sign-in-'.$width);

            $page = visit('/invitations/'.$invite->token);
            $page->resize($width, $height)->assertSee('Create your account');
            $layout = cardLayout($page);
            expect($layout['bar'])->toBeFalse();
            expect($layout['widths'])->toHaveCount(1);
            expect(abs($layout['widths'][0] - $layout['inner']))->toBeLessThanOrEqual(1);
            expect($layout['lines'][0])->toStartWith('1:');
            expect($layout['heading'])->toBeIn(['start', 'left']);
            expect($layout['scroll'])->toBeTrue();
            settled($page)->screenshot(filename: 'invitation-'.$width);
        }
    } finally {
        putenv('APP_ENV='.$environment);
        app()['env'] = $environment;
    }
});
