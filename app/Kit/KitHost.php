<?php

namespace App\Kit;

use Closure;
use RuntimeException;
use Tey\Kit\Catalog;
use Tey\Kit\CatalogHost;
use Tey\Kit\Lifecycle;
use Tey\Kit\LifecycleHost;

/**
 * This app's trusted binding for kit:add and kit:update. Written by kit:install; the app owns it,
 * and no kit command changes it. Every input names a real file in this checkout.
 */
final class KitHost implements CatalogHost, LifecycleHost
{
    public const INPUTS = [
        'verification-policy' => 'config/kit-lifecycle.php',
        'verification-code' => 'app/Kit/KitHost.php',
        'dependency-lock' => 'composer.lock',
        'host-config' => 'config/app.php',
        'host-binding' => 'app/Providers/KitHostServiceProvider.php',
        'host-operations' => 'app/Kit/operations.php',
        'executor-provenance' => 'config/kit-executor.json',
    ];

    public function lifecycle(): Lifecycle
    {
        return new Lifecycle;
    }

    public function catalog(): Catalog
    {
        return Catalog::package();
    }

    public function appId(): string
    {
        $id = config('kit-lifecycle.app_id');
        if (! is_string($id) || $id === '') {
            throw new RuntimeException('config/kit-lifecycle.php must set app_id.');
        }

        return $id;
    }

    public function bindings(): array
    {
        $bindings = config('kit-lifecycle.bindings', []);
        if (! is_array($bindings)) {
            throw new RuntimeException('config/kit-lifecycle.php bindings must be an array.');
        }

        return $bindings;
    }

    public function inputPaths(): array
    {
        return self::INPUTS;
    }

    public function operationCheck(): Closure
    {
        // Loaded from the checkout's own host-operations input, so the kit can verify where it is declared.
        $check = require base_path(self::INPUTS['host-operations']);
        if (! $check instanceof Closure) {
            throw new RuntimeException('app/Kit/operations.php must return the operation check closure.');
        }

        return $check;
    }
}
