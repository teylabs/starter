<?php

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tey\Kit\Features\SessionUi\Http\Middleware\HandleInertiaRequests;

require dirname(__DIR__).'/vendor/autoload.php';

/** Guard 4: no `can` maps. Pages carry action descriptors (null when denied, disabled with a reason when shown but unusable), never permission or role flags. */
final class ActionDescriptorsTest extends TestCase
{
    private string $password = '';

    public function createApplication()
    {
        return require __DIR__.'/application.php';
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function inertia(): array
    {
        return ['X-Inertia' => 'true', 'Accept' => 'text/html', 'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(Request::create('/')) ?? ''];
    }

    private function signIn(int $id): void
    {
        Auth::forgetGuards();
        $this->password = Str::random(40);
        User::findOrFail($id)->update(['password' => Hash::make($this->password)]);
        $this->postJson('/login', ['email' => 'actor'.$id.'@example.test', 'password' => $this->password])->assertOk();
        $this->withCookie(config('session.cookie'), app('session')->getId());
        Auth::forgetGuards();
    }

    private function signOut(): void
    {
        $this->postJson('/logout')->assertNoContent();
        $this->flushSession();
        $this->defaultCookies = [];
        $this->unencryptedCookies = [];
        Auth::forgetGuards();
    }

    private const BANNED = ['can', 'permissions', 'abilities', 'isAdmin', 'is_admin'];

    private function walk(mixed $value, string $path, array &$found): void
    {
        if (! is_array($value)) {
            return;
        }
        foreach ($value as $key => $child) {
            if (is_string($key) && (in_array($key, self::BANNED, true) || str_starts_with($key, 'can_') || in_array($key, ['manageable', 'is_owner'], true))) {
                $found[] = $path.'.'.$key;
            }
            $this->walk($child, $path.'.'.$key, $found);
        }
    }

    public function test_no_response_carries_a_can_map_or_permission_flag_and_denied_actions_are_null_or_explained(): void
    {
        require dirname(__DIR__).'/database/fixture.php';
        $this->assertSame(0, app(Kernel::class)->call('kit:permissions:sync', ['--no-interaction' => true]));
        $pages = ['/@alpha/dashboard', '/@alpha/settings', '/@alpha/settings/general', '/@alpha/settings/people', '/@alpha/settings/connections', '/me', '/me/profile', '/me/workspaces'];
        $found = [];
        // The guest pages, then the owner and the viewer.
        foreach (['/login'] as $url) {
            $this->walk($this->get($url, $this->inertia())->json('props'), $url, $found);
        }
        $this->signIn(1);
        foreach ($pages as $url) {
            $response = $this->get($url, $this->inertia());
            if ($response->getStatusCode() === 200) {
                $this->walk($response->json('props'), $url, $found);
            }
        }
        $owner = $this->get('/@alpha/settings/general', $this->inertia())->json('props');
        $this->assertFalse($owner['save']['disabled']);
        $this->assertSame('put', $owner['transfer']['method']);
        $this->assertTrue($owner['leave']['disabled'], 'an owner is shown Leave as unusable, with the reason');
        $this->assertNotSame('', (string) $owner['leave']['reason']);
        $this->signOut();
        $this->signIn(3);
        foreach ($pages as $url) {
            $response = $this->get($url, $this->inertia());
            if ($response->getStatusCode() === 200) {
                $this->walk($response->json('props'), $url, $found);
            }
        }
        $viewer = $this->get('/@alpha/settings/general', $this->inertia())->assertOk()->json('props');
        $this->assertTrue($viewer['save']['disabled']);
        $this->assertNotSame('', (string) $viewer['save']['reason'], 'a disabled action says why');
        $this->assertNull($viewer['transfer'], 'a denied action is absent');
        $this->assertFalse($viewer['leave']['disabled']);
        $this->assertSame([], $found, 'no can map, can_* flag, permissions list or role flag in any page or shared prop');
    }

    public function test_the_guest_shell_response_matches_its_generated_type_and_has_no_shell_only_field(): void
    {
        require dirname(__DIR__).'/database/fixture.php';
        $types = (string) file_get_contents(dirname(__DIR__).'/resources/js/types.generated.d.ts');
        $this->assertSame(1, preg_match('/export type GuestShellData = \{(.*?)\n\};/s', $types, $guest));
        $declared = [[], []];
        $depth = 0;
        foreach (explode("\n", $guest[1]) as $line) {
            if ($depth === 0 && preg_match('/^([a-z_]+):/', $line, $key)) {
                $declared[1][] = $key[1];
            }
            $depth += substr_count($line, '{') - substr_count($line, '}');
        }
        $sent = array_keys($this->get('/login', $this->inertia())->json('props.shell'));
        sort($sent);
        $declaredKeys = $declared[1];
        sort($declaredKeys);
        $this->assertSame($declaredKeys, $sent, 'the guest shell sends exactly what GuestShellData declares');
        foreach (['user', 'navigation', 'switcher', 'links', 'env', 'can'] as $field) {
            $this->assertNotContains($field, $sent, $field.' belongs to ShellData only');
        }
    }
}
