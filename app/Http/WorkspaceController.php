<?php

namespace App\Http;

use App\Adapters\SettingsUi;
use App\Models\User;
use App\Models\Workspace;
use App\Operations\AcceptWorkspaceInvitation;
use App\Support\Access;
use App\Support\Principal;
use App\Support\TenantBoundary;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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

    public function show(Request $request, int $workspace): mixed
    {
        $settings = app(SettingsUi::class)->show($this->principal($request), $workspace);

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json($settings);
        }
        $canWrite = app(TenantBoundary::class)->run($workspace, function () use ($request): bool {
            try {
                app(Access::class)->authorize($this->principal($request), 'settings.write');

                return true;
            } catch (\RuntimeException $exception) {
                if ($exception->getMessage() !== 'permission_denied') {
                    throw $exception;
                }

                return false;
            }
        });

        return Inertia::render('Settings', ['settings' => $settings, 'can_write' => $canWrite, 'update_url' => '/workspaces/'.$workspace.'/settings', 'intent_key' => (string) Str::uuid()]);
    }

    public function update(Request $request, int $workspace): mixed
    {
        $settings = app(SettingsUi::class)->update($this->principal($request), $workspace, $request->except(['_token', '_method']));

        return ($request->expectsJson() && ! $request->header('X-Inertia')) ? response()->json($settings) : redirect('/workspaces/'.$workspace.'/settings')->with('status', 'Workspace settings saved.');
    }

    public function accept(Request $request): mixed
    {
        $data = $request->validate(['token' => ['required', 'string', 'max:512']]);
        $result = app(AcceptWorkspaceInvitation::class)->handle($this->principal($request), $data['token']);

        return ($request->expectsJson() && ! $request->header('X-Inertia')) ? response()->json($result) : redirect('/workspaces/'.$result['workspace_id'].'/settings');
    }
}
