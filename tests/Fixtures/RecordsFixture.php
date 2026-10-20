<?php

namespace Tests\Fixtures;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;
use Laravel\Pennant\Feature;
use Tey\Kit\Features\Access\Access;
use Tey\Kit\Features\Core\Principal;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\Workspaces\Models\Workspace;
use Tey\Kit\Features\Workspaces\TenantBoundary;
use Tey\Kit\Features\Workspaces\WorkspaceUrl;

/**
 * A test-only sample resource: workspace-scoped records at /records, with a list page, a record page, and writes that redirect.
 * The kit ships no sample resource, so the tests that need a feature page with data (the URL forms, the breadcrumb trails, the
 * short-path parameter order, redirects after a write) adopt this one. It uses only what a real feature uses: the registry,
 * WorkspaceRoutes, the access abilities and a Pennant capability.
 */
final class RecordsFixtureProvider extends ServiceProvider
{
    public function boot(): void
    {
        app(Registry::class)
            ->navigation('records.read', 'Records', 'records', order: 10)
            ->abilities(['records.read' => 'workspace.records', 'records.write' => 'workspace.records'])
            ->capabilities(['workspace.records']);
        Feature::define('workspace.records', fn (): bool => true);
        // The app is already booted when a test adopts this fixture, so its routes are defined now, in the web group, as the kit loads its own.
        Route::middleware('web')->group(__DIR__.'/records-routes.php');
        Route::getRoutes()->refreshNameLookups();
        Route::getRoutes()->refreshActionLookups();
    }

    /** The table and two private rows (one per fixture workspace). */
    public static function install(): void
    {
        Schema::create('fixture_records', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('workspace_id')->constrained();
            $t->string('title');
        });
        DB::table('fixture_records')->insert([['workspace_id' => 1, 'title' => 'Alpha private'], ['workspace_id' => 2, 'title' => 'Beta private']]);
    }
}

final class RecordsController
{
    private function in(Request $request, int $workspace, string $ability, callable $operation): mixed
    {
        return app(TenantBoundary::class)->run($workspace, function () use ($request, $ability, $operation): mixed {
            app(Access::class)->authorize(new Principal((int) $request->user()->getKey()), $ability);

            return $operation();
        });
    }

    private function json(Request $request): bool
    {
        return $request->expectsJson() && ! $request->header('X-Inertia');
    }

    public function index(Request $request, int $workspace): mixed
    {
        return $this->in($request, $workspace, 'records.read', function () use ($request, $workspace) {
            $records = DB::table('fixture_records')->where('workspace_id', $workspace)->orderBy('id')->get(['id', 'workspace_id', 'title'])->map(fn ($record) => (array) $record)->all();
            if ($this->json($request)) {
                return response()->json(['records' => $records]);
            }

            return Inertia::render('Workspace/Record/Index/Page', ['title' => 'Records', 'records' => $records, 'create_url' => WorkspaceUrl::to($workspace, 'records', null, $request), 'actions' => collect($records)->mapWithKeys(fn (array $record) => [$record['id'] => ['url' => WorkspaceUrl::to($workspace, 'records/'.$record['id'], null, $request)]])->all()]);
        });
    }

    public function show(Request $request, int $workspace, int $record): mixed
    {
        return $this->in($request, $workspace, 'records.read', function () use ($request, $workspace, $record) {
            $found = (array) (DB::table('fixture_records')->where('workspace_id', $workspace)->where('id', $record)->first() ?? abort(404));

            return $this->json($request) ? response()->json($found) : Inertia::render('Workspace/Record/Show/Page', ['title' => 'Record', 'record' => $found, 'list_url' => WorkspaceUrl::to($workspace, 'records', null, $request)]);
        });
    }

    public function store(Request $request, int $workspace): mixed
    {
        return $this->in($request, $workspace, 'records.write', function () use ($request, $workspace) {
            $id = DB::table('fixture_records')->insertGetId(['workspace_id' => $workspace, 'title' => (string) $request->validate(['title' => ['required', 'string', 'max:120']])['title']]);

            return $this->json($request) ? response()->json(['id' => $id], 201) : redirect(WorkspaceUrl::to($workspace, 'records', null, $request));
        });
    }

    public function update(Request $request, int $workspace, int $record): mixed
    {
        return $this->in($request, $workspace, 'records.write', function () use ($request, $workspace, $record) {
            DB::table('fixture_records')->where('workspace_id', $workspace)->where('id', $record)->update(['title' => (string) $request->validate(['title' => ['required', 'string', 'max:120']])['title']]);

            return $this->json($request) ? response()->json(['id' => $record]) : redirect(WorkspaceUrl::to($workspace, 'records', null, $request));
        });
    }

    public function destroy(Request $request, int $workspace, int $record): mixed
    {
        return $this->in($request, $workspace, 'records.write', function () use ($request, $workspace, $record) {
            DB::table('fixture_records')->where('workspace_id', $workspace)->where('id', $record)->delete();

            return $this->json($request) ? response()->json(['deleted' => true]) : redirect(WorkspaceUrl::to($workspace, 'records', null, $request));
        });
    }
}

/** The fixture's tenant-scoped model: rows and route binding are scoped to the current workspace, and a held model cannot be saved into another one. */
final class Record extends Model
{
    protected $table = 'fixture_records';

    public $timestamps = false;

    protected $fillable = ['title'];

    protected static function booted(): void
    {
        self::addGlobalScope('workspace', fn (Builder $q) => $q->where('workspace_id', Workspace::required()->getKey()));
        self::saving(function (self $record): void {
            $id = Workspace::required()->getKey();
            if ($record->exists) {
                $record->assertPersistedOwnership();
            } elseif ($record->workspace_id !== null && (int) $record->workspace_id !== $id) {
                throw new \RuntimeException('workspace_mismatch');
            }
            $record->workspace_id = $id;
        });
        self::deleting(fn (self $record) => $record->assertPersistedOwnership());
    }

    private function assertPersistedOwnership(): void
    {
        $workspaceId = Workspace::required()->getKey();
        if ((int) $this->getOriginal('workspace_id') !== $workspaceId
            || (int) $this->workspace_id !== $workspaceId
            || $this->getKey() !== $this->getOriginal($this->getKeyName())
            || ! self::query()->whereKey($this->getOriginal($this->getKeyName()))->exists()) {
            throw new \RuntimeException('workspace_mismatch');
        }
    }
}
