<?php

namespace App\Starter\Notes\Http;

use App\Starter\Access\Access;
use App\Starter\Core\Principal;
use App\Starter\Notes\Operations\WorkspaceNotes;
use App\Starter\Workspaces\TenantBoundary;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use RuntimeException;

final class NoteController
{
    public function index(Request $request, int $workspace): mixed
    {
        return app(TenantBoundary::class)->run($workspace, function () use ($request, $workspace) {
            $principal = new Principal((int) $request->user()->getKey());
            $notes = array_map(fn ($note) => $note->toArray(), app(WorkspaceNotes::class)->listing($principal));
            $canWrite = false;
            try {
                app(Access::class)->authorize($principal, 'notes.write');
                $canWrite = true;
            } catch (RuntimeException) {
            }

            if ($request->expectsJson() && ! $request->header('X-Inertia')) {
                return response()->json(['notes' => $notes, 'can_write' => $canWrite]);
            }
            $base = '/workspaces/'.$workspace.'/notes';
            $actions = [];
            foreach ($notes as $note) {
                $actions[$note['id']] = ['url' => $base.'/'.$note['id'], 'update_intent' => (string) Str::uuid(), 'delete_intent' => (string) Str::uuid()];
            }

            return Inertia::render('Notes', ['notes' => $notes, 'can_write' => $canWrite, 'create_url' => $base, 'create_intent' => (string) Str::uuid(), 'actions' => $actions]);
        });
    }

    public function show(Request $request, int $workspace, int $note): mixed
    {
        return app(TenantBoundary::class)->run($workspace, fn () => response()->json(app(WorkspaceNotes::class)->read(new Principal((int) $request->user()->getKey()), ['id' => $note])->toArray()));
    }

    public function store(Request $request, int $workspace): mixed
    {
        return $this->mutate($request, $workspace, 'create');
    }

    public function update(Request $request, int $workspace, int $note): mixed
    {
        return $this->mutate($request, $workspace, 'update', $note);
    }

    public function destroy(Request $request, int $workspace, int $note): mixed
    {
        return $this->mutate($request, $workspace, 'delete', $note);
    }

    private function mutate(Request $request, int $workspace, string $operation, ?int $note = null): mixed
    {
        $input = $request->except(['_token', '_method']);
        if (array_key_exists('id', $input)) {
            throw new RuntimeException('unknown_arguments');
        }
        if ($note !== null) {
            $input['id'] = $note;
        }
        $result = app(TenantBoundary::class)->run($workspace, fn () => app(WorkspaceNotes::class)->mutate(new Principal((int) $request->user()->getKey()), $operation, $input));

        return ($request->expectsJson() && ! $request->header('X-Inertia')) ? response()->json($result->toArray(), $operation === 'create' ? 201 : 200) : redirect('/workspaces/'.$workspace.'/notes');
    }
}
