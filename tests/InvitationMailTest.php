<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Mail;
use Tey\Concierge\Actions\InviteMember;
use Tey\Concierge\Actions\ResendInvitation;
use Tey\Concierge\Concierge;
use Tey\Concierge\Support\ConciergeModels;
use Tey\Concierge\Support\GrantSet;
use Tey\Kit\Brand;
use Tey\Kit\Features\Workspaces\Models\Workspace;
use Tey\Kit\Features\Workspaces\TenantBoundary;

require dirname(__DIR__).'/vendor/autoload.php';

/** The invitation email: branded, with who invited you and their words, a specific subject, a preheader and one button, in HTML and plain text. */
final class InvitationMailTest extends TestCase
{
    public function createApplication()
    {
        return require __DIR__.'/application.php';
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->withoutMiddleware(PreventRequestForgery::class);
        $this->app['env'] = 'local';
        $this->assertSame(0, app(Kernel::class)->call('migrate', ['--force' => true]));
        $this->assertSame(0, app(Kernel::class)->call('starter:first-owner', ['--no-interaction' => true]));
        config(['mail.default' => 'array', 'concierge.invitations.deliver' => true, 'queue.default' => 'sync']);
        Brand::applyToMail();
    }

    private function sent(): array
    {
        return Mail::mailer('array')->getSymfonyTransport()->messages()->map(fn ($m) => $m->getOriginalMessage())->all();
    }

    public function test_an_invitation_sends_one_branded_email_with_the_inviter_the_message_and_one_button(): void
    {
        $workspace = Workspace::query()->firstOrFail();
        $owner = app(Concierge::class)->ownerOf($workspace)->user;
        $this->actingAs($owner)->postJson('/@'.$workspace->slug.'/settings/people/invitations', ['email' => 'mail.person@example.test', 'role' => 'viewer', 'message' => 'Glad to have you on the team.'])->assertCreated();

        $mails = $this->sent();
        $this->assertCount(1, $mails, 'Concierge\'s own email is swapped for the kit\'s, not sent as well');
        $mail = $mails[0];
        $this->assertSame($owner->name.' invited you to '.$workspace->name, $mail->getSubject());
        $this->assertSame('mail.person@example.test', $mail->getTo()[0]->getAddress());
        $this->assertSame(config('app.name'), $mail->getFrom()[0]->getName());
        $this->assertSame(config('kit.brand.mail_from_address'), $mail->getFrom()[0]->getAddress());
        $this->assertSame(config('kit.brand.support_email'), $mail->getReplyTo()[0]->getAddress());

        $html = (string) $mail->getHtmlBody();
        $text = (string) $mail->getTextBody();
        $this->assertStringContainsString('Glad to have you on the team.', $html);
        $this->assertStringContainsString('<blockquote', $html);
        $this->assertStringContainsString('Join '.$workspace->name, $html);
        $this->assertSame(1, substr_count($html, 'class="button'), 'one button');
        $this->assertMatchesRegularExpression('#/invitations/[A-Za-z0-9_-]{20,}#', $html);
        // The preheader is the personal message, hidden in the body for inbox previews.
        $this->assertMatchesRegularExpression('#display:\\s*none[^>]*>\\s*Glad to have you on the team\\.#', $html);
        // The preheader is for inbox previews only: the text part carries the message once, in the quote.
        $this->assertSame(1, substr_count($text, 'Glad to have you on the team.'));
        $this->assertMatchesRegularExpression('#/invitations/[A-Za-z0-9_-]{20,}#', $text);
        $this->assertStringContainsString('Join '.$workspace->name, $text);
        $this->assertStringContainsString($owner->name, $text);
    }

    public function test_without_a_message_there_is_no_quote_and_the_preheader_names_the_workspace(): void
    {
        $workspace = Workspace::query()->firstOrFail();
        $owner = app(Concierge::class)->ownerOf($workspace)->user;
        $this->actingAs($owner)->postJson('/@'.$workspace->slug.'/settings/people/invitations', ['email' => 'quiet@example.test', 'role' => 'viewer'])->assertCreated();
        $html = (string) $this->sent()[0]->getHtmlBody();
        $this->assertStringNotContainsString('<blockquote', $html);
        $this->assertMatchesRegularExpression('#display:\\s*none[^>]*>Join '.preg_quote($workspace->name, '#').' on #', $html);
        // A message over 500 characters is refused.
        $this->actingAs($owner)->postJson('/@'.$workspace->slug.'/settings/people/invitations', ['email' => 'long@example.test', 'role' => 'viewer', 'message' => str_repeat('a', 501)])->assertUnprocessable();
    }

    public function test_a_resend_is_the_branded_email_too(): void
    {
        $workspace = Workspace::query()->firstOrFail();
        $owner = app(Concierge::class)->ownerOf($workspace)->user;
        app(TenantBoundary::class)->run($workspace->id, function () use ($owner, $workspace): void {
            InviteMember::run($owner, $workspace, 'again@example.test', new GrantSet(['viewer']), 'Hello again.');
            $invitation = ConciergeModels::invitation()::query()->where('email', 'again@example.test')->firstOrFail();
            $this->travel(2)->minutes();
            ResendInvitation::run($owner, $invitation, 'Hello again.');
        });
        $mails = $this->sent();
        $this->assertNotEmpty($mails);
        foreach ($mails as $mail) {
            $this->assertStringContainsString('invited you to '.$workspace->name, $mail->getSubject());
            $this->assertStringContainsString('Hello again.', (string) $mail->getHtmlBody());
        }
    }
}
