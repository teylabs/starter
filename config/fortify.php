<?php

use Laravel\Fortify\Features;

// The account pages under /me use Fortify's own features and actions: profile information (with the email re-verified when it changes), passwords, two-factor
// authentication (confirmed with a code before it is on) and email verification. Password reset by emailed link is on: "Forgot your password?" on the sign-in
// page asks for the link (the kit's Auth/ForgotPassword and Auth/ResetPassword pages), which needs mail that can send it and the password_reset_tokens table.
// Registration stays off: accounts come from invitations.
return ['guard' => 'web', 'middleware' => ['web'], 'username' => 'email', 'email' => 'email', 'home' => '/me/workspaces', 'views' => true, 'features' => [Features::resetPasswords(), Features::emailVerification(), Features::updateProfileInformation(), Features::updatePasswords(), Features::twoFactorAuthentication(['confirm' => true, 'confirmPassword' => true])]];
