<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Passport\Contracts\OAuthenticatable;
use Laravel\Passport\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Tey\Concierge\Concerns\InteractsWithMemberships;
use Tey\Concierge\Contracts\Member;

final class User extends Authenticatable implements Member, OAuthenticatable
{
    use HasApiTokens, HasRoles, InteractsWithMemberships;

    protected $guard_name = 'web';

    protected $guarded = [];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['email_verified_at' => 'datetime'];
    }
}
