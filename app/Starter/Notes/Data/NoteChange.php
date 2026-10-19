<?php

namespace App\Starter\Notes\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class NoteChange extends Data
{
    public function __construct(public int $note_id, public int $workspace_id, public bool $deleted, public ?NoteRecord $record) {}
}
