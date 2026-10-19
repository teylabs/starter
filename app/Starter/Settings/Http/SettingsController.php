<?php

namespace App\Starter\Settings\Http;

use App\Models\User;
use App\Starter\Access\Access;
use App\Starter\Core\Principal;
use App\Starter\Settings\Adapters\SettingsUi;
use App\Starter\Workspaces\TenantBoundary;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

final class SettingsController
{
    private function principal(Request $request): Principal
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return new Principal((int) $user->getKey());
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
}
