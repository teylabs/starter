<?php

namespace App\Support;

use Closure;
use RuntimeException;
use Tey\StarterBlueprint\Lifecycle;
use Tey\StarterBlueprint\LifecycleHost;

/** The starter's trusted binding for `starter:maintain`; every input names a real checkout file. */
final class StarterLifecycle implements LifecycleHost
{
    public const INPUTS = [
        'verification-policy' => 'config/lifecycle.php',
        'verification-code' => 'app/Support/StarterLifecycle.php',
        'dependency-lock' => 'composer.lock',
        'host-config' => 'config/foundation.php',
        'host-binding' => 'app/Support/InstallProvider.php',
        'host-operations' => 'app/Support/lifecycle-operations.php',
        'executor-provenance' => 'config/lifecycle-executor.json',
    ];

    private readonly string $root;

    public function __construct(?string $root = null)
    {
        $this->root = $root ?? base_path();
    }

    public function lifecycle(): Lifecycle
    {
        return new Lifecycle;
    }

    public function appId(): string
    {
        $id = config('lifecycle.app_id');
        if (! is_string($id) || $id === '') {
            throw new RuntimeException('lifecycle_app_id_required');
        }

        return $id;
    }

    public function bindings(): array
    {
        $bindings = config('lifecycle.bindings');
        if (! is_array($bindings) || $bindings === []) {
            throw new RuntimeException('lifecycle_bindings_required');
        }

        return $bindings;
    }

    public function inputPaths(): array
    {
        return self::INPUTS;
    }

    public function operationCheck(): Closure
    {
        // Loaded from the checkout's own host-operations input so the blueprint can verify where it is declared.
        $check = require $this->root.'/'.self::INPUTS['host-operations'];
        if (! $check instanceof Closure) {
            throw new RuntimeException('lifecycle_operation_check_required');
        }

        return $check;
    }
}
