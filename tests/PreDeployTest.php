<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\ServiceProvider;
use Tey\Kit\Features\Workspaces\Models\Workspace;

test('the workspace model orders by a column the table has', function () {
    // The workspaces table has no timestamps: `oldest()` (Concierge's first local owner uses it) must not ask for created_at, which PostgreSQL refuses.
    $this->assertStringNotContainsString('created_at', Workspace::query()->oldest()->toSql());
    $this->assertStringNotContainsString('created_at', Workspace::query()->latest()->toSql());
    $this->assertStringContainsString('"id"', Workspace::query()->oldest()->toSql());
});
test('the root layout has a non data icon link the brand preflight can read', function () {
    $source = (string) file_get_contents(dirname(__DIR__).'/resources/views/app.blade.php');
    $source = preg_replace('/{{--.*?--}}|<!--.*?-->/s', '', $source) ?? '';
    preg_match_all('/<link\b[^>]*>/i', $source, $links);
    $icons = array_filter($links[0], fn (string $link): bool => preg_match('/rel="[^"]*\bicon\b/i', $link) === 1 && preg_match('/href="(?!data:)[^"]+"/i', $link) === 1);
    expect($icons)->not->toBeEmpty('the root layout names a favicon link itself, not only through a component');
    foreach (['favicon.svg', 'favicon.ico', 'apple-touch-icon.png'] as $file) {
        $this->assertStringContainsString($file, implode("\n", $links[0]), $file.' is linked');
    }

    // The rendered head carries each icon once: the layout's links and the brand component do not double up.
    $html = (string) view('app', ['page' => ['props' => [], 'component' => 'Test', 'url' => '/', 'version' => '', 'clearHistory' => false, 'encryptHistory' => false]])->render();
    expect(substr_count($html, 'href="'.asset('favicon.svg').'"'))->toBe(1);
    expect(substr_count($html, 'href="'.asset('favicon.ico').'"'))->toBe(1);
    expect(substr_count($html, 'href="'.asset('apple-touch-icon.png').'"'))->toBe(1);
});
test('every cache the kit and its packages build is registered with optimize', function () {
    $optimize = array_values(ServiceProvider::$optimizeCommands);
    $clear = array_values(ServiceProvider::$optimizeClearCommands);
    foreach (['breadcrumbs:cache', 'data:cache-structures', 'structure-scouts:cache', 'mod:cache'] as $command) {
        expect($optimize)->toContain($command);
    }
    foreach (['breadcrumbs:clear', 'structure-scouts:clear', 'mod:clear'] as $command) {
        expect($clear)->toContain($command);
    }
});
test('the optional preflight exists and there is no deploy command', function () {
    $commands = array_keys(Artisan::all());
    expect($commands)->toContain('kit:preflight');
    expect($commands)->not->toContain('kit:deploy');
});
