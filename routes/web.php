<?php

// standards-pattern: ui-page.public-legal

use Illuminate\Support\Facades\Route;
use Tey\Kit\Features\Shell\PublicMarkdown;

// Starter features register their own route files through the StarterRegistry.

// The footer's Terms and Privacy links point at /terms and /privacy until an app sets `terms_url` and `privacy_url` in config/brand.php. These two stand-in
// pages answer there, so a new app has no dead links; each says to replace it before deploying, and kit:preflight refuses the stand-in URLs in production.
// Replace them with the app's own documents: the kit's public Markdown page renders the markdown on the server, sanitized.
Route::get('/terms', fn () => PublicMarkdown::page('Terms of service', (string) file_get_contents(resource_path('legal/terms.md')), 'A stand-in for the terms of service. Replace it before you deploy.'))->name('legal.terms');
Route::get('/privacy', fn () => PublicMarkdown::page('Privacy policy', (string) file_get_contents(resource_path('legal/privacy.md')), 'A stand-in for the privacy policy. Replace it before you deploy.'))->name('legal.privacy');
