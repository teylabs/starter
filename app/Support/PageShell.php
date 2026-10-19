<?php

namespace App\Support;

use App\Data\ShellData;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\Request;
use RuntimeException;
use Tey\Concierge\Concierge;

final class PageShell
{
    public function forRequest(Request $request): ShellData
    {
        $user = $request->user('web');
        if (! $user instanceof User) {
            return new ShellData(config('app.name'), null, null, [], [], null);
        }
        $identity = ['id' => (int) $user->id, 'name' => $user->name, 'email' => $user->email];
        $choices = $navigation = [];
        $current = null;
        $concierge = app(Concierge::class);
        if ($concierge->account($user)->isActive() && $user->conciergeHasVerifiedEmail()) {
            foreach (Workspace::query()->orderBy('id')->get() as $workspace) {
                if (! $concierge->membership($user, $workspace)?->isActive()) {
                    continue;
                }
                $links = app(TenantBoundary::class)->run((int) $workspace->id, function () use ($workspace, $user, $request): array {
                    $links = [];
                    foreach (['settings.read' => ['Settings', 'settings'], 'notes.read' => ['Notes', 'notes']] as $ability => [$label, $suffix]) {
                        try {
                            app(Access::class)->authorize(new Principal((int) $user->id), $ability);
                            $href = '/workspaces/'.$workspace->id.'/'.$suffix;
                            $links[] = ['label' => $label, 'href' => $href, 'current' => '/'.$request->path() === $href];
                        } catch (RuntimeException $exception) {
                            if (! in_array($exception->getMessage(), ['workspace_not_found', 'account_inactive', 'verification_required', 'admission_required', 'permission_denied', 'capability_unavailable', 'entitlement_required'], true)) {
                                throw $exception;
                            }
                        }
                    }

                    return $links;
                });
                $choices[] = ['id' => (int) $workspace->id, 'name' => $workspace->name, 'href' => $links[0]['href'] ?? null];
                if ((string) $request->route('workspace') === (string) $workspace->id) {
                    $current = ['id' => (int) $workspace->id, 'name' => $workspace->name];
                    $navigation = $links;
                }
            }
        }

        return new ShellData(config('app.name'), $identity, $current, $navigation, $choices, '/logout');
    }
}
