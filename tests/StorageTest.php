<?php

use App\Providers\AppServiceProvider;
use Illuminate\Filesystem\AwsS3V3Adapter;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter as FlysystemAdapter;
use Tests\Concerns\Template;
use Tests\StorageCase;

require_once __DIR__.'/Concerns/Template.php';

afterEach(function () {
    foreach (array_keys(StorageCase::BUCKET) as $key) {
        putenv($key);
        unset($_ENV[$key], $_SERVER[$key]);
    }
});
test('the private s3 disk builds on the s3 adapter', function () {
    $disk = Storage::disk('s3');
    expect($disk)->toBeInstanceOf(AwsS3V3Adapter::class);
    expect($disk->getAdapter())->toBeInstanceOf(FlysystemAdapter::class);
    $client = $disk->getClient();
    expect($disk->getConfig()['bucket'])->toBe('synthetic-bucket');
    expect($disk->getConfig()['visibility'])->toBe('private');
    expect($client->getConfig('use_path_style_endpoint'))->toBeTrue();
    expect($client->getRegion())->toBe('auto');
    expect((string) $client->getEndpoint())->toBe('https://synthetic.r2.example.test');

    // Path-style: the bucket is the first path segment, not a subdomain.
    expect($disk->temporaryUrl('media/1.txt', now()->addMinutes(5)))->toStartWith('https://synthetic.r2.example.test/synthetic-bucket/media/1.txt?');
});
test('media is on the private media disk: storage/app/private/media by default, the bucket under media/ when the disk is s3', function () {
    // Development stores avatars under storage/app/private/media; production sets MEDIA_DISK=s3 and the bucket's variables (see docs/deploy.md).
    expect(config('media-library.disk_name'))->toBe('media');
    expect(config('filesystems.disks.media.driver'))->toBe('local');
    expect(config('filesystems.disks.media.root'))->toBe(storage_path('app/private/media'));
    expect(config('filesystems.disks.s3.visibility'))->toBe('private');

    // env() reads $_ENV and $_SERVER before getenv(), and a checkout's own .env has already filled them, so set all three.
    $before = [getenv('MEDIA_DISK'), $_ENV['MEDIA_DISK'] ?? null, $_SERVER['MEDIA_DISK'] ?? null];
    try {
        putenv('MEDIA_DISK=private');
        $_ENV['MEDIA_DISK'] = $_SERVER['MEDIA_DISK'] = 'private';
        $disks = (require config_path('filesystems.php'))['disks'];
    } finally {
        putenv($before[0] === false ? 'MEDIA_DISK' : 'MEDIA_DISK='.$before[0]);
        unset($_ENV['MEDIA_DISK'], $_SERVER['MEDIA_DISK']);
        if ($before[1] !== null) {
            $_ENV['MEDIA_DISK'] = $before[1];
        }
        if ($before[2] !== null) {
            $_SERVER['MEDIA_DISK'] = $before[2];
        }
    }
    // On Laravel Cloud MEDIA_DISK is the attached bucket's disk name (here 'private'), not s3.
    expect($disks['media'])->toBe(['driver' => 'scoped', 'disk' => 'private', 'prefix' => 'media']);
    expect($disks['s3'])->not->toHaveKey('root', 'the s3 disk stays the whole bucket for the app\'s other files');
    if (Template::isTemplate()) {
        $this->assertStringContainsString('MEDIA_DISK=local', (string) file_get_contents(dirname(__DIR__).'/.env.example'));
    }
});

test('the media disk uses the bucket Laravel Cloud injects under the disk name it was given', function () {
    // Cloud adds the attached bucket as its own disk (LARAVEL_CLOUD_DISK_CONFIG) under the chosen name, at runtime; s3 keeps a null bucket.
    config([
        'filesystems.disks.private' => [...config('filesystems.disks.s3'), 'bucket' => 'injected-bucket'],
        'filesystems.disks.s3.bucket' => null,
        'filesystems.disks.media' => ['driver' => 'scoped', 'disk' => 'private', 'prefix' => 'media'],
    ]);
    Storage::forgetDisk(['s3', 'private', 'media']);

    expect(Storage::disk('media')->temporaryUrl('1.txt', now()->addMinutes(5)))
        ->toStartWith('https://synthetic.r2.example.test/injected-bucket/media/1.txt?');
});
test('on Laravel Cloud with MEDIA_DISK unset, media scopes over the attached default bucket; MEDIA_DISK still wins', function () {
    $disksWith = function (array $env): array {
        $keys = ['MEDIA_DISK', 'LARAVEL_CLOUD_DISK_CONFIG'];
        $before = [];
        foreach ($keys as $key) {
            $before[$key] = [getenv($key), $_ENV[$key] ?? null, $_SERVER[$key] ?? null];
        }
        try {
            foreach ($keys as $key) {
                putenv($key);
                unset($_ENV[$key], $_SERVER[$key]);
                if (isset($env[$key])) {
                    putenv($key.'='.$env[$key]);
                    $_ENV[$key] = $_SERVER[$key] = $env[$key];
                }
            }

            return (require config_path('filesystems.php'))['disks'];
        } finally {
            foreach ($before as $key => [$process, $envValue, $serverValue]) {
                putenv($process === false ? $key : $key.'='.$process);
                unset($_ENV[$key], $_SERVER[$key]);
                if ($envValue !== null) {
                    $_ENV[$key] = $envValue;
                }
                if ($serverValue !== null) {
                    $_SERVER[$key] = $serverValue;
                }
            }
        }
    };
    // Cloud's shape (Illuminate\Foundation\CloudBootstrapper): the default bucket is listed second, after a scoped disk and a non-default bucket.
    $cloud = json_encode([
        ['disk' => 'exports', 'scoped_disk' => 'archive', 'prefix' => 'exports'],
        ['disk' => 'archive', 'bucket' => 'a', 'is_default' => false],
        ['disk' => 'private', 'bucket' => 'b', 'is_default' => true],
    ]);

    expect($disksWith(['LARAVEL_CLOUD_DISK_CONFIG' => $cloud])['media'])->toBe(['driver' => 'scoped', 'disk' => 'private', 'prefix' => 'media']);
    expect($disksWith(['LARAVEL_CLOUD_DISK_CONFIG' => json_encode([['disk' => 'only', 'bucket' => 'c']])])['media']['disk'])->toBe('only');
    expect($disksWith(['LARAVEL_CLOUD_DISK_CONFIG' => $cloud, 'MEDIA_DISK' => 's3'])['media']['disk'])->toBe('s3');
    expect($disksWith([])['media']['driver'])->toBe('local');
});

test('R2 disks, including one Laravel Cloud adds after the config loads, checksum requests only when required', function () {
    config(['filesystems.disks.attached' => ['driver' => 's3', 'key' => 'k', 'secret' => 's', 'region' => 'auto', 'bucket' => 'b', 'endpoint' => 'https://synthetic.r2.cloudflarestorage.com']]);
    config(['filesystems.disks.aws' => ['driver' => 's3', 'key' => 'k', 'secret' => 's', 'region' => 'us-east-1', 'bucket' => 'b']]);
    (new AppServiceProvider(app()))->boot();

    $client = Storage::disk('attached')->getClient();
    expect($client->getConfig('request_checksum_calculation'))->toBe('when_required');
    expect($client->getConfig('response_checksum_validation'))->toBe('when_required');
    expect(config('filesystems.disks.aws'))->not->toHaveKey('request_checksum_calculation', 'a plain AWS S3 disk keeps the SDK default');
    expect(config('filesystems.disks.s3.request_checksum_calculation'))->toBe('when_required');
});
