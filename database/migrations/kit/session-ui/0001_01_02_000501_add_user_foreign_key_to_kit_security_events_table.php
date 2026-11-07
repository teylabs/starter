<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The user model's table and key: the kit's configured user model, else the `users` auth provider's.
     *
     * @return array{string, string}
     */
    private function user(): array
    {
        /** @var class-string<Model> $class */
        $class = config('kit.models.user') ?? config('auth.providers.users.model');
        $model = new $class;

        return [$model->getTable(), $model->getKeyName()];
    }

    public function up(): void
    {
        [$table, $key] = $this->user();
        // The security log is kept as long as the account is, and goes with it: a deleted user's rows are deleted. Rows left behind by a user already gone are cleared first.
        DB::table('kit_security_events')->whereNotIn('user_id', DB::table($table)->select($key))->delete();
        Schema::table('kit_security_events', function (Blueprint $t) use ($table, $key): void {
            $t->foreign('user_id')->references($key)->on($table)->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('kit_security_events', function (Blueprint $t): void {
            $t->dropForeign(['user_id']);
        });
    }
};
