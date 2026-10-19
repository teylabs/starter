<?php

namespace App\Support;

use App\Models\Workspace;
use Illuminate\Support\Facades\Cache;

final class WorkspaceCache
{
    public function put(string $key, mixed $value): void
    {
        Cache::put($this->key($key), $value, 60);
    }

    public function get(string $key): mixed
    {
        return Cache::get($this->key($key));
    }

    private function key(string $key): string
    {
        return 'workspace:'.Workspace::required()->getKey().':'.$key;
    }
}
