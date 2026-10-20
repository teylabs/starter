<?php

require __DIR__.'/application.php';
require dirname(__DIR__).'/database/fixture.php';
require_once __DIR__.'/Fixtures/RecordsFixture.php';
RecordsFixtureProvider::install();
use Illuminate\Support\Facades\DB;
use Tests\Fixtures\Record;
use Tests\Fixtures\RecordsFixtureProvider;
use Tey\Kit\Features\Settings\WorkspaceSettings;
use Tey\Kit\Features\Workspaces\TenantBoundary;

$boundary = app(TenantBoundary::class);
$held = $boundary->run(1, fn () => Record::query()->firstOrFail());
try {
    $boundary->run(2, function () use ($held): void {
        $held->workspace_id = 2;
        $held->delete();
    });
    echo "FAIL forged tenant deletion was allowed\n";
    exit(1);
} catch (RuntimeException $e) {
    if ($e->getMessage() !== 'workspace_mismatch') {
        throw $e;
    }
}
if (DB::table('fixture_records')->count() !== 2) {
    throw new RuntimeException('Foreign row changed');
}
echo "PASS forged tenant deletion refused without row loss\n";

foreach (['save', 'delete'] as $method) {
    foreach (['workspace', 'identity', 'persisted-owner'] as $forgery) {
        $held = $boundary->run(1, fn () => Record::query()->firstOrFail());
        try {
            $boundary->run($forgery === 'workspace' ? 2 : 1, function () use ($held, $method, $forgery): void {
                if ($forgery === 'workspace') {
                    $held->workspace_id = 2;
                }
                if ($forgery === 'identity') {
                    $held->id = 2;
                }
                if ($forgery === 'persisted-owner') {
                    DB::table('fixture_records')->where('id', 1)->update(['workspace_id' => 2]);
                }
                $held->body = 'must not persist';
                $held->$method();
            });
            throw new RuntimeException('Expected persisted ownership refusal');
        } catch (RuntimeException $e) {
            if ($e->getMessage() !== 'workspace_mismatch') {
                throw $e;
            }
        } finally {
            DB::table('fixture_records')->where('id', 1)->update(['workspace_id' => 1]);
        }
        if (DB::table('fixture_records')->orderBy('id')->pluck('title')->all() !== ['Alpha private', 'Beta private']) {
            throw new RuntimeException('Foreign rows changed');
        }
        echo "PASS $method refuses $forgery forgery without row change\n";
    }
}
$heldSettings = $boundary->run(1, fn () => WorkspaceSettings::current());
$beforeSettings = DB::table('settings')->get()->toJson();
try {
    $boundary->run(2, function () use ($heldSettings): void {
        $heldSettings->display_name = 'must not cross settings owner';
        $heldSettings->save();
    });
    throw new RuntimeException('Expected held settings ownership refusal');
} catch (RuntimeException $e) {
    if ($e->getMessage() !== 'workspace_mismatch') {
        throw $e;
    }
}
if ($beforeSettings !== DB::table('settings')->get()->toJson()) {
    throw new RuntimeException('Foreign settings changed');
}
echo "PASS held settings cannot save across workspace context\n";
$heldSettings = $boundary->run(1, fn () => WorkspaceSettings::current());
$boundary->run(2, fn () => WorkspaceSettings::current());
$boundary->run(1, function () use ($heldSettings): void {
    $heldSettings->display_name = 'Alpha after tenant switch';
    $heldSettings->save();
});
if ($boundary->run(2, fn () => WorkspaceSettings::current()->display_name) !== 'Beta') {
    throw new RuntimeException('Held mapper wrote foreign settings');
}
echo "PASS held settings save restores mapper owner after an intervening tenant\n";
