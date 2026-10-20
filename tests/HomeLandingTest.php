<?php

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tey\Concierge\Actions\AcceptInvitation;
use Tey\Concierge\Actions\InviteMember;
use Tey\Concierge\Support\GrantSet;
use Tey\Kit\Features\Workspaces\Models\Workspace;

require dirname(__DIR__).'/vendor/autoload.php';

final class HomeLandingTest extends TestCase
{
    public function createApplication()
    {
        return require __DIR__.'/application.php';
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    /** Signs in through the form (not JSON) and returns where it redirects. */
    private function signIn(int $id): string
    {
        $password = Str::random(40);
        User::findOrFail($id)->update(['password' => Hash::make($password)]);

        return $this->post('/login', ['email' => 'actor'.$id.'@example.test', 'password' => $password])->assertRedirect()->headers->get('Location');
    }

    public function test_a_user_with_no_workspace_sees_the_chooser(): void
    {
        require dirname(__DIR__).'/database/fixture.php';
        $this->assertSame(url('/workspaces'), $this->signIn(99));
    }

    public function test_a_user_with_one_workspace_lands_in_it(): void
    {
        require dirname(__DIR__).'/database/fixture.php';
        $this->assertSame(url('/workspaces/2/notes'), $this->signIn(2));
    }

    public function test_a_user_with_two_workspaces_keeps_the_chooser(): void
    {
        require dirname(__DIR__).'/database/fixture.php';
        $invite = InviteMember::run(User::findOrFail(1), Workspace::findOrFail(1), 'actor2@example.test', new GrantSet(['viewer']));
        AcceptInvitation::run(User::findOrFail(2), $invite->token);
        $this->assertSame(url('/workspaces'), $this->signIn(2));
    }
}
