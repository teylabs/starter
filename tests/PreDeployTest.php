<?php

use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\ServiceProvider;
use Tey\Kit\Features\Workspaces\Models\Workspace;

require dirname(__DIR__).'/vendor/autoload.php';

/** Traps that only show on PostgreSQL or at deploy: an ordering column the table lacks, a root layout the favicon preflight cannot read, caches that `optimize` skips. */
final class PreDeployTest extends TestCase
{
    public function createApplication()
    {
        return require __DIR__.'/application.php';
    }

    public function test_the_workspace_model_orders_by_a_column_the_table_has(): void
    {
        // The workspaces table has no timestamps: `oldest()` (Concierge's first local owner uses it) must not ask for created_at, which PostgreSQL refuses.
        $this->assertStringNotContainsString('created_at', Workspace::query()->oldest()->toSql());
        $this->assertStringNotContainsString('created_at', Workspace::query()->latest()->toSql());
        $this->assertStringContainsString('"id"', Workspace::query()->oldest()->toSql());
    }

    public function test_the_root_layout_has_a_non_data_icon_link_the_brand_preflight_can_read(): void
    {
        $source = (string) file_get_contents(dirname(__DIR__).'/resources/views/app.blade.php');
        $source = preg_replace('/{{--.*?--}}|<!--.*?-->/s', '', $source) ?? '';
        preg_match_all('/<link\b[^>]*>/i', $source, $links);
        $icons = array_filter($links[0], fn (string $link): bool => preg_match('/rel="[^"]*\bicon\b/i', $link) === 1 && preg_match('/href="(?!data:)[^"]+"/i', $link) === 1);
        $this->assertNotEmpty($icons, 'the root layout names a favicon link itself, not only through a component');
        foreach (['favicon.svg', 'favicon.ico', 'apple-touch-icon.png'] as $file) {
            $this->assertStringContainsString($file, implode("\n", $links[0]), $file.' is linked');
        }
        // The rendered head carries each icon once: the layout's links and the brand component do not double up.
        $html = (string) view('app', ['page' => ['props' => [], 'component' => 'Test', 'url' => '/', 'version' => '', 'clearHistory' => false, 'encryptHistory' => false]])->render();
        $this->assertSame(1, substr_count($html, 'href="'.asset('favicon.svg').'"'));
        $this->assertSame(1, substr_count($html, 'href="'.asset('favicon.ico').'"'));
        $this->assertSame(1, substr_count($html, 'href="'.asset('apple-touch-icon.png').'"'));
    }

    public function test_every_cache_the_kit_and_its_packages_build_is_registered_with_optimize(): void
    {
        $optimize = array_values(ServiceProvider::$optimizeCommands);
        $clear = array_values(ServiceProvider::$optimizeClearCommands);
        foreach (['breadcrumbs:cache', 'data:cache-structures', 'structure-scouts:cache', 'mod:cache'] as $command) {
            $this->assertContains($command, $optimize, $command.' runs with `php artisan optimize`');
        }
        foreach (['breadcrumbs:clear', 'structure-scouts:clear', 'mod:clear'] as $command) {
            $this->assertContains($command, $clear, $command.' runs with `php artisan optimize:clear`');
        }
    }

    public function test_the_optional_preflight_exists_and_there_is_no_deploy_command(): void
    {
        $commands = array_keys(Artisan::all());
        $this->assertContains('kit:preflight', $commands);
        $this->assertNotContains('kit:deploy', $commands, 'a deploy is migrate --force and optimize: there is no deploy command');
    }
}
