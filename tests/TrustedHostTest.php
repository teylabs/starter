<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

afterEach(function () {
    // The trusted hosts are process-wide: leave none behind for the next test.
    Request::setTrustedHosts([]);
});
test('only the app url host is trusted', function () {
    config(['app.url' => 'https://app.example.test']);

    // Laravel skips the host check in unit tests; the real environment applies it.
    app()->instance('env', 'production');
    try {
        $forwarded = ['X-Forwarded-Proto' => 'https', 'X-Forwarded-Port' => '443'];
        $this->get('http://app.example.test/login', $forwarded + ['X-Forwarded-Host' => 'app.example.test'])->assertOk();
        expect(URL::to('/workspaces'))->toBe('https://app.example.test/workspaces');
        foreach (['attacker.example', 'evil.app.example.test', 'app.example.test.attacker.example', 'appxexample.test'] as $host) {
            // A forged forwarded host behind the real one, and a forged host outright.
            $this->get('http://app.example.test/login', $forwarded + ['X-Forwarded-Host' => $host])->assertBadRequest();
            $this->get('http://'.$host.'/login')->assertBadRequest();
        }
    } finally {
        app()->instance('env', 'testing');
    }
});
