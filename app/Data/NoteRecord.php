<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class NoteRecord extends Data
{
    public function __construct(public int $id, public int $workspace_id, public string $body, public int $version) {}
}
