<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Passport\Contracts\OAuthenticatable;
use Laravel\Passport\HasApiTokens;
use Tey\Concierge\Contracts\Member;
use Tey\Kit\Features\Workspaces\Concerns\HasWorkspaces;

final class User extends Authenticatable implements Member, MustVerifyEmail, OAuthenticatable
{
    use HasApiTokens, HasWorkspaces, Notifiable, TwoFactorAuthenticatable;

    protected $guarded = [];

    protected $hidden = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'];

    protected function casts(): array
    {
        return ['email_verified_at' => 'datetime', 'two_factor_confirmed_at' => 'datetime'];
    }
}
