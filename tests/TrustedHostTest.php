<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

require dirname(__DIR__).'/vendor/autoload.php';
require_once __DIR__.'/TestCase.php';

/** Behind the trusted proxy the forwarded scheme is honoured, but only APP_URL's host is: a forged host gets a 400. */
final class TrustedHostTest extends TestCase
{
    protected function tearDown(): void
    {
        // The trusted hosts are process-wide: leave none behind for the next test.
        Request::setTrustedHosts([]);
        parent::tearDown();
    }

    public function test_only_the_app_url_host_is_trusted(): void
    {
        config(['app.url' => 'https://app.example.test']);
        // Laravel skips the host check in unit tests; the real environment applies it.
        app()->instance('env', 'production');
        try {
            $forwarded = ['X-Forwarded-Proto' => 'https', 'X-Forwarded-Port' => '443'];
            $this->get('http://app.example.test/login', $forwarded + ['X-Forwarded-Host' => 'app.example.test'])->assertOk();
            $this->assertSame('https://app.example.test/workspaces', URL::to('/workspaces'));
            foreach (['attacker.example', 'evil.app.example.test', 'app.example.test.attacker.example', 'appxexample.test'] as $host) {
                // A forged forwarded host behind the real one, and a forged host outright.
                $this->get('http://app.example.test/login', $forwarded + ['X-Forwarded-Host' => $host])->assertBadRequest();
                $this->get('http://'.$host.'/login')->assertBadRequest();
            }
        } finally {
            app()->instance('env', 'testing');
        }
    }
}
