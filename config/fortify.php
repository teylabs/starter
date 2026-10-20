<?php

use Laravel\Fortify\Features;

// The account pages under /me use Fortify's own features and actions: profile information (with the email re-verified when it changes), passwords, two-factor
// authentication (confirmed with a code before it is on) and email verification. Registration and password reset stay off.
return ['guard' => 'web', 'middleware' => ['web'], 'username' => 'email', 'email' => 'email', 'home' => '/me/workspaces', 'views' => true, 'features' => [Features::emailVerification(), Features::updateProfileInformation(), Features::updatePasswords(), Features::twoFactorAuthentication(['confirm' => true, 'confirmPassword' => true])]];
