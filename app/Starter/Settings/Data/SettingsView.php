<?php

namespace App\Starter\Settings\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class SettingsView extends Data
{
    /** @param list<array{label: string, href: string}> $navigation */
    public function __construct(public int $workspace_id, public string $display_name, public string $timezone, public array $navigation, public int $version) {}
}
