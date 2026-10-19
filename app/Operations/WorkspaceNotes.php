<?php

namespace App\Operations;

use App\Data\NoteChange;
use App\Data\NoteRecord;
use App\Models\Note;
use App\Models\Workspace;
use App\Support\Access;
use App\Support\Audit;
use App\Support\Principal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

final class WorkspaceNotes
{
    /** @return list<NoteRecord> */
    public function listing(Principal $principal, array $input = []): array
    {
        app(Access::class)->authorize($principal, 'notes.read');
        $this->only($input, []);

        return Note::query()->orderBy('id')->get()->map(fn (Note $note) => $this->record($note))->all();
    }

    public function read(Principal $principal, array $input): NoteRecord
    {
        app(Access::class)->authorize($principal, 'notes.read');
        $this->only($input, ['id']);
        $data = Validator::make($input, ['id' => ['required', 'integer', 'min:1']])->validate();

        return $this->record(Note::query()->whereKey($data['id'])->first() ?? throw new RuntimeException('note_not_found'));
    }

    public function mutate(Principal $principal, string $operation, array $input): NoteChange
    {
        if (! in_array($operation, ['create', 'update', 'delete'], true)) {
            throw new RuntimeException('unknown_arguments');
        }
        app(Access::class)->authorize($principal, 'notes.write');
        $rules = ['intent_key' => ['required', 'string', 'max:120']];
        if ($operation !== 'create') {
            $rules += ['id' => ['required', 'integer', 'min:1'], 'expected_version' => ['required', 'integer', 'min:1']];
        }
        if ($operation !== 'delete') {
            $rules['body'] = ['required', 'string', 'max:2000'];
        }
        $this->only($input, array_keys($rules));
        $data = Validator::make($input, $rules)->validate();
        $fingerprint = hash('sha256', json_encode([$operation, isset($data['id']) ? (int) $data['id'] : null, $data['body'] ?? null, isset($data['expected_version']) ? (int) $data['expected_version'] : null], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($principal, $operation, $data, $fingerprint): NoteChange {
            $workspace = app(Access::class)->authorize($principal, 'notes.write');
            Workspace::query()->whereKey($workspace->id)->lockForUpdate()->firstOrFail();
            $previous = DB::table('note_intents')->where('workspace_id', $workspace->id)->where('actor_id', $principal->actorId)->where('intent_key', $data['intent_key'])->first();
            if ($previous) {
                if ($previous->fingerprint !== $fingerprint) {
                    throw new RuntimeException('note_intent_conflict');
                }

                $saved = NoteChange::from(json_decode($previous->result, true, flags: JSON_THROW_ON_ERROR));
                $owner = DB::table('notes')->where('id', $saved->note_id)->value('workspace_id');
                if ($owner !== null && (int) $owner !== (int) $workspace->id) {
                    throw new RuntimeException('workspace_not_found');
                }

                return $saved;
            }
            $note = $operation === 'create' ? new Note : (Note::query()->whereKey($data['id'])->lockForUpdate()->first() ?? throw new RuntimeException('note_not_found'));
            if ($operation !== 'create' && (int) $note->version !== (int) $data['expected_version']) {
                throw new RuntimeException('stale_note');
            }
            if ($operation === 'delete') {
                $note->delete();
            } else {
                $note->body = $data['body'];
                $note->version = $operation === 'create' ? 1 : $note->version + 1;
                $note->save();
            }
            $result = new NoteChange((int) $note->id, (int) $workspace->id, $operation === 'delete', $operation === 'delete' ? null : $this->record($note));
            app(Audit::class)->write($principal->actorId, $workspace->id, 'workspace.note.'.$operation, ['note_id' => $note->id, 'version' => $note->version]);
            DB::table('note_intents')->insert(['workspace_id' => $workspace->id, 'actor_id' => $principal->actorId, 'intent_key' => $data['intent_key'], 'fingerprint' => $fingerprint, 'result' => $result->toJson()]);

            return $result;
        });
    }

    private function only(array $input, array $keys): void
    {
        if (array_diff(array_keys($input), $keys)) {
            throw new RuntimeException('unknown_arguments');
        }
    }

    private function record(Note $note): NoteRecord
    {
        return new NoteRecord((int) $note->id, (int) $note->workspace_id, $note->body, (int) $note->version);
    }
}
