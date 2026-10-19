<?php

namespace App\Models;

use App\Starter\Workspaces\Concerns\HasWorkspaces;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Passport\Contracts\OAuthenticatable;
use Laravel\Passport\HasApiTokens;
use Tey\Concierge\Contracts\Member;

final class User extends Authenticatable implements Member, OAuthenticatable
{
    use HasApiTokens, HasWorkspaces;

    protected $guarded = [];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['email_verified_at' => 'datetime'];
    }
}
