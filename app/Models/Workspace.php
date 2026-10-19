<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Multitenancy\Concerns\UsesMultitenancyConfig;
use Spatie\Multitenancy\Contracts\IsTenant;
use Spatie\Multitenancy\Models\Concerns\ImplementsTenant;

class Workspace extends Model implements \Tey\Concierge\Contracts\Workspace, IsTenant
{
    use ImplementsTenant, UsesMultitenancyConfig;

    public $timestamps = false;

    protected $guarded = [];

    public function conciergeName(): string
    {
        return $this->name;
    }

    public function conciergeAcceptsMembers(): bool
    {
        return (bool) $this->admitted;
    }

    public static function required(): self
    {
        return static::current() ?? throw new \RuntimeException('workspace_required');
    }
}
