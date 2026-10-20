<?php

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tey\Kit\Features\SessionUi\Avatars;
use Tey\Kit\Features\SessionUi\Http\Middleware\HandleInertiaRequests;
use Tey\Kit\Features\SessionUi\Models\UserAvatar;

require dirname(__DIR__).'/vendor/autoload.php';

/** Avatars: upload, replace and remove on /me, validated and stripped, only your own, initials when there is none, never a stale URL. */
final class AvatarTest extends TestCase
{
    public function createApplication()
    {
        return require __DIR__.'/application.php';
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('public');
        config(['media-library.disk_name' => 'public']);
    }

    private function inertia(): array
    {
        return ['X-Inertia' => 'true', 'Accept' => 'text/html', 'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(Request::create('/')) ?? ''];
    }

    private function signIn(int $id): void
    {
        $password = Str::random(40);
        User::findOrFail($id)->update(['password' => Hash::make($password)]);
        $this->postJson('/login', ['email' => 'actor'.$id.'@example.test', 'password' => $password])->assertOk();
        $this->withCookie(config('session.cookie'), app('session')->getId());
        Auth::forgetGuards();
    }

    private function original(int $userId): Media
    {
        return Media::query()->where('model_id', $userId)->where('collection_name', 'avatar')->firstOrFail();
    }

    public function test_upload_replace_and_remove(): void
    {
        require dirname(__DIR__).'/database/fixture.php';
        $this->signIn(1);
        $this->postJson('/me/avatar', ['avatar' => UploadedFile::fake()->image('me.png', 600, 400)])->assertCreated()->assertJsonStructure(['avatar' => ['url', 'url_2x']]);
        $first = Avatars::urls(1);
        $media = $this->original(1);
        // Stored as a centred square, with the 96px and 192px thumbnails made at once.
        [$width, $height] = getimagesize($media->getPath());
        $this->assertSame([400, 400], [$width, $height]);
        $this->assertSame([96, 96], array_slice(getimagesize($media->getPath('thumb')), 0, 2));
        $this->assertSame([192, 192], array_slice(getimagesize($media->getPath('thumb2x')), 0, 2));
        // The shell shows it.
        $this->get('/dashboard', $this->inertia())->assertJsonPath('props.layout.user.avatar_url', $first['url'])->assertJsonPath('props.layout.user.avatar_url_2x', $first['url_2x']);
        // Replacing keeps one avatar, at a new URL, and deletes the old file.
        $oldPath = $media->getPath();
        $this->postJson('/me/avatar', ['avatar' => UploadedFile::fake()->image('new.jpg', 300, 300)])->assertCreated();
        $second = Avatars::urls(1);
        $this->assertNotSame($first['url'], $second['url']);
        $this->assertSame(1, Media::query()->where('collection_name', 'avatar')->count());
        $this->assertFileDoesNotExist($oldPath);
        $this->get('/dashboard', $this->inertia())->assertJsonPath('props.layout.user.avatar_url', $second['url']);
        // Removing it leaves no file, no media and no URL: initials again.
        $current = $this->original(1)->getPath();
        $this->deleteJson('/me/avatar')->assertOk()->assertJsonPath('avatar', null);
        $this->assertSame(0, Media::query()->count());
        $this->assertFileDoesNotExist($current);
        $this->assertNull(Avatars::urls(1));
        $this->get('/dashboard', $this->inertia())->assertJsonPath('props.layout.user.avatar_url', null)->assertJsonPath('props.layout.user.initials', 'AL');
    }

    public function test_type_size_and_pixel_limits_refuse_the_upload(): void
    {
        require dirname(__DIR__).'/database/fixture.php';
        $this->signIn(1);
        $this->postJson('/me/avatar', ['avatar' => UploadedFile::fake()->create('cv.pdf', 20, 'application/pdf')])->assertUnprocessable()->assertJsonValidationErrors('avatar');
        // The type is read from the content, not the name.
        $this->postJson('/me/avatar', ['avatar' => UploadedFile::fake()->createWithContent('photo.png', '<?php echo 1;')])->assertUnprocessable();
        $this->postJson('/me/avatar', ['avatar' => UploadedFile::fake()->image('big.jpg', 200, 200)->size(3000)])->assertUnprocessable()->assertJsonPath('errors.avatar.0', 'The photo may be at most 2048 KB.');
        config(['kit.avatar.max_pixels' => 100]);
        $this->postJson('/me/avatar', ['avatar' => UploadedFile::fake()->image('wide.png', 50, 50)])->assertUnprocessable();
        $this->postJson('/me/avatar', [])->assertUnprocessable();
        $this->assertSame(0, Media::query()->count());
    }

    public function test_exif_and_gps_are_stripped_from_the_stored_original(): void
    {
        require dirname(__DIR__).'/database/fixture.php';
        $this->signIn(1);
        ob_start();
        imagejpeg(imagecreatetruecolor(80, 60));
        $jpeg = (string) ob_get_clean();
        // An APP1 (EXIF) segment carrying a marker string, right after the SOI.
        $payload = "Exif\0\0SECRETGPS-48.8566,2.3522";
        $withExif = substr($jpeg, 0, 2)."\xFF\xE1".pack('n', strlen($payload) + 2).$payload.substr($jpeg, 2);
        $this->assertStringContainsString('SECRETGPS', $withExif);
        $this->postJson('/me/avatar', ['avatar' => UploadedFile::fake()->createWithContent('gps.jpg', $withExif)])->assertCreated();
        $stored = (string) file_get_contents($this->original(1)->getPath());
        $this->assertStringNotContainsString('SECRETGPS', $stored);
        $this->assertStringNotContainsString('Exif', $stored);
        $this->assertSame([60, 60], array_slice(getimagesizefromstring($stored), 0, 2));
    }

    public function test_only_you_change_your_own_avatar(): void
    {
        require dirname(__DIR__).'/database/fixture.php';
        $this->signIn(1);
        $this->postJson('/me/avatar', ['avatar' => UploadedFile::fake()->image('one.png', 100, 100)])->assertCreated();
        $before = Avatars::urls(1);
        $this->postJson('/logout')->assertNoContent();
        $this->signIn(2);
        // There is no id in the URL: user 2 can only ever touch their own, and the policy refuses the other.
        $this->postJson('/me/avatar', ['avatar' => UploadedFile::fake()->image('two.png', 100, 100)])->assertCreated();
        $this->deleteJson('/me/avatar')->assertOk();
        $this->assertSame($before, Avatars::urls(1));
        $this->assertNull(Avatars::urls(2));
        $this->assertTrue(Gate::forUser(User::findOrFail(1))->allows('update', UserAvatar::findOrFail(1)));
        $this->assertTrue(Gate::forUser(User::findOrFail(2))->denies('update', UserAvatar::findOrFail(1)));
        $this->postJson('/logout')->assertNoContent();
        Auth::forgetGuards();
        $this->flushSession();
        $this->postJson('/me/avatar', ['avatar' => UploadedFile::fake()->image('x.png', 10, 10)])->assertUnauthorized();
    }

    public function test_the_profile_page_shows_initials_until_there_is_a_photo_and_never_a_stale_url(): void
    {
        require dirname(__DIR__).'/database/fixture.php';
        $this->signIn(1);
        $this->get('/me/profile', $this->inertia())->assertOk()->assertJsonPath('component', 'Workspace/Profile/Edit/Page')
            ->assertJsonPath('props.avatar', null)->assertJsonPath('props.initials', 'AL')->assertJsonPath('props.store_url', '/me/avatar')
            ->assertJsonPath('props.breadcrumbs.2.title', 'Profile');
        $this->postJson('/me/avatar', ['avatar' => UploadedFile::fake()->image('a.png', 100, 100)])->assertCreated();
        $shown = $this->get('/me/profile', $this->inertia())->json('props.avatar.url');
        $this->assertSame(Avatars::urls(1)['url'], $shown);
        $this->postJson('/me/avatar', ['avatar' => UploadedFile::fake()->image('b.png', 100, 100)])->assertCreated();
        $this->assertNotSame($shown, $this->get('/me/profile', $this->inertia())->json('props.avatar.url'));
        $this->deleteJson('/me/avatar')->assertOk();
        $this->get('/me/profile', $this->inertia())->assertJsonPath('props.avatar', null);
        $this->get('/me', $this->inertia())->assertJsonPath('props.groups.0.sections.0.key', 'profile');
    }
}
