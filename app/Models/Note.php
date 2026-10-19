<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Note extends Model
{
    public $timestamps = false;

    protected $fillable = ['body'];

    protected static function booted(): void
    {
        static::addGlobalScope('workspace', fn (Builder $q) => $q->where('workspace_id', Workspace::required()->getKey()));
        static::saving(function (self $note): void {
            $id = Workspace::required()->getKey();
            if ($note->exists) {
                $note->assertPersistedOwnership();
            } elseif ($note->workspace_id !== null && (int) $note->workspace_id !== $id) {
                throw new \RuntimeException('workspace_mismatch');
            }
            $note->workspace_id = $id;
        });
        static::deleting(fn (self $note) => $note->assertPersistedOwnership());
    }

    private function assertPersistedOwnership(): void
    {
        $workspaceId = Workspace::required()->getKey();
        if ((int) $this->getOriginal('workspace_id') !== $workspaceId
            || (int) $this->workspace_id !== $workspaceId
            || $this->getKey() !== $this->getOriginal($this->getKeyName())
            || ! static::query()->whereKey($this->getOriginal($this->getKeyName()))->exists()) {
            throw new \RuntimeException('workspace_mismatch');
        }
    }
}
