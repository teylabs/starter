<?php

require_once __DIR__.'/../Concerns/FreshClone.php';

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\FreshClone;
use Tey\Concierge\LocalAuth\LocalAuthServiceProvider;

// The notes wall in a real browser: the composer saves a note (the card appears, the save toast shows, the box is ready for the next one), the card's menu edits
// it in place, and Delete asks first. The dashboard's notes card shows the note too.
it('writes, edits and deletes a note on the wall', function (): void {
    $environment = getenv('APP_ENV');
    try {
        putenv('APP_ENV=local');
        app()['env'] = 'local';
        config(['concierge.local_login' => FreshClone::localLogin('local')]);
        app()->getProvider(LocalAuthServiceProvider::class)->registerRoutes();
        app('router')->getRoutes()->refreshNameLookups();
        expect(app(Kernel::class)->call('migrate', ['--force' => true]))->toBe(0);
        expect(app(Kernel::class)->call('starter:first-owner', ['--no-interaction' => true]))->toBe(0);

        $page = visit('/login')->click('[data-local-login] button')->assertPathIs('/');
        $page->navigate('/notes')->assertPresent('[data-note-composer]')->assertSee('No notes yet')->assertNoJavaScriptErrors();
        $page->fill('note-body', 'Call the venue about Friday')->click('[data-note-composer] [data-action="save"]');
        $page->assertSeeIn('[data-scroll-list="notes"]', 'Call the venue about Friday')->assertSee('Note added');
        expect((string) $page->script('() => document.querySelector("#note-body").value'))->toBe('');
        // The reset composer is clean again: no "Unsaved changes" for a form that was just saved.
        expect((string) $page->script('() => document.querySelector("[data-note-composer]").innerText'))->not->toContain('Unsaved changes');
        expect(DB::table('kit_notes')->where('body', 'Call the venue about Friday')->count())->toBe(1);

        // Edit in place from the card's menu.
        $page->click('[data-note-menu]')->click('[data-note-edit]')->assertPresent('[data-note-editor]');
        $id = (int) DB::table('kit_notes')->value('id');
        $page->fill('note-'.$id.'-body', 'Call the venue about Saturday')->click('[data-note-editor] [data-action="save"]');
        $page->assertSeeIn('[data-scroll-list="notes"]', 'Call the venue about Saturday')->assertSee('Note saved');
        expect(DB::table('kit_notes')->where('id', $id)->value('version'))->toBe(2);
        $page->screenshot(filename: 'notes-wall');

        // The dashboard's notes card has it.
        $page->navigate('/')->assertSeeIn('[data-widget="notes.recent"]', 'Call the venue about Saturday');
        $page->screenshot(filename: 'notes-widget');

        // Delete asks first; confirming removes it.
        $page->navigate('/notes')->click('[data-note-menu]')->click('[data-note-delete]')->assertSee('Delete this note?')->click('Delete note');
        $page->assertSee('No notes yet')->assertNoJavaScriptErrors();
        expect(DB::table('kit_notes')->whereNull('deleted_at')->count())->toBe(0);
    } finally {
        putenv('APP_ENV='.$environment);
        app()['env'] = $environment;
    }
});
