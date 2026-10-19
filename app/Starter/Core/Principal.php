<?php

namespace App\Starter\Core;

// Supplied by the authenticated transport, never deserialized from tool arguments.
final readonly class Principal
{
    public function __construct(public int $actorId, public ?int $connectionId = null) {}
}
