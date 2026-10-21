<?php

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Tey\Kit\Features\SessionUi\Account\SecurityLog;
use Tey\Kit\Features\SessionUi\UserState;

require dirname(__DIR__).'/vendor/autoload.php';

/** What the kit remembers about a person goes with their account, like the security log: with the model, with the database's own foreign key, and only on a force delete. */
final class UserStateTest extends TestCase
{
    public function createApplication()
    {
        return require __DIR__.'/application.php';
    }

    public function test_the_state_goes_with_the_account_and_a_soft_delete_keeps_it(): void
    {
        require dirname(__DIR__).'/database/fixture.php';
        foreach ([50, 51] as $id) {
            User::create(['id' => $id, 'name' => 'Temp '.$id, 'email' => 'temp'.$id.'@example.test']);
        }
        foreach ([1, 50, 51] as $id) {
            UserState::set($id, 'timezone', 'Europe/Amsterdam');
            UserState::set($id, 'welcome.dismissed', '1', 7);
        }
        // Deleting a user removes their rows and leaves everyone else's.
        User::findOrFail(50)->delete();
        $this->assertSame(0, DB::table('kit_user_state')->where('user_id', 50)->count());
        $this->assertSame(2, DB::table('kit_user_state')->where('user_id', 1)->count());
        $this->assertSame(2, DB::table('kit_user_state')->where('user_id', 51)->count());
        // The foreign key removes the rows in the database itself, whatever the model does.
        DB::table('users')->where('id', 51)->delete();
        $this->assertSame(0, DB::table('kit_user_state')->where('user_id', 51)->count());
        $this->assertSame(2, DB::table('kit_user_state')->where('user_id', 1)->count());
        // A model that soft-deletes keeps the state until the force delete.
        $soft = new class
        {
            public bool $force = false;

            public function isForceDeleting(): bool
            {
                return $this->force;
            }

            public function getKey(): int
            {
                return 1;
            }
        };
        SecurityLog::forget($soft);
        $this->assertSame(2, DB::table('kit_user_state')->where('user_id', 1)->count(), 'a soft delete keeps the state');
        $soft->force = true;
        SecurityLog::forget($soft);
        $this->assertSame(0, DB::table('kit_user_state')->where('user_id', 1)->count(), 'a force delete removes it');
    }
}
