<?php

namespace App\Starter\Workspaces\Http;

use App\Models\User;
use App\Starter\Core\Principal;
use App\Starter\Workspaces\Models\Workspace;
use App\Starter\Workspaces\Operations\AcceptWorkspaceInvitation;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Tey\Concierge\Concierge;

final class WorkspaceController
{
    private function principal(Request $request): Principal
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return new Principal((int) $user->getKey());
    }

    public function index(Request $request): mixed
    {
        $workspaces = Workspace::all()->filter(fn (Workspace $workspace) => app(Concierge::class)->membership($request->user(), $workspace)?->isActive())->map(fn ($workspace) => ['id' => $workspace->id, 'name' => $workspace->name])->values()->all();

        return ($request->expectsJson() && ! $request->header('X-Inertia')) ? response()->json(['workspaces' => $workspaces]) : Inertia::render('Workspaces', compact('workspaces'));
    }

    public function accept(Request $request): mixed
    {
        $data = $request->validate(['token' => ['required', 'string', 'max:512']]);
        $result = app(AcceptWorkspaceInvitation::class)->handle($this->principal($request), $data['token']);

        return ($request->expectsJson() && ! $request->header('X-Inertia')) ? response()->json($result) : redirect('/workspaces/'.$result['workspace_id'].'/settings');
    }
}
