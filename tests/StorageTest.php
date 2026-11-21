<?php

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

    putenv('MEDIA_DISK=s3');
    $disks = (require config_path('filesystems.php'))['disks'];
    putenv('MEDIA_DISK');
    expect($disks['media']['driver'])->toBe('s3');
    expect($disks['media']['bucket'])->toBe('synthetic-bucket');
    expect($disks['media']['root'])->toBe('media');
    expect($disks['s3'])->not->toHaveKey('root', 'the s3 disk stays the whole bucket for the app\'s other files');
    if (Template::isTemplate()) {
        $this->assertStringContainsString('MEDIA_DISK=local', (string) file_get_contents(dirname(__DIR__).'/.env.example'));
    }
});
