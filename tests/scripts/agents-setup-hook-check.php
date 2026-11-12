<?php

// .agents/setup's "House rule" block: the commit-msg hook it installs, and where it puts it. Runs the block in throwaway
// repositories (no network, nothing outside the system temp dir) and checks the core.hooksPath cases and the trailer filter.
$source = file_get_contents(dirname(__DIR__, 2).'/.agents/setup');
$start = strpos($source, "\n# House rule: no AI attribution");
if ($start === false) {
    fwrite(STDERR, "FAIL .agents/setup has no House rule block\n");
    exit(1);
}
$block = sys_get_temp_dir().'/agents-hook-block-'.bin2hex(random_bytes(4)).'.sh';
file_put_contents($block, "#!/usr/bin/env bash\nset -euo pipefail\n".substr($source, $start));
$failures = 0;
$check = function (bool $ok, string $message) use (&$failures): void {
    echo ($ok ? 'PASS ' : 'FAIL ').$message."\n";
    $failures += $ok ? 0 : 1;
};
$sh = function (string $dir, string $command): array {
    exec('cd '.escapeshellarg($dir).' && '.$command.' 2>&1', $output, $code);

    return [$code, implode("\n", $output)];
};
$repo = function () use ($sh): string {
    $dir = sys_get_temp_dir().'/agents-hook-repo-'.bin2hex(random_bytes(6));
    mkdir($dir);
    $sh($dir, 'git init -q');

    return realpath($dir);
};
$install = fn (string $dir): array => $sh($dir, 'bash '.escapeshellarg($block));
$hooksPath = fn (string $dir): string => trim($sh($dir, 'git config core.hooksPath')[1]);
$cleanup = [];

try {
    // Default: the common git dir's hooks.
    $dir = $cleanup[] = $repo();
    $install($dir);
    $check($hooksPath($dir) === realpath($dir.'/.git').'/hooks' && is_executable($dir.'/.git/hooks/commit-msg'), 'no hooksPath: the hook goes in .git/hooks');

    // Inside the checkout: kept and created.
    $dir = $cleanup[] = $repo();
    $sh($dir, 'git config --local core.hooksPath .githooks');
    $install($dir);
    $check($hooksPath($dir) === $dir.'/.githooks' && is_file($dir.'/.githooks/commit-msg'), 'a missing hooksPath inside the checkout is created and used');

    // Outside the checkout, in every spelling: fall back, create nothing.
    $outside = sys_get_temp_dir().'/agents-hook-outside-'.bin2hex(random_bytes(6));
    foreach (['absolute' => $outside.'/a/b', 'relative ../' => '../'.basename($outside).'/hooks', 'missing/../..' => 'missing/../../'.basename($outside)] as $label => $path) {
        $dir = $cleanup[] = $repo();
        $sh($dir, 'git config --local core.hooksPath '.escapeshellarg($path));
        $install($dir);
        $check($hooksPath($dir) === realpath($dir.'/.git').'/hooks' && ! file_exists($outside), "an outside hooksPath ({$label}) falls back to .git/hooks and creates nothing");
    }

    // A regular file in the way fails closed.
    $dir = $cleanup[] = $repo();
    file_put_contents($dir.'/not-a-dir', 'x');
    $sh($dir, 'git config --local core.hooksPath not-a-dir/hooks');
    [$code] = $install($dir);
    $check($code === 0 && $hooksPath($dir) === realpath($dir.'/.git').'/hooks', 'a file in the hooksPath falls back to .git/hooks');

    // A dangling symlink inside the checkout fails closed.
    $dir = $cleanup[] = $repo();
    symlink($outside.'/target', $dir.'/hooks-link');
    $sh($dir, 'git config --local core.hooksPath hooks-link/x');
    [$code] = $install($dir);
    $check($code === 0 && $hooksPath($dir) === realpath($dir.'/.git').'/hooks' && ! file_exists($outside), 'a dangling symlink falls back to .git/hooks');

    // A live symlink inside the checkout to a real directory outside it: resolved, so it falls back and writes nothing there.
    $live = sys_get_temp_dir().'/agents-hook-live-'.bin2hex(random_bytes(6));
    mkdir($live);
    foreach (['the link itself' => 'live-link', 'a missing dir under it' => 'live-link/new'] as $label => $path) {
        $dir = $cleanup[] = $repo();
        symlink($live, $dir.'/live-link');
        $sh($dir, 'git config --local core.hooksPath '.escapeshellarg($path));
        [$code] = $install($dir);
        $check($code === 0 && $hooksPath($dir) === realpath($dir.'/.git').'/hooks' && scandir($live) === ['.', '..'], "a symlink to an outside directory ({$label}) falls back to .git/hooks and writes nothing there");
    }
    $cleanup[] = $live;

    // An existing hook is chained, and a second run keeps it.
    $dir = $cleanup[] = $repo();
    file_put_contents($dir.'/.git/hooks/commit-msg', "#!/bin/sh\necho local-ran >> \"\$1\"\n");
    chmod($dir.'/.git/hooks/commit-msg', 0755);
    $install($dir);
    $install($dir);
    $check(is_file($dir.'/.git/hooks/commit-msg.local'), 'an existing commit-msg is kept as commit-msg.local');

    // The filter: bot identities go, people stay, a second pass changes nothing.
    $message = $dir.'/message';
    file_put_contents($message, implode("\n", [
        'Subject', '', 'Body.', '',
        'Amp-Thread-ID: https://ampcode.com/threads/T-1',
        'Co-authored-by: Amp <amp@ampcode.com>',
        'Co-authored-by: Claude <noreply@anthropic.com>',
        'Claude-Session: https://claude.ai/'.'code/session_x', // split so the privacy check's pattern doesn't match the fixture
        'Generated with OpenAI Codex',
        'Generated with care by Sam',
        'Co-authored-by: Claude Shannon <claude.shannon@example.com>',
        'Co-authored-by: Pat <pat@campus.example>',
    ])."\n");
    $sh($dir, '.git/hooks/commit-msg '.escapeshellarg($message));
    $once = file_get_contents($message);
    $sh($dir, '.git/hooks/commit-msg '.escapeshellarg($message));
    // The chained local hook appends "local-ran" on each pass; everything else must be exactly this.
    $kept = "Subject\n\nBody.\n\nGenerated with care by Sam\nCo-authored-by: Claude Shannon <claude.shannon@example.com>\nCo-authored-by: Pat <pat@campus.example>\n";
    $check($once === $kept."local-ran\n", 'AI trailers are stripped, human lines kept, and the chained hook ran');
    $check(file_get_contents($message) === $once."local-ran\n", 'a second pass removes nothing more');
} finally {
    foreach ($cleanup as $dir) {
        exec('rm -rf '.escapeshellarg($dir));
    }
    @unlink($block);
}
exit($failures === 0 ? 0 : 1);
