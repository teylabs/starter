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

test('the scan asks axe for WCAG 2.2 AA after animations settle, and survey mode prints instead of failing', function () {
    $page = new class
    {
        public array $scripts = [];

        public function script(string $js): mixed
        {
            $this->scripts[] = $js;

            return str_contains($js, 'axe.run') ? [['id' => 'region', 'impact' => 'minor', 'targets' => ['main']]] : null;
        }
    };
    putenv('AXE_SURVEY=1');
    try {
        assertAccessible($page, 'home');
    } finally {
        putenv('AXE_SURVEY');
    }
    expect($page->scripts)->toHaveCount(2)->and($page->scripts[0])->toContain('getAnimations')
        ->and($page->scripts[1])->toContain('"wcag22aa"')->toContain('"wcag2a"')->not->toContain('impact ===');
    expect(fn () => assertAccessible($page, 'home'))->toThrow(ExpectationFailedException::class);
});
