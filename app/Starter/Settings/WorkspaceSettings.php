<?php

namespace App\Starter\Settings;

use App\Starter\Workspaces\Models\Workspace;
use Spatie\LaravelSettings\Settings;
use Spatie\LaravelSettings\SettingsMapper;

class WorkspaceSettings extends Settings
{
    private ?int $boundWorkspaceId = null;

    public string $display_name = 'New workspace';

    public string $timezone = 'UTC';

    public static function group(): string
    {
        return 'workspace.'.Workspace::required()->getKey();
    }

    public static function cacheKey(): string
    {
        return static::group();
    }

    public function save(): self
    {
        if ($this->boundWorkspaceId !== Workspace::required()->getKey()) {
            throw new \RuntimeException('workspace_mismatch');
        }
        // The mapper is shared by the package; initialise it for this current
        // workspace even when a different settings instance ran in between.
        app(SettingsMapper::class)->initialize(static::class);

        return parent::save();
    }

    // Never register as a scoped/singleton binding: a worker can visit many tenants.
    public static function current(): self
    {
        $settings = new self;
        $settings->toArray();
        $settings->boundWorkspaceId = Workspace::required()->getKey();

        return $settings;
    }
}
