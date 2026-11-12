<?php

use PHPUnit\Framework\ExpectationFailedException;

// assertAccessible's verdict, without a browser: the browser half runs with the Browser group.

$violation = fn (string $id, int $nodes = 1): array => ['id' => $id, 'impact' => 'moderate', 'targets' => array_map(fn (int $i): string => "#n{$i}", range(1, $nodes))];
$today = new DateTimeImmutable('2026-11-07');

test('a violation fails at any impact level, naming the page, the rule and at most four nodes', function () use ($violation, $today) {
    expect(axeFailures([$violation('region', 6)], 'the dashboard', [], $today))->toBe(['the dashboard: region (moderate): #n1 | #n2 | #n3 | #n4 … +2'])
        ->and(axeFailures([], 'the dashboard', [], $today))->toBe([]);
});

test('a current exception with a reason covers its rule, on the pages it names or on all', function () use ($violation, $today) {
    $exceptions = ['region' => ['reason' => 'Landmarks land with the new shell.', 'until' => '2026-12-01'], 'list' => ['reason' => 'Vendor widget.', 'until' => '2026-12-01', 'pages' => ['settings']]];

    expect(axeFailures([$violation('region'), $violation('list')], 'settings', $exceptions, $today))->toBe([])
        ->and(axeFailures([$violation('list')], 'the dashboard', $exceptions, $today))->toBe(['the dashboard: list (moderate): #n1']);
});

test('an exception without a reason or a date, or past its date, fails even when nothing violates it', function () use ($today) {
    expect(axeFailures([], 'home', ['a' => ['until' => '2026-12-01'], 'b' => ['reason' => 'x', 'until' => 'soon'], 'e' => ['reason' => 'x', 'until' => '2026-02-31'], 'c' => ['reason' => 'x', 'until' => '2026-11-06'], 'd' => ['reason' => 'x', 'until' => '2026-11-07']], $today))->toBe([
        "AXE_EXCEPTIONS['a'] needs a reason and an until date (Y-m-d).",
        "AXE_EXCEPTIONS['b'] needs a reason and an until date (Y-m-d).",
        "AXE_EXCEPTIONS['e'] needs a reason and an until date (Y-m-d).",
        "AXE_EXCEPTIONS['c'] expired on 2026-11-06: fix the violation or renew the exception with a reason.",
    ]);
});

// A stand-in page: the axe call returns these violations from this URL, so no browser or external service is involved.
$fixturePage = fn (array $violations, string $url = 'http://starter.test/dashboard') => new class($violations, $url)
{
    public array $scripts = [];

    public function __construct(private array $violations, private string $url) {}

    public function script(string $js): mixed
    {
        $this->scripts[] = $js;

        return str_contains($js, 'axe.run') ? ['url' => $this->url, 'violations' => $this->violations] : null;
    }
};

test('the scan asks axe for WCAG 2.2 AA and best practice after animations settle, and survey mode prints instead of failing', function () use ($fixturePage) {
    $page = $fixturePage([['id' => 'color-contrast', 'impact' => 'serious', 'targets' => ['main'], 'wcag' => true]]);
    putenv('AXE_SURVEY=1');
    try {
        assertAccessible($page, 'home', fn () => null);
    } finally {
        putenv('AXE_SURVEY');
    }
    expect($page->scripts)->toHaveCount(2)->and($page->scripts[0])->toContain('getAnimations')
        ->and($page->scripts[1])->toContain('"wcag22aa"')->toContain('"wcag2a"')->toContain('"best-practice"')->toContain('location.href')->not->toContain('impact ===');
    expect(fn () => assertAccessible($page, 'home', fn () => null))->toThrow(ExpectationFailedException::class);
});

test('a page with only best-practice findings passes and reports each rule, impact, selector and URL', function () use ($fixturePage) {
    $page = $fixturePage([
        ['id' => 'heading-order', 'impact' => 'moderate', 'targets' => ['h3.title'], 'wcag' => false],
        ['id' => 'region', 'impact' => 'moderate', 'targets' => ['#banner', '#footer'], 'wcag' => false],
    ], 'http://starter.test/fixture-best-practice');
    $printed = [];

    assertAccessible($page, 'the best-practice fixture', function (string $line) use (&$printed) {
        $printed[] = $line;
    });

    expect($printed)->toBe([
        'AXE WARNING heading-order (moderate) h3.title @ http://starter.test/fixture-best-practice',
        'AXE WARNING region (moderate) #banner @ http://starter.test/fixture-best-practice',
        'AXE WARNING region (moderate) #footer @ http://starter.test/fixture-best-practice',
    ]);
});

test('a WCAG violation still fails when best-practice warnings come with it', function () use ($fixturePage) {
    $page = $fixturePage([
        ['id' => 'landmark-unique', 'impact' => 'moderate', 'targets' => ['nav'], 'wcag' => false],
        ['id' => 'image-alt', 'impact' => 'critical', 'targets' => ['img.logo'], 'wcag' => true],
    ]);

    expect(fn () => assertAccessible($page, 'the WCAG fixture', fn () => null))->toThrow(ExpectationFailedException::class, 'the WCAG fixture is not accessible');
});

test('warnings print once per rule, selector and URL, and AXE_EXCEPTIONS silences them', function () {
    $seen = [];
    $warning = ['id' => 'region', 'impact' => 'moderate', 'targets' => ['#a', '#a']];

    expect(axeWarnings([$warning], 'home', 'http://x.test/', [], $seen))->toBe(['AXE WARNING region (moderate) #a @ http://x.test/'])
        ->and(axeWarnings([$warning], 'home', 'http://x.test/', [], $seen))->toBe([])
        ->and(axeWarnings([$warning], 'home', 'http://x.test/other', [], $seen))->toBe(['AXE WARNING region (moderate) #a @ http://x.test/other']);

    $fresh = [];
    expect(axeWarnings([$warning], 'home', 'http://x.test/', ['region' => ['reason' => 'New shell.', 'until' => '2026-12-01']], $fresh))->toBe([])
        ->and(axeWarnings([$warning], 'home', 'http://x.test/', ['region' => ['reason' => 'New shell.', 'until' => '2026-12-01', 'pages' => ['settings']]], $fresh))->toHaveCount(1);
});

test('AXE_BEST_PRACTICE=0 runs only the WCAG rules', function () use ($fixturePage) {
    $page = $fixturePage([]);
    putenv('AXE_BEST_PRACTICE=0');
    try {
        assertAccessible($page, 'home', fn () => null);
    } finally {
        putenv('AXE_BEST_PRACTICE');
    }
    expect($page->scripts[1])->toContain('"wcag22aa"')->not->toContain('"best-practice"');
});
