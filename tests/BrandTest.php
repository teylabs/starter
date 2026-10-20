<?php

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification as Notifications;
use Illuminate\Support\Str;
use Tey\Kit\Brand;
use Tey\Kit\BrandAssets;
use Tey\Kit\Features\SessionUi\Http\Middleware\HandleInertiaRequests;

require dirname(__DIR__).'/vendor/autoload.php';

/** The brand: one config for the product's identity, the assets made from its logomark, branded mail, the footers and the placeholders. */
final class BrandTest extends TestCase
{
    public function createApplication()
    {
        return require __DIR__.'/application.php';
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        // Deterministic whatever the developer's .env says.
        config(['app.name' => 'Product name', 'kit.brand.private' => false]);
    }

    private function directory(): string
    {
        $directory = sys_get_temp_dir().'/starter-brand-'.bin2hex(random_bytes(4));
        mkdir($directory);

        return $directory;
    }

    public function test_every_asset_is_generated_and_a_second_run_changes_nothing(): void
    {
        if (BrandAssets::renderer() === null) {
            $this->markTestSkipped('No SVG renderer (Imagick with SVG support, or rsvg-convert) here; kit:brand refuses without one and the committed placeholders are used.');
        }
        $directory = $this->directory();
        try {
            $first = BrandAssets::fromConfig()->write($directory);
            $this->assertEqualsCanonicalizing(BrandAssets::FILES, $first['written']);
            foreach (BrandAssets::FILES as $file) {
                $this->assertGreaterThan(0, filesize($directory.'/'.$file), $file);
            }
            $this->assertStringContainsString('<svg', (string) file_get_contents($directory.'/favicon.svg'));
            // The ICO: its header, and the 16 and 32 pixel entries the favicon needs.
            $ico = (string) file_get_contents($directory.'/favicon.ico');
            $this->assertSame("\x00\x00\x01\x00", substr($ico, 0, 4));
            $sizes = [];
            for ($entry = 0, $count = unpack('v', substr($ico, 4, 2))[1]; $entry < $count; $entry++) {
                $sizes[] = ord($ico[6 + $entry * 16]);
            }
            $this->assertContains(16, $sizes);
            $this->assertContains(32, $sizes);
            $this->assertSame([180, 180], array_slice(getimagesize($directory.'/apple-touch-icon.png'), 0, 2));
            $this->assertSame([1200, 630], array_slice(getimagesize($directory.'/og-image.png'), 0, 2));
            $this->assertSame('image/png', getimagesize($directory.'/mail-mark.png')['mime']);
            // Idempotent: the same brand renders the same bytes, so nothing is rewritten.
            $second = BrandAssets::fromConfig()->write($directory);
            $this->assertSame([], $second['written']);
            $this->assertEqualsCanonicalizing(BrandAssets::FILES, $second['unchanged']);
            // A new accent changes the assets that carry it.
            config(['kit.brand.accent' => '#0f766e']);
            $this->assertContains('favicon.svg', BrandAssets::fromConfig()->write($directory)['written']);
        } finally {
            array_map('unlink', glob($directory.'/*'));
            rmdir($directory);
        }
    }

    public function test_the_committed_placeholders_are_current_and_linked_in_the_head(): void
    {
        foreach (BrandAssets::FILES as $file) {
            $this->assertFileExists(public_path($file));
            $this->assertGreaterThan(0, filesize(public_path($file)), $file);
        }
        if (BrandAssets::renderer() !== null) {
            // Not the social image: its text uses whichever font the machine has.
            $rendered = BrandAssets::fromConfig()->render();
            foreach (['favicon.svg', 'favicon.ico', 'apple-touch-icon.png', 'mail-mark.png'] as $file) {
                $this->assertSame(hash('sha256', $rendered[$file]), hash('sha256', (string) file_get_contents(public_path($file))), $file.' is stale: run php artisan kit:brand');
            }
        }
        $head = $this->get('/login')->assertOk()->getContent();
        $this->assertStringContainsString('rel="icon" href="'.asset('favicon.svg').'" type="image/svg+xml"', $head);
        $this->assertStringContainsString('rel="icon" href="'.asset('favicon.ico').'" sizes="32x32"', $head);
        $this->assertStringContainsString('rel="apple-touch-icon" href="'.asset('apple-touch-icon.png').'"', $head);
        $this->assertStringContainsString('property="og:image" content="'.asset('og-image.png').'"', $head);
    }

    public function test_mail_is_from_the_product_replies_to_hello_and_carries_the_brand(): void
    {
        config(['mail.default' => 'array', 'kit.brand.legal_name' => 'Acme Holdings', 'kit.brand.postal_address' => '5 Test Road, Testville', 'kit.brand.support_email' => 'help@acme.test']);
        // The kit applies the brand to the mailers: the sender is the product's name (never "Example") and replies go to hello.
        Brand::applyToMail();
        $this->assertSame('Product name', config('mail.from.name'));
        $this->assertSame('updates@example.com', config('mail.from.address'));
        $this->assertSame('hello@example.com', config('mail.reply_to.address'));
        $notification = new class extends Notification
        {
            public function via(): array
            {
                return ['mail'];
            }

            public function toMail(): MailMessage
            {
                return (new MailMessage)->subject('Hello')->line('A line.')->action('Open it', 'https://app.test/open');
            }
        };
        Notifications::route('mail', 'someone@example.test')->notifyNow($notification);
        $sent = Mail::mailer('array')->getSymfonyTransport()->messages();
        $this->assertCount(1, $sent);
        $email = $sent[0]->getOriginalMessage();
        $this->assertSame('Product name', $email->getFrom()[0]->getName());
        $this->assertSame('updates@example.com', $email->getFrom()[0]->getAddress());
        $this->assertSame('hello@example.com', $email->getReplyTo()[0]->getAddress());
        $html = (string) $email->getHtmlBody();
        // The accent is a literal colour on the button and on a thin rule on the 570px header content, not the full-width header.
        $this->assertSame('#52525b', Brand::accent());
        $this->assertMatchesRegularExpression('/class="button button-primary"[^>]*background-color: #52525b/', $html);
        $this->assertMatchesRegularExpression('/<table class="header-inner"[^>]*width="570"[^>]*border-bottom: 2px solid #52525b/', $html);
        $this->assertDoesNotMatchRegularExpression('/<td class="header"[^>]*border-bottom/', $html);
        // The mark is an inline CID image with a matching PNG part; never a remote or data image.
        $this->assertMatchesRegularExpression('/<img src="cid:kit-mark" width="40" height="40" alt="Product name"/', $html);
        $this->assertDoesNotMatchRegularExpression('/<img[^>]+src="(https?:|data:)/', $html);
        $inline = array_filter($email->getAttachments(), fn ($part) => $part->getDisposition() === 'inline' && $part->getMediaType() === 'image');
        $this->assertCount(1, $inline);
        // The footer: identity; "{verb} by {legal name} · {address} · {contact}"; the manage link. No ©.
        $this->assertMatchesRegularExpression('/<p[^>]*>Product name<\/p>/', $html);
        $this->assertMatchesRegularExpression('/<p[^>]*>Built by Acme Holdings · 5 Test Road, Testville · help@acme\.test<\/p>/', $html);
        $this->assertStringContainsString('>Manage notifications</a>', $html);
        $this->assertStringNotContainsString('©', $html);
        $text = (string) $email->getTextBody();
        $this->assertStringContainsString('Built by Acme Holdings · 5 Test Road, Testville · help@acme.test', $text);
        $this->assertStringNotContainsString('©', $text);
    }

    /** @return array{0: string, 1: string} the rendered HTML and text of a notification, with this brand */
    private function sentMail(array $brand): array
    {
        config(['mail.default' => 'array', 'kit.brand' => array_merge(config('kit.brand'), $brand)]);
        Brand::applyToMail();
        $notification = new class extends Notification
        {
            public function via(): array
            {
                return ['mail'];
            }

            public function toMail(): MailMessage
            {
                return (new MailMessage)->subject('Hello')->line('A line.');
            }
        };
        Notifications::route('mail', 'someone@example.test')->notifyNow($notification);
        $email = Mail::mailer('array')->getSymfonyTransport()->messages()->last()->getOriginalMessage();

        return [(string) $email->getHtmlBody(), (string) $email->getTextBody()];
    }

    public function test_a_private_app_leaves_the_legal_parts_out_of_the_mail_and_web_footers(): void
    {
        [$html, $text] = $this->sentMail(['legal_name' => 'Acme Holdings', 'postal_address' => '5 Test Road, Testville', 'support_email' => 'help@acme.test', 'private' => true]);
        $this->assertMatchesRegularExpression('/<p[^>]*>help@acme\.test<\/p>/', $html);
        $this->assertStringNotContainsString('Acme Holdings', $html.$text);
        $this->assertStringNotContainsString('5 Test Road', $html.$text);
        $this->assertStringNotContainsString('·', $text);
        $this->assertSame('help@acme.test', collect(explode("\n", $text))->first(fn (string $line) => str_contains($line, 'help@acme.test')));
        $this->assertDoesNotMatchRegularExpression('/<p[^>]*>\s*<\/p>/', $html);
        // With no contact either the line is gone, not an empty paragraph or a dangling separator.
        [$html, $text] = $this->sentMail(['private' => true, 'support_email' => '']);
        $this->assertDoesNotMatchRegularExpression('/<p[^>]*>\s*(·|Built by)?\s*<\/p>/', $html);
        $this->assertStringNotContainsString('Built by', $html);
        // The web footer has no Terms or Privacy; the credit stays.
        $this->assertSame(['identity' => 'Product name', 'credit' => 'Built by Tey Labs', 'links' => []], Brand::webFooter());
        // Any one part left empty is dropped cleanly even when not private.
        [$html] = $this->sentMail(['private' => false, 'legal_name' => '', 'postal_address' => '5 Test Road, Testville', 'support_email' => 'help@acme.test']);
        $this->assertMatchesRegularExpression('/<p[^>]*>5 Test Road, Testville · help@acme\.test<\/p>/', $html);
        $this->assertStringNotContainsString('Built by  ', $html);
        // The placeholders: the legal parts are asked for unless the app is private.
        config(['kit.brand.legal_name' => 'Example Company', 'kit.brand.postal_address' => '1 Example Street, Example City', 'kit.brand.terms_url' => '/terms', 'kit.brand.privacy_url' => '/privacy', 'kit.brand.private' => false]);
        $this->assertContains('brand.legal_name', Brand::placeholders());
        config(['kit.brand.private' => true]);
        $this->assertNotContains('brand.legal_name', Brand::placeholders());
        $this->assertNotContains('brand.terms_url', Brand::placeholders());
        $this->assertStringContainsString('KIT_BRAND_PRIVATE=false', (string) file_get_contents(dirname(__DIR__).'/.env.example'));
    }

    public function test_the_web_footer_is_identity_credit_and_legal_links_without_a_copyright_sign(): void
    {
        require dirname(__DIR__).'/database/fixture.php';
        $password = Str::random(40);
        User::findOrFail(1)->update(['password' => Hash::make($password)]);
        $this->postJson('/login', ['email' => 'actor1@example.test', 'password' => $password])->assertOk();
        $this->withCookie(config('session.cookie'), app('session')->getId());
        Auth::forgetGuards();
        $inertia = ['X-Inertia' => 'true', 'Accept' => 'text/html', 'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(Request::create('/')) ?? ''];
        $this->get('/dashboard', $inertia)->assertOk()->assertJsonPath('props.layout.footer', ['identity' => 'Product name', 'credit' => 'Built by Tey Labs', 'links' => [['label' => 'Terms', 'url' => '/terms'], ['label' => 'Privacy', 'url' => '/privacy']]]);
        // A client build overrides the credit line; the identity can differ from the product name.
        config(['kit.brand.credit_verb' => 'Made', 'kit.brand.credit_org' => 'A Studio', 'kit.brand.credit_for' => 'Client Co', 'kit.brand.identity' => 'Client Co Platform']);
        $this->get('/dashboard', $inertia)->assertJsonPath('props.layout.footer.identity', 'Client Co Platform')->assertJsonPath('props.layout.footer.credit', 'Made by A Studio, for Client Co');
        $this->assertDoesNotMatchRegularExpression('/<(p|span|a)[^>]*>[^<]*©/u', (string) file_get_contents(resource_path('js/components/Shell/ShellFooter.vue')));
    }

    public function test_the_default_accent_is_neutral_and_a_configured_accent_overrides_it(): void
    {
        // The default has no hue (zinc), is listed as a placeholder, and leaves the theme's own scale alone.
        $this->assertSame('#52525b', Brand::accent());
        $this->assertTrue(Brand::accentIsNeutral());
        $this->assertContains('brand.accent', Brand::placeholders());
        $this->assertStringNotContainsString('--color-brand-600', $this->get('/login')->getContent());
        [$red, $green, $blue] = sscanf(Brand::accent(), '#%02x%02x%02x');
        $this->assertLessThan(20, max($red, $green, $blue) - min($red, $green, $blue), 'the neutral accent has no hue');
        // A configured accent overrides the brand scale, and is no longer a placeholder.
        config(['kit.brand.accent' => '#0f766e']);
        $this->assertFalse(Brand::accentIsNeutral());
        $this->assertNotContains('brand.accent', Brand::placeholders());
        $this->assertStringContainsString('--color-brand-600:#0f766e', $this->get('/login')->getContent());
        $this->assertStringContainsString('#0f766e', BrandAssets::fromConfig()->render()['favicon.svg']);
    }

    public function test_placeholders_lists_the_defaults_and_is_empty_once_configured(): void
    {
        $this->assertEqualsCanonicalizing(['brand.name', 'brand.logomark', 'brand.accent', 'brand.hello_email', 'brand.support_email', 'brand.mail_from_address', 'brand.legal_name', 'brand.postal_address', 'brand.terms_url', 'brand.privacy_url'], Brand::placeholders());
        config(['app.name' => 'Real Product', 'kit.brand' => [...config('kit.brand'), 'logomark' => '/real-mark.svg', 'accent' => '#0f766e', 'hello_email' => 'hello@real.test', 'support_email' => 'support@real.test', 'mail_from_address' => 'updates@real.test', 'legal_name' => 'Real Ltd', 'postal_address' => '9 Real Street, Realtown', 'terms_url' => '/legal/terms', 'privacy_url' => '/legal/privacy']]);
        $this->assertSame([], Brand::placeholders());
        // Only keys are named, never values.
        config(['kit.brand.accent' => '#52525b']);
        $this->assertSame(['brand.accent'], Brand::placeholders());
    }
}
