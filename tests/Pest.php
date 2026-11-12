<?php

use Tests\RecordsCase;
use Tests\StorageCase;
use Tests\TestCase;

// A local run needs --tia (impacted tests only) or --no-tia (browser specs); a non-empty CI, or a set PARATEST or PEST_TIA, bypasses (an empty CI= does not). tests/TiaGuard.php holds the TIA base branch.
require_once __DIR__.'/TiaGuard.php';

$argv = $_SERVER['argv'] ?? [];
if ((getenv('CI') === false || getenv('CI') === '') && getenv('PARATEST') === false && getenv('PEST_TIA') === false && ! in_array('--tia', $argv, true) && ! in_array('--no-tia', $argv, true)) {
    fwrite(STDERR, "Run tests with TIA: composer test (or vendor/bin/pest --tia …). Use --no-tia only for browser specs.\n");
    exit(1);
}

// Browser tests keep their own base case and configuration (composer test:browser); every other test extends the application base case.
require_once __DIR__.'/Browser/BrowserCase.php';
require_once __DIR__.'/RecordsCase.php';
require_once __DIR__.'/StorageCase.php';

pest()->extend(BrowserCase::class)->in('Browser');

// Two groups boot the application differently: with the test-only Records fixture, and with an object-storage bucket in the environment.
$records = ['Breadcrumbs', 'InertiaTransport', 'PageTitle', 'UrlConvention'];
$storage = ['Storage'];
// These need no application: they stay plain PHPUnit cases.
$plain = ['Artisan', 'FeatureGraph', 'MailSender', 'NightwatchConfig', 'TiaGuard'];
$tests = static fn (array $names): array => array_map(static fn (string $name): string => __DIR__.'/'.$name.'Test.php', $names);

pest()->extend(RecordsCase::class)->in(...$tests($records));
pest()->extend(StorageCase::class)->in(...$tests($storage));
pest()->extend(TestCase::class)->in(...array_values(array_diff(glob(__DIR__.'/*Test.php'), $tests([...$records, ...$storage, ...$plain]))));

// Runtime coverage cannot prove client behaviour or discover every dynamic dependency, so files it cannot see re-run the suite.
// database/** covers the factories and seeders. The arch tests read source and workflow files rather than executing them, so coverage never links them;
// the application-free checks are re-run on any change under tests/ (the guard and helpers they load are not covered files).
$arch = ['FeatureGraph', 'HandsFreeDeploy', 'KitUi', 'Lifecycle', 'SettingsForms', 'ViewModelStandard'];
$tia = pest()->tia()->defaultBranch(TiaGuard::defaultBranch(getenv()))->directory('.phpunit.cache/tia')->watch([
    'config/**' => 'tests',
    'routes/**' => 'tests',
    'resources/js/**' => 'tests',
    'resources/css/**' => 'tests',
    'database/**' => 'tests',
]);
foreach ([...$tests($arch), ...$tests($plain)] as $index => $file) {
    $target = 'tests/'.basename($file);
    $tia->watch($index < count($arch) ? ['app/**' => $target, '.github/workflows/**' => $target] : ['tests/**' => $target]);
}

/** @return list<string> the project-relative files tey/kit's installed lineage records (.mod-maintenance/installed) list as generated: kit:add writes them byte for byte, so the kit's own checks hold them */
function kitOwnedFiles(): array
{
    static $owned = null;
    if ($owned === null) {
        $owned = [];
        foreach (glob(dirname(__DIR__).'/.mod-maintenance/installed/*.json') ?: [] as $record) {
            $owned = [...$owned, ...array_keys(json_decode((string) file_get_contents($record), true)['artifact']['files'] ?? [])];
        }
    }

    return $owned;
}

// Browser screenshots wait for the web fonts to arrive (or fail). Shared here so every browser file has it under --parallel.
if (! function_exists('settled')) {
    function settled(mixed $page): mixed
    {
        $page->script('() => document.fonts.ready.then(() => true)');

        return $page;
    }
}

/** Whether this app adopted the kit's opt-in notes feature (`php artisan kit:add notes`). New starters do not; tests pin both. */
function notesAdopted(): bool
{
    return in_array('notes', config('kit.features'), true);
}

// Accessibility: every browser test that visits a page calls assertAccessible($page, 'what it shows'). axe checks the page against
// WCAG 2.2 AA (the wcag2a, wcag2aa, wcag21a, wcag21aa and wcag22aa tags) at every impact level, once animations have settled.
// A violation the app cannot fix yet goes in AXE_EXCEPTIONS with a reason and an expiry date; an exception without both, or past
// its date, fails the test. AXE_SURVEY=1 prints each page's violations to STDERR instead of failing, to size the work.
// The same scan also runs axe's best-practice rules (heading-order, landmark-unique, region, ...) as warnings: each finding is
// printed to STDERR once per run as "AXE WARNING rule (impact) selector @ url" and never fails the test. A rule that carries a
// WCAG tag too still fails. AXE_EXCEPTIONS silences a warning the same way; AXE_BEST_PRACTICE=0 skips the best-practice rules.
// A parallel run swallows the workers' STDERR, so run an accessibility test without --parallel to see the warnings (docs/testing.md).
const AXE_TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'];

const AXE_BEST_PRACTICE_TAGS = ['best-practice'];

/** @var array<string, array{reason: string, until: string, pages?: list<string>}> axe rule id => why it is allowed, until when (Y-m-d), and on which pages (all if left out). */
const AXE_EXCEPTIONS = [];

function axeBestPracticeEnabled(): bool
{
    return getenv('AXE_BEST_PRACTICE') !== '0';
}

/**
 * The page's URL and its violations; a violation that carries none of AXE_TAGS is a best-practice warning (`wcag` false).
 *
 * @return array{url: string, violations: list<array{id: string, impact: string, targets: list<string>, wcag: bool}>}
 */
function axeScan(object $page): array
{
    // Scan the mounted page: a full load fires `load` before Inertia has resolved the page component and rendered it, so the root
    // (`@inertia`'s #app) is still empty and axe reports a page without a <main> or an <h1>. Wait (up to 5 s) for it to render.
    // Then scan settled pixels: wait (up to another 5 s, on its own deadline) for finite animations and transitions to finish, so contrast is never measured mid-fade.
    $page->script('async () => { const deadline = Date.now() + 5000; const root = document.getElementById("app"); while (root && root.childElementCount === 0 && Date.now() < deadline) { await new Promise((r) => setTimeout(r, 50)); } const settleBy = Date.now() + 5000; const running = () => document.getAnimations().filter((a) => a.playState === "running" && a.effect?.getComputedTiming().iterations !== Infinity); while (running().length > 0 && Date.now() < settleBy) { await Promise.race([Promise.all(running().map((a) => a.finished.catch(() => null))), new Promise((r) => setTimeout(r, 250))]); } await new Promise((r) => requestAnimationFrame(() => requestAnimationFrame(r))); }');
    $tags = axeBestPracticeEnabled() ? [...AXE_TAGS, ...AXE_BEST_PRACTICE_TAGS] : AXE_TAGS;
    $scan = $page->script('async () => ({ url: location.href, violations: (await window.axe.run(document, { runOnly: { type: "tag", values: '.json_encode($tags).' } })).violations.map((v) => ({ id: v.id, impact: v.impact ?? "unknown", targets: v.nodes.map((n) => n.target.join(" ")), wcag: v.tags.some((t) => '.json_encode(AXE_TAGS).'.includes(t)) })) })');

    return [
        'url' => is_array($scan) && is_string($scan['url'] ?? null) ? $scan['url'] : '',
        'violations' => is_array($scan) && is_array($scan['violations'] ?? null) ? array_values($scan['violations']) : [],
    ];
}

/** @return list<array{id: string, impact: string, targets: list<string>}> the WCAG violations only. */
function axeViolations(object $page): array
{
    return array_values(array_filter(axeScan($page)['violations'], fn (array $v): bool => $v['wcag'] ?? true));
}

/** @param array<string, mixed>|null $exception */
function axeExceptionApplies(?array $exception, string $what): bool
{
    return $exception !== null && (! isset($exception['pages']) || in_array($what, $exception['pages'], true));
}

/**
 * The failures to report: each violation no current exception covers, and each exception that is malformed or expired.
 *
 * @param  list<array{id: string, impact: string, targets: list<string>}>  $violations
 * @param  array<string, array<string, mixed>>  $exceptions
 * @return list<string>
 */
function axeFailures(array $violations, string $what, array $exceptions, DateTimeImmutable $today): array
{
    $failures = [];
    foreach ($exceptions as $id => $exception) {
        $until = is_string($exception['until'] ?? null) ? DateTimeImmutable::createFromFormat('!Y-m-d', $exception['until']) : false;
        // A date that does not round-trip (2026-02-31) is not a date.
        $until = $until !== false && $until->format('Y-m-d') === $exception['until'] ? $until : false;
        if (trim((string) ($exception['reason'] ?? '')) === '' || $until === false) {
            $failures[] = "AXE_EXCEPTIONS['{$id}'] needs a reason and an until date (Y-m-d).";
        } elseif ($until < $today) {
            $failures[] = "AXE_EXCEPTIONS['{$id}'] expired on {$exception['until']}: fix the violation or renew the exception with a reason.";
        }
    }
    foreach ($violations as $violation) {
        if (! axeExceptionApplies($exceptions[$violation['id']] ?? null, $what)) {
            $shown = array_slice($violation['targets'], 0, 4);
            $more = count($violation['targets']) > 4 ? ' … +'.(count($violation['targets']) - 4) : '';
            $failures[] = "{$what}: {$violation['id']} ({$violation['impact']}): ".implode(' | ', $shown).$more;
        }
    }

    return $failures;
}

/**
 * The best-practice warnings to print, one line per rule, selector and URL, each at most once per run: `$seen` carries what was printed.
 *
 * @param  list<array{id: string, impact: string, targets: list<string>}>  $warnings
 * @param  array<string, array<string, mixed>>  $exceptions
 * @param  array<string, true>  $seen
 * @return list<string>
 */
function axeWarnings(array $warnings, string $what, string $url, array $exceptions, array &$seen): array
{
    $lines = [];
    foreach ($warnings as $warning) {
        if (axeExceptionApplies($exceptions[$warning['id']] ?? null, $what)) {
            continue;
        }
        foreach ($warning['targets'] as $target) {
            $key = $warning['id']."\0".$target."\0".$url;
            if (! isset($seen[$key])) {
                $seen[$key] = true;
                $lines[] = "AXE WARNING {$warning['id']} ({$warning['impact']}) {$target} @ {$url}";
            }
        }
    }

    return $lines;
}

/** Assert a page meets WCAG 2.2 AA at every impact level, apart from current AXE_EXCEPTIONS, and print best-practice warnings; AXE_SURVEY=1 prints instead of failing. */
function assertAccessible(object $page, string $what, ?Closure $warn = null): void
{
    $warn ??= fn (string $line) => fwrite(STDERR, $line.PHP_EOL);
    static $seen = [];
    $scan = axeScan($page);
    $violations = array_values(array_filter($scan['violations'], fn (array $v): bool => $v['wcag'] ?? true));
    $warnings = array_values(array_filter($scan['violations'], fn (array $v): bool => ! ($v['wcag'] ?? true)));
    foreach (axeWarnings($warnings, $what, $scan['url'], AXE_EXCEPTIONS, $seen) as $line) {
        $warn($line);
    }
    if (getenv('AXE_SURVEY') === '1') {
        fwrite(STDERR, 'AXE '.$what.' '.json_encode($violations, JSON_UNESCAPED_SLASHES).PHP_EOL);
        // Surveying prints the violations, but a malformed or expired exception still fails.
        expect(axeFailures([], $what, AXE_EXCEPTIONS, new DateTimeImmutable('today')))->toBe([], 'AXE_EXCEPTIONS');

        return;
    }
    expect(axeFailures($violations, $what, AXE_EXCEPTIONS, new DateTimeImmutable('today')))->toBe([], "{$what} is not accessible");
}
