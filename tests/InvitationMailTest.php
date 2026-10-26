<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Mail;
use Tey\Concierge\Actions\InviteMember;
use Tey\Concierge\Actions\ResendInvitation;
use Tey\Concierge\Concierge;
use Tey\Concierge\Support\ConciergeModels;
use Tey\Concierge\Support\GrantSet;
use Tey\Kit\Brand;
use Tey\Kit\Features\Workspaces\Models\Workspace;
use Tey\Kit\Features\Workspaces\TenantBoundary;

beforeEach(function () {
    $this->withoutMiddleware(PreventRequestForgery::class);
    $this->app['env'] = 'local';
    expect(app(Kernel::class)->call('migrate', ['--force' => true]))->toBe(0);
    expect(app(Kernel::class)->call('starter:first-owner', ['--no-interaction' => true]))->toBe(0);
    config(['mail.default' => 'array', 'concierge.invitations.deliver' => true, 'queue.default' => 'sync']);
    Brand::applyToMail();
});
function invitationMailSent(): array
{
    return Mail::mailer('array')->getSymfonyTransport()->messages()->map(fn ($m) => $m->getOriginalMessage())->all();
}
test('an invitation sends one branded email with the inviter the message and one button', function () {
    $workspace = Workspace::query()->firstOrFail();
    $owner = app(Concierge::class)->ownerOf($workspace)->user;
    $this->actingAs($owner)->postJson('/@'.$workspace->slug.'/settings/people/invitations', ['email' => 'mail.person@example.test', 'role' => 'viewer', 'message' => 'Glad to have you on the team.'])->assertCreated();

    $mails = invitationMailSent();
    expect($mails)->toHaveCount(1, 'Concierge\'s own email is swapped for the kit\'s, not sent as well');
    $mail = $mails[0];
    expect($mail->getSubject())->toBe($owner->name.' invited you to '.$workspace->name);
    expect($mail->getTo()[0]->getAddress())->toBe('mail.person@example.test');
    expect($mail->getFrom()[0]->getName())->toBe(config('app.name'));
    expect($mail->getFrom()[0]->getAddress())->toBe(config('kit.brand.mail_from_address'));
    expect($mail->getReplyTo()[0]->getAddress())->toBe(config('kit.brand.support_email'));

    $html = (string) $mail->getHtmlBody();
    $text = (string) $mail->getTextBody();
    $this->assertStringContainsString('Glad to have you on the team.', $html);
    $this->assertStringContainsString('<blockquote', $html);
    $this->assertStringContainsString('Join '.$workspace->name, $html);
    expect(substr_count($html, 'class="button'))->toBe(1, 'one button');
    expect($html)->toMatch('#/invitations/[A-Za-z0-9_-]{20,}#');

    // The preheader is the personal message, hidden in the body for inbox previews.
    expect($html)->toMatch('#display:\\s*none[^>]*>\\s*Glad to have you on the team\\.#');

    // The preheader is for inbox previews only: the text part carries the message once, in the quote.
    expect(substr_count($text, 'Glad to have you on the team.'))->toBe(1);
    expect($text)->toMatch('#/invitations/[A-Za-z0-9_-]{20,}#');
    $this->assertStringContainsString('Join '.$workspace->name, $text);
    $this->assertStringContainsString($owner->name, $text);
});
test('without a message there is no quote and the preheader names the workspace', function () {
    $workspace = Workspace::query()->firstOrFail();
    $owner = app(Concierge::class)->ownerOf($workspace)->user;
    $this->actingAs($owner)->postJson('/@'.$workspace->slug.'/settings/people/invitations', ['email' => 'quiet@example.test', 'role' => 'viewer'])->assertCreated();
    $html = (string) invitationMailSent()[0]->getHtmlBody();
    $this->assertStringNotContainsString('<blockquote', $html);
    expect($html)->toMatch('#display:\\s*none[^>]*>Join '.preg_quote($workspace->name, '#').' on #');

    // A message over 500 characters is refused.
    $this->actingAs($owner)->postJson('/@'.$workspace->slug.'/settings/people/invitations', ['email' => 'long@example.test', 'role' => 'viewer', 'message' => str_repeat('a', 501)])->assertUnprocessable();
});
test('a resend is the branded email too', function () {
    $workspace = Workspace::query()->firstOrFail();
    $owner = app(Concierge::class)->ownerOf($workspace)->user;
    app(TenantBoundary::class)->run($workspace->id, function () use ($owner, $workspace): void {
        InviteMember::run($owner, $workspace, 'again@example.test', new GrantSet(['viewer']), 'Hello again.');
        $invitation = ConciergeModels::invitation()::query()->where('email', 'again@example.test')->firstOrFail();
        $this->travel(2)->minutes();
        ResendInvitation::run($owner, $invitation, 'Hello again.');
    });
    $mails = invitationMailSent();
    expect($mails)->not->toBeEmpty();
    foreach ($mails as $mail) {
        $this->assertStringContainsString('invited you to '.$workspace->name, $mail->getSubject());
        $this->assertStringContainsString('Hello again.', (string) $mail->getHtmlBody());
    }
});
