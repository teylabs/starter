<?php

// standards-pattern: ui-page.declared-guards

use App\Models\User;
use Glhd\Gretel\Registry;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Once;
use Illuminate\Support\Str;
use Tests\Fixtures\RecordsFixtureProvider;
use Tey\Concierge\Actions\AcceptInvitation;
use Tey\Concierge\Actions\InviteMember;
use Tey\Concierge\Support\GrantSet;
use Tey\Kit\Features\SessionUi\Http\Middleware\HandleInertiaRequests;
use Tey\Kit\Features\SessionUi\Welcome\Onboarding;
use Tey\Kit\Features\Workspaces\Models\Workspace;

/**
 * Queries a page may repeat because tey/kit repeats them, not the starter. Each entry is the normalized statement (bindings left out, whitespace
 * collapsed), the routes it is seen on ('*' for every page), the most times it may run there, and the kit issue that removes it. A repeat on another
 * route, or more often than listed, fails; so does an entry nothing repeats any more, so it goes when the kit fix is installed.
 *
 * @var list<array{query: string, routes: list<string>, max: int, issue: string}>
 */
const KIT_REPEATS = [
    // EnsureAccountActive reads the account outside RequestMemo, then Access reads it through it.
    ['query' => 'select * from "concierge_accounts" where "concierge_accounts"."user_id" = ? limit 1', 'routes' => ['*'], 'max' => 2, 'issue' => 'kit#1024'],
    // The People page reads the viewer's membership and the owner per check.
    ['query' => 'select * from "concierge_memberships" where "workspace_id" = ? and "user_id" = ? limit 1', 'routes' => ['workspaces.people.index'], 'max' => 3, 'issue' => 'kit#1024'],
    ['query' => 'select "membership_id" from "concierge_ownerships" where "concierge_ownerships"."workspace_id" = ? limit 1', 'routes' => ['workspaces.people.index'], 'max' => 2, 'issue' => 'kit#1024'],
    // The viewer's timezone and clock are read from user_state per call.
    ['query' => 'select "value" from "user_state" where ("user_id" = ? and "workspace_id" = ? and "key" = ?) limit 1', 'routes' => ['workspaces.dashboard.index', 'me.profile', 'focus.welcome.show'], 'max' => 3, 'issue' => 'kit#986'],
    // Shared per-request lookups the kit memoizes with once() from 0.25.1: the workspace row, its settings, onboarding, the avatar, ownership and roles.
    ['query' => 'select * from "workspaces" where "workspaces"."id" = ? limit 1', 'routes' => ['workspaces.settings.general', 'workspaces.settings.email', 'workspaces.people.index'], 'max' => 2, 'issue' => 'kit#978'],
    ['query' => 'select "name", "payload" from "settings" where "group" = ?', 'routes' => ['workspaces.dashboard.index', 'workspaces.settings.general'], 'max' => 3, 'issue' => 'kit#978'],
    ['query' => 'select exists(select * from "user_state" where "workspace_id" = ? and "key" = ?) as "exists"', 'routes' => ['workspaces.dashboard.index'], 'max' => 3, 'issue' => 'kit#978'],
    ['query' => 'select * from "media" where "model_type" = ? and "model_id" = ? and "collection_name" = ? order by "id" desc limit 1', 'routes' => ['workspaces.dashboard.index', 'workspaces.index', 'me.index', 'me.profile', 'me.password', 'me.security'], 'max' => 3, 'issue' => 'kit#978'],
    ['query' => 'select exists(select * from "concierge_ownerships" where "concierge_ownerships"."workspace_id" = ? and "membership_id" = ?) as "exists"', 'routes' => ['workspaces.dashboard.index', 'me.index', 'me.profile', 'me.password', 'me.security'], 'max' => 3, 'issue' => 'kit#978'],
    ['query' => 'select "roles".*, "model_has_roles"."model_id" as "pivot_model_id", "model_has_roles"."role_id" as "pivot_role_id", "model_has_roles"."model_type" as "pivot_model_type", "model_has_roles"."workspace_id" as "pivot_workspace_id" from "roles" inner join "model_has_roles" on "roles"."id" = "model_has_roles"."role_id" where "model_has_roles"."workspace_id" = ? and ("roles"."workspace_id" is null or "roles"."workspace_id" = ?) and "model_has_roles"."model_id" in (?) and "model_has_roles"."model_type" = ?', 'routes' => ['workspaces.dashboard.index'], 'max' => 2, 'issue' => 'kit#978'],
    // The breadcrumb trail is resolved once per caller (the layout, the page title and a shared prop), so a title that reads a row reads it three times.
    ['query' => 'select "title" from "fixture_records" where "workspace_id" = ? and "id" = ? limit 1', 'routes' => ['workspaces.records.show'], 'max' => 3, 'issue' => 'kit#1034'],
];

/**
 * Declared pages that do not answer 200 on the seed, with the status they give and why. Any other status fails, and so does a listed page that
 * answers differently.
 *
 * @var array<string, array{status: int, reason: string}>
 */
const SKIPPED_PAGES = [
    'home' => ['status' => 302, 'reason' => 'with more than one workspace, / opens the chooser; the dashboard is covered at /@{workspace}'],
    'focus.welcome' => ['status' => 302, 'reason' => 'redirects to the next onboarding step, covered at /welcome/{step}'],
    'focus.no-workspace' => ['status' => 302, 'reason' => 'the person has workspaces, so it redirects to the chooser'],
    'workspaces.connections.index' => ['status' => 404, 'reason' => 'the AI assistants page is off (kit.mcp.ui)'],
    'workspaces.settings.delete' => ['status' => 404, 'reason' => 'workspace deletion is not enabled (Deletions::WORKSPACE)'],
    'me.delete' => ['status' => 404, 'reason' => 'account deletion is not enabled (Deletions::ACCOUNT)'],
];

/** A statement as KIT_REPEATS lists it: one line, single spaces, no bindings (an integer list Laravel inlines into `in (...)` becomes `?`). */
function pageQueryNormalized(string $sql): string
{
    return trim((string) preg_replace(['/\s+/', '/\bin \(\d+(?:, ?\d+)*\)/'], [' ', 'in (?)'], $sql));
}

function pageQuerySignIn(int $id): void
{
    $password = Str::random(40);
    User::findOrFail($id)->update(['password' => Hash::make($password)]);
    test()->postJson('/login', ['email' => 'actor'.$id.'@example.test', 'password' => $password])->assertOk();
    test()->withCookie(config('session.cookie'), app('session')->getId());
    Auth::forgetGuards();
}

/**
 * The kit fixture (Alpha and Beta, actors 1-3) filled out the way a workspace in use looks: several records, more members with different roles,
 * pending invitations and connections, so a list page renders a list and a per-row lookup shows up as a repeat.
 *
 * @return array<string, string> the value each route parameter takes
 */
function pageQuerySeed(): array
{
    require dirname(__DIR__).'/database/fixture.php';
    RecordsFixtureProvider::install();
    foreach (range(1, 5) as $n) {
        DB::table('fixture_records')->insert([['workspace_id' => 1, 'title' => 'Alpha record '.$n], ['workspace_id' => 2, 'title' => 'Beta record '.$n]]);
    }
    $owner = User::findOrFail(1);
    $alpha = Workspace::findOrFail(1);
    foreach ([4 => 'admin', 5 => 'admin', 6 => 'viewer', 7 => 'viewer'] as $id => $role) {
        $member = User::create(['name' => 'Member '.$id, 'email' => 'actor'.$id.'@example.test', 'email_verified_at' => now()]);
        AcceptInvitation::run($member, InviteMember::run($owner, $alpha, $member->email, new GrantSet([$role]))->token);
    }
    $invitations = [];
    foreach (['pending1@example.test', 'pending2@example.test', 'pending3@example.test'] as $email) {
        $invitations[] = InviteMember::run($owner, $alpha, $email, new GrantSet(['viewer']));
    }
    // The owner is also a member of Beta, so their workspace list has more than one row.
    AcceptInvitation::run($owner, InviteMember::run(User::findOrFail(2), Workspace::findOrFail(2), $owner->email, new GrantSet(['viewer']))->token);
    DB::table('connections')->insert(['workspace_id' => 1, 'actor_id' => 1, 'scopes' => json_encode(['settings.read'])]);

    return array_filter([
        'workspace' => 'alpha',
        'record' => (string) DB::table('fixture_records')->where('workspace_id', 1)->orderBy('id')->value('id'),
        'membership' => (string) DB::table('concierge_memberships')->where('workspace_id', 1)->where('user_id', 4)->value('id'),
        'token' => $invitations[0]->token,
        'step' => Onboarding::next(1)['id'] ?? null,
    ], fn (?string $value): bool => $value !== null && $value !== '');
}

/**
 * Every page the kit and the app declare (a GET route with a breadcrumb: WorkspaceRoutes::page, or WorkspaceRoutes::personalPage under /me), visited
 * warm on the seed as its owner. A page repeats no query except the kit's known ones, and runs at most 45. A new page is covered as soon as it is
 * declared; one whose route parameters the seed cannot fill is reported as not covered. PAGE_REPORT=1 prints each page's count, repeats and callers.
 */
it('runs each query once on every declared page, within the query budget', function (): void {
    $values = pageQuerySeed();
    pageQuerySignIn(1);
    $headers = ['X-Inertia' => 'true', 'Accept' => 'text/html', 'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(Request::create('/')) ?? ''];
    // A dialog route opens as its ModalLink asks for it (the modal alone), not as a pasted URL that also renders the page under it.
    $headers['X-InertiaUI-Modal'] = 'guard';
    $crumbs = app(Registry::class);

    $pages = [];
    $notCovered = [];
    foreach (Route::getRoutes() as $route) {
        /** @var RoutingRoute $route */
        $name = (string) $route->getName();
        // The short form (`short.` names) is the same page without the workspace in its path.
        if (! in_array('GET', $route->methods(), true) || $name === '' || str_starts_with($name, 'short.') || $crumbs->get($route) === null) {
            continue;
        }
        $missing = array_values(array_diff($route->parameterNames(), array_keys($values)));
        if ($missing !== []) {
            $notCovered[$name] = 'the seed has no '.implode(', ', $missing);

            continue;
        }
        $pages[$name] = '/'.ltrim((string) preg_replace_callback('/\{(\w+)\??\}/', fn (array $m): string => $values[$m[1]], $route->uri()), '/');
    }

    $report = [];
    $failures = [];
    $used = [];
    $callers = [];
    $tracing = false;
    DB::listen(function ($query) use (&$callers, &$tracing): void {
        if (! $tracing) {
            return;
        }
        $frames = array_filter(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 80), fn (array $f): bool => isset($f['file']) && ! str_contains($f['file'], '/vendor/laravel/') && ! str_contains($f['file'], '/tests/'));
        $callers[$query->sql.'|'.json_encode($query->bindings)][] = implode(' < ', array_slice(array_map(fn (array $f): string => str_replace(base_path().'/', '', $f['file']).':'.$f['line'], array_values($frames)), 0, 4));
    });
    foreach ($pages as $name => $path) {
        // Warm once: the first view of a fresh seed fills caches a returning visitor already has. Each request starts with no once() memos, as a real one does.
        Once::flush();
        $this->get($path, $headers);
        Once::flush();
        DB::flushQueryLog();
        DB::enableQueryLog();
        $callers = [];
        $tracing = (bool) getenv('PAGE_REPORT');
        $response = $this->get($path, $headers);
        $tracing = false;
        $log = DB::getQueryLog();
        DB::disableQueryLog();
        $expected = SKIPPED_PAGES[$name]['status'] ?? 200;
        if ($response->getStatusCode() !== $expected) {
            $failures[] = $path.' answered '.$response->getStatusCode().', expected '.$expected;

            continue;
        }
        if ($expected !== 200) {
            $report[$name] = 'skipped: '.SKIPPED_PAGES[$name]['reason'];

            continue;
        }
        if (! str_contains((string) $response->headers->get('Content-Type'), 'json')) {
            $failures[] = $path.' is not an Inertia page';

            continue;
        }
        $repeats = [];
        foreach (array_count_values(array_map(fn (array $q): string => $q['query'].'|'.json_encode($q['bindings']), $log)) as $key => $times) {
            if ($times < 2) {
                continue;
            }
            $sql = pageQueryNormalized(explode('|', $key, 2)[0]);
            $known = array_filter(KIT_REPEATS, fn (array $entry, int $index): bool => $entry['query'] === $sql && (in_array('*', $entry['routes'], true) || in_array($name, $entry['routes'], true)) && $times <= $entry['max'], ARRAY_FILTER_USE_BOTH);
            if ($known === []) {
                $repeats[$key] = $times;
            }
            foreach (array_keys($known) as $index) {
                $used[$index] = true;
            }
        }
        $report[$name] = ['queries' => count($log), 'repeats' => $repeats, 'callers' => array_intersect_key($callers, $repeats)];
        if ($repeats !== []) {
            $failures[] = $path.' repeats '.json_encode($repeats);
        }
        // The cap is 45 for now; it goes down as pages get cheaper (the kit's known repeats are fixed, a page is trimmed), never up.
        if (count($log) > 45) {
            $failures[] = $path.' runs '.count($log).' queries (budget 45)';
        }
    }
    foreach (KIT_REPEATS as $index => $entry) {
        if (! isset($used[$index])) {
            $failures[] = 'KIT_REPEATS entry for '.$entry['issue'].' matched no repeat: remove it ('.$entry['query'].')';
        }
    }

    if (getenv('PAGE_REPORT')) {
        fwrite(STDERR, json_encode(['pages' => $report, 'not_covered' => $notCovered], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
    } elseif ($notCovered !== []) {
        fwrite(STDERR, 'Pages the seed does not cover: '.json_encode($notCovered, JSON_UNESCAPED_SLASHES)."\n");
    }

    $visited = array_keys(array_filter($report, is_array(...)));
    expect($visited)->toContain('workspaces.dashboard.index', 'workspaces.records.index', 'workspaces.records.show', 'workspaces.settings.index', 'workspaces.people.index', 'me.index')
        ->and($failures)->toBe([], 'each declared page runs each query once and at most 45 queries');
});
