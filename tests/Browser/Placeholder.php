<?php

require_once __DIR__.'/../Concerns/FreshClone.php';

use Illuminate\Contracts\Console\Kernel;
use Tests\Concerns\FreshClone;
use Tey\Concierge\LocalAuth\LocalAuthServiceProvider;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\SessionUi\PlaceholderRoutes;

// A page that is not built yet, in a real browser: in the shell the real page will have, readable and unclipped at phone and desktop width in both themes, with the "Planned" tag on a
// planned settings section.
it('renders a placeholder page and a planned settings section', function (): void {
    $environment = getenv('APP_ENV');
    try {
        putenv('APP_ENV=local');
        app()['env'] = 'local';
        config(['concierge.local_login' => FreshClone::localLogin('local')]);
        app()->getProvider(LocalAuthServiceProvider::class)->registerRoutes();
        app('router')->getRoutes()->refreshNameLookups();
        expect(app(Kernel::class)->call('migrate', ['--force' => true]))->toBe(0);
        expect(app(Kernel::class)->call('starter:first-owner', ['--no-interaction' => true]))->toBe(0);
        app(Registry::class)->placeholder('orders.index', 'orders', 'dashboard.read', 'Orders', 'Every order with its status and next step.', ['Orders'], 'full', 'the Orders release', [['label' => 'Back to the dashboard', 'route' => 'workspaces.dashboard.index']])
            ->settingsSection('workspace', 'billing', 'Billing', 'Plans and invoices.', 'members.manage', 'Workspace', 30, 'credit-card', placeholder: true);
        app(PlaceholderRoutes::class)->register();
        app('router')->getRoutes()->refreshNameLookups();

        $page = visit('/login')->click('[data-local-login] button')->assertPathIs('/');
        $page->navigate('/orders')->assertSeeIn('[data-slot="placeholder"]', 'Not built yet')->assertSeeIn('[data-placeholder-milestone]', 'Planned for the Orders release')
            ->assertPresent('[data-slot="placeholder"] a')->assertMissing('main form, main [role="status"]')->assertNoJavaScriptErrors();
        foreach ([[1440, 900], [390, 844]] as [$width, $height]) {
            foreach (['light', 'dark'] as $theme) {
                $page->resize($width, $height)->script('() => document.documentElement.classList.toggle("dark", '.($theme === 'dark' ? 'true' : 'false').')');
                expect($page->script('() => document.documentElement.scrollWidth - document.documentElement.clientWidth'))->toBeLessThanOrEqual(0);
            }
        }
        $page->script('() => document.documentElement.classList.remove("dark")');
        // Pinned to its own card and its own sidebar entry, so an app with other planned sections still passes.
        $page->resize(1440, 900)->navigate('/settings')->assertSeeIn('[data-section="billing"] [data-planned-tag]', 'Planned')->assertMissing('[data-section="general"] [data-planned-tag]');
        $page->navigate('/settings/billing')->assertSeeIn('[data-slot="placeholder"]', 'Not built yet')->assertSeeIn('[data-settings-sidebar] [data-section-url$="/settings/billing"] [data-planned-tag]', 'Planned')->assertNoJavaScriptErrors();
    } finally {
        putenv('APP_ENV='.$environment);
        app()['env'] = $environment;
    }
});
