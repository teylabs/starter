<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tey\Kit\Features\SessionUi\Account\SecurityLog;
use Tey\Kit\Features\SessionUi\UserState;

test('the state goes with the account and a soft delete keeps it', function () {
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
    expect(DB::table('kit_user_state')->where('user_id', 50)->count())->toBe(0);
    expect(DB::table('kit_user_state')->where('user_id', 1)->count())->toBe(2);
    expect(DB::table('kit_user_state')->where('user_id', 51)->count())->toBe(2);

    // The foreign key removes the rows in the database itself, whatever the model does.
    DB::table('users')->where('id', 51)->delete();
    expect(DB::table('kit_user_state')->where('user_id', 51)->count())->toBe(0);
    expect(DB::table('kit_user_state')->where('user_id', 1)->count())->toBe(2);

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
    expect(DB::table('kit_user_state')->where('user_id', 1)->count())->toBe(2, 'a soft delete keeps the state');
    $soft->force = true;
    SecurityLog::forget($soft);
    expect(DB::table('kit_user_state')->where('user_id', 1)->count())->toBe(0, 'a force delete removes it');
});
