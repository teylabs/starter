<?php

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification as Notifications;
use Illuminate\Support\Str;
use Tests\Concerns\DefaultBrand;
use Tests\Concerns\Template;
use Tey\Kit\Brand;
use Tey\Kit\BrandAssets;
use Tey\Kit\Features\SessionUi\Http\Middleware\HandleInertiaRequests;

require_once __DIR__.'/Concerns/Template.php';
require_once __DIR__.'/Concerns/DefaultBrand.php';
uses(DefaultBrand::class);

beforeEach(function () {
    // Deterministic whatever brand the developer's .env sets: the package's own defaults, every key.
    $this->defaultBrand();
    config(['app.name' => 'Product name']);
});
function brandDirectory(): string
{
    $directory = sys_get_temp_dir().'/starter-brand-'.bin2hex(random_bytes(4));
    mkdir($directory);

    return $directory;
}
test('every asset is generated and a second run changes nothing', function () {
    if (BrandAssets::renderer() === null) {
        $this->markTestSkipped('No SVG renderer (Imagick with SVG support, or rsvg-convert) here; kit:brand refuses without one and the committed placeholders are used.');
    }
    $directory = brandDirectory();
    try {
        $first = BrandAssets::fromConfig()->write($directory);
        expect($first['written'])->toEqualCanonicalizing(BrandAssets::FILES);
        foreach (BrandAssets::FILES as $file) {
            expect(filesize($directory.'/'.$file))->toBeGreaterThan(0, $file);
        }
        $this->assertStringContainsString('<svg', (string) file_get_contents($directory.'/favicon.svg'));
        // The ICO: its header, and the 16 and 32 pixel entries the favicon needs.
        $ico = (string) file_get_contents($directory.'/favicon.ico');
        expect(substr($ico, 0, 4))->toBe("\x00\x00\x01\x00");
        $sizes = [];
        for ($entry = 0, $count = unpack('v', substr($ico, 4, 2))[1]; $entry < $count; $entry++) {
            $sizes[] = ord($ico[6 + $entry * 16]);
        }
        expect($sizes)->toContain(16);
        expect($sizes)->toContain(32);
        expect(array_slice(getimagesize($directory.'/apple-touch-icon.png'), 0, 2))->toBe([180, 180]);
        expect(array_slice(getimagesize($directory.'/og-image.png'), 0, 2))->toBe([1200, 630]);
        expect(getimagesize($directory.'/mail-mark.png')['mime'])->toBe('image/png');
        // Idempotent: the same brand renders the same bytes, so nothing is rewritten.
        $second = BrandAssets::fromConfig()->write($directory);
        expect($second['written'])->toBe([]);
        expect($second['unchanged'])->toEqualCanonicalizing(BrandAssets::FILES);
        // A new accent changes the assets that carry it.
        config(['kit.brand.accent' => '#0f766e']);
        expect(BrandAssets::fromConfig()->write($directory)['written'])->toContain('favicon.svg');
    } finally {
        array_map('unlink', glob($directory.'/*'));
        rmdir($directory);
    }
});
test('the committed placeholders are current and linked in the head', function () {
    // In the template the committed placeholders must match a fresh render. In an app they are its own; kit:brand --check checks their freshness.
    if (! Template::isTemplate()) {
        // An app: its own assets exist and are linked; their freshness is kit:brand --check's job, run with the app's real brand.
        foreach (BrandAssets::FILES as $file) {
            expect(public_path($file))->toBeFile();
            expect(filesize(public_path($file)))->toBeGreaterThan(0, $file);
        }
        $this->assertStringContainsString('rel="icon" href="'.asset('favicon.svg').'" type="image/svg+xml"', $this->get('/login')->assertOk()->getContent());

        return;
    }
    foreach (BrandAssets::FILES as $file) {
        expect(public_path($file))->toBeFile();
        expect(filesize(public_path($file)))->toBeGreaterThan(0, $file);
    }

    // In the template run with an app brand in the environment (composer test:brand-env), the committed files are still the template's.
    $defaults = self::packageBrandDefaults();
    $templateBrand = ($this->appBrand['logomark'] ?? null) === $defaults['logomark'] && ($this->appBrand['accent'] ?? null) === $defaults['accent'];
    if ($templateBrand && BrandAssets::renderer() !== null) {
        // Not the social image: its text uses whichever font the machine has.
        $rendered = BrandAssets::fromConfig()->render();
        // The SVG is text, so it must match byte for byte. Raster bytes depend on the machine's ImageMagick or librsvg build, so the
        // rasters must match the render's format and size; a changed mark or accent still shows in the SVG.
        expect(hash('sha256', (string) file_get_contents(public_path('favicon.svg'))))->toBe(hash('sha256', $rendered['favicon.svg']), 'favicon.svg is stale: run php artisan kit:brand');
        foreach (['favicon.ico', 'apple-touch-icon.png', 'mail-mark.png'] as $file) {
            expect(array_slice((array) getimagesize(public_path($file)), 0, 3))->toBe(array_slice((array) getimagesizefromstring($rendered[$file]), 0, 3), $file.' is stale: run php artisan kit:brand');
        }
    }
    $head = $this->get('/login')->assertOk()->getContent();
    $this->assertStringContainsString('rel="icon" href="'.asset('favicon.svg').'" type="image/svg+xml"', $head);
    $this->assertStringContainsString('rel="icon" href="'.asset('favicon.ico').'" sizes="32x32"', $head);
    $this->assertStringContainsString('rel="apple-touch-icon" href="'.asset('apple-touch-icon.png').'"', $head);
    $this->assertStringContainsString('property="og:image" content="'.asset('og-image.png').'"', $head);
});
test('mail is from the product replies to support and carries the brand', function () {
    config(['mail.default' => 'array', 'kit.brand.legal_name' => 'Acme Holdings', 'kit.brand.postal_address' => '5 Test Road, Testville', 'kit.brand.support_email' => 'help@acme.test']);

    // The kit applies the brand to the mailers: the sender is the product's name (never "Example") and replies go to support.
    Brand::applyToMail();
    expect(config('mail.from.name'))->toBe('Product name');
    expect(config('mail.from.address'))->toBe('updates@example.com');
    expect(config('mail.reply_to.address'))->toBe('help@acme.test');
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
    expect($sent)->toHaveCount(1);
    $email = $sent[0]->getOriginalMessage();
    expect($email->getFrom()[0]->getName())->toBe('Product name');
    expect($email->getFrom()[0]->getAddress())->toBe('updates@example.com');
    expect($email->getReplyTo()[0]->getAddress())->toBe('help@acme.test');
    $html = (string) $email->getHtmlBody();

    // The accent is a literal colour on the button and on a thin rule on the 570px header content, not the full-width header.
    expect(Brand::accent())->toBe('#52525b');
    expect($html)->toMatch('/class="button button-primary"[^>]*background-color: #52525b/');
    expect($html)->toMatch('/<table class="header-inner"[^>]*width="570"[^>]*border-bottom: 2px solid #52525b/');
    $this->assertDoesNotMatchRegularExpression('/<td class="header"[^>]*border-bottom/', $html);

    // The mark is an inline CID image with a matching PNG part; never a remote or data image.
    expect($html)->toMatch('/<img src="cid:kit-mark" width="40" height="40" alt="Product name"/');
    $this->assertDoesNotMatchRegularExpression('/<img[^>]+src="(https?:|data:)/', $html);
    $inline = array_filter($email->getAttachments(), fn ($part) => $part->getDisposition() === 'inline' && $part->getMediaType() === 'image');
    expect($inline)->toHaveCount(1);

    // The footer: identity; "{verb} by {legal name} · {address} · {contact}"; the manage link. No ©.
    expect($html)->toMatch('/<p[^>]*>Product name<\/p>/');
    expect($html)->toMatch('/<p[^>]*>Built by Acme Holdings · 5 Test Road, Testville · help@acme\.test<\/p>/');
    $this->assertStringContainsString('>Manage notifications</a>', $html);
    $this->assertStringNotContainsString('©', $html);
    $text = (string) $email->getTextBody();
    $this->assertStringContainsString('Built by Acme Holdings · 5 Test Road, Testville · help@acme.test', $text);
    $this->assertStringNotContainsString('©', $text);
});
/** @return array{0: string, 1: string} the rendered HTML and text of a notification, with this brand */
function brandSentMail(array $brand): array
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
test('a private app leaves the legal parts out of the mail and web footers', function () {
    [$html, $text] = brandSentMail(['legal_name' => 'Acme Holdings', 'postal_address' => '5 Test Road, Testville', 'support_email' => 'help@acme.test', 'private' => true]);
    expect($html)->toMatch('/<p[^>]*>help@acme\.test<\/p>/');
    $this->assertStringNotContainsString('Acme Holdings', $html.$text);
    $this->assertStringNotContainsString('5 Test Road', $html.$text);
    $this->assertStringNotContainsString('·', $text);
    expect(collect(explode("\n", $text))->first(fn (string $line) => str_contains($line, 'help@acme.test')))->toBe('help@acme.test');
    $this->assertDoesNotMatchRegularExpression('/<p[^>]*>\s*<\/p>/', $html);

    // With no contact either the line is gone, not an empty paragraph or a dangling separator.
    [$html, $text] = brandSentMail(['private' => true, 'support_email' => '']);
    $this->assertDoesNotMatchRegularExpression('/<p[^>]*>\s*(·|Built by)?\s*<\/p>/', $html);
    $this->assertStringNotContainsString('Built by', $html);

    // The web footer has no Terms or Privacy; the credit stays.
    expect(Brand::webFooter())->toBe(['identity' => 'Product name', 'credit' => 'Built by Tey Labs', 'links' => []]);

    // Any one part left empty is dropped cleanly even when not private.
    [$html] = brandSentMail(['private' => false, 'legal_name' => '', 'postal_address' => '5 Test Road, Testville', 'support_email' => 'help@acme.test']);
    expect($html)->toMatch('/<p[^>]*>5 Test Road, Testville · help@acme\.test<\/p>/');
    $this->assertStringNotContainsString('Built by  ', $html);

    // The placeholders: the legal parts are asked for unless the app is private.
    config(['kit.brand.legal_name' => 'Example Company', 'kit.brand.postal_address' => '1 Example Street, Example City', 'kit.brand.terms_url' => '/terms', 'kit.brand.privacy_url' => '/privacy', 'kit.brand.private' => false]);
    expect(Brand::placeholders())->toContain('brand.legal_name');
    config(['kit.brand.private' => true]);
    expect(Brand::placeholders())->not->toContain('brand.legal_name');
    expect(Brand::placeholders())->not->toContain('brand.terms_url');
    expect(Brand::placeholders())->not->toContain('brand.privacy_email');
    if (Template::isTemplate()) {
        $this->assertStringContainsString('KIT_BRAND_PRIVATE=false', (string) file_get_contents(dirname(__DIR__).'/.env.example'));
    }
});
test('the web footer is identity credit and legal links without a copyright sign', function () {
    require dirname(__DIR__).'/database/fixture.php';
    $password = Str::random(40);
    User::findOrFail(1)->update(['password' => Hash::make($password)]);
    $this->postJson('/login', ['email' => 'actor1@example.test', 'password' => $password])->assertOk();
    $this->withCookie(config('session.cookie'), app('session')->getId());
    Auth::forgetGuards();
    $inertia = ['X-Inertia' => 'true', 'Accept' => 'text/html', 'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(Request::create('/')) ?? ''];
    $this->get('/', $inertia)->assertOk()->assertJsonPath('props.shell.footer', ['identity' => 'Product name', 'credit' => 'Built by Tey Labs', 'links' => [['label' => 'Terms', 'url' => '/terms'], ['label' => 'Privacy', 'url' => '/privacy'], ['label' => 'Privacy requests', 'url' => 'mailto:privacy@example.com']]]);

    // A client build overrides the credit line; the identity can differ from the product name.
    config(['kit.brand.credit_verb' => 'Made', 'kit.brand.credit_org' => 'A Studio', 'kit.brand.credit_for' => 'Client Co', 'kit.brand.identity' => 'Client Co Platform']);
    $this->get('/', $inertia)->assertJsonPath('props.shell.footer.identity', 'Client Co Platform')->assertJsonPath('props.shell.footer.credit', 'Made by A Studio, for Client Co');
    $this->assertDoesNotMatchRegularExpression('/<(p|span|a)[^>]*>[^<]*©/u', (string) file_get_contents(resource_path('js/components/Shell/ShellFooter.vue')));
});
test('the default accent is neutral and a configured accent overrides it', function () {
    // The default has no hue (zinc), is listed as a placeholder, and leaves the theme's own scale alone.
    expect(Brand::accent())->toBe('#52525b');
    expect(Brand::accentIsNeutral())->toBeTrue();
    expect(Brand::placeholders())->toContain('brand.accent');
    $this->assertStringNotContainsString('--color-brand-600', $this->get('/login')->getContent());
    [$red, $green, $blue] = sscanf(Brand::accent(), '#%02x%02x%02x');
    expect(max($red, $green, $blue) - min($red, $green, $blue))->toBeLessThan(20, 'the neutral accent has no hue');

    // A configured accent overrides the brand scale, and is no longer a placeholder.
    config(['kit.brand.accent' => '#0f766e']);
    expect(Brand::accentIsNeutral())->toBeFalse();
    expect(Brand::placeholders())->not->toContain('brand.accent');
    $this->assertStringContainsString('--color-brand-600:#0f766e', $this->get('/login')->getContent());
    $this->assertStringContainsString('#0f766e', BrandAssets::fromConfig()->render()['favicon.svg']);
});
test('placeholders lists the defaults and is empty once configured', function () {
    expect(Brand::placeholders())->toEqualCanonicalizing(['brand.name', 'brand.logomark', 'brand.accent', 'brand.hello_email', 'brand.support_email', 'brand.privacy_email', 'brand.mail_from_address', 'brand.legal_name', 'brand.postal_address', 'brand.terms_url', 'brand.privacy_url']);
    config(['app.name' => 'Real Product', 'kit.brand' => [...config('kit.brand'), 'logomark' => '/real-mark.svg', 'accent' => '#0f766e', 'hello_email' => 'hello@real.test', 'support_email' => 'support@real.test', 'privacy_email' => 'privacy@real.test', 'mail_from_address' => 'updates@real.test', 'legal_name' => 'Real Ltd', 'postal_address' => '9 Real Street, Realtown', 'terms_url' => '/legal/terms', 'privacy_url' => '/legal/privacy']]);
    expect(Brand::placeholders())->toBe([]);

    // Only keys are named, never values.
    config(['kit.brand.accent' => '#52525b']);
    expect(Brand::placeholders())->toBe(['brand.accent']);
});
