<?php

namespace App\Starter\SessionUi\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class ShellData extends Data
{
    /**
     * @param  array{id: int, name: string, email: string}|null  $user
     * @param  array{id: int, name: string}|null  $workspace
     * @param  list<array{label: string, href: string, current: bool}>  $navigation
     * @param  list<array{id: int, name: string, href: ?string}>  $workspaces
     */
    public function __construct(public string $brand, public ?array $user, public ?array $workspace, public array $navigation, public array $workspaces, public ?string $logout_url) {}
}
