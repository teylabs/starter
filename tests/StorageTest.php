<?php

use Illuminate\Filesystem\AwsS3V3Adapter;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter as FlysystemAdapter;
use Tests\TestCase;

require dirname(__DIR__).'/vendor/autoload.php';
require_once __DIR__.'/TestCase.php';

/** The private s3 disk builds on the S3 adapter from Laravel Cloud's bucket variables, and media is stored on it. Nothing is sent. */
final class StorageTest extends TestCase
{
    private const BUCKET = ['AWS_ACCESS_KEY_ID' => 'synthetic-key', 'AWS_SECRET_ACCESS_KEY' => 'synthetic-secret', 'AWS_DEFAULT_REGION' => 'auto', 'AWS_BUCKET' => 'synthetic-bucket', 'AWS_ENDPOINT' => 'https://synthetic.r2.example.test', 'AWS_URL' => 'https://synthetic.r2.example.test/synthetic-bucket', 'AWS_USE_PATH_STYLE_ENDPOINT' => 'true'];

    public function createApplication()
    {
        foreach (self::BUCKET as $key => $value) {
            putenv($key.'='.$value);
            $_ENV[$key] = $_SERVER[$key] = $value;
        }

        return require __DIR__.'/application.php';
    }

    protected function tearDown(): void
    {
        foreach (array_keys(self::BUCKET) as $key) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);
        }
        parent::tearDown();
    }

    public function test_the_private_s3_disk_builds_on_the_s3_adapter(): void
    {
        $disk = Storage::disk('s3');
        $this->assertInstanceOf(AwsS3V3Adapter::class, $disk);
        $this->assertInstanceOf(FlysystemAdapter::class, $disk->getAdapter());
        $client = $disk->getClient();
        $this->assertSame('synthetic-bucket', $disk->getConfig()['bucket']);
        $this->assertSame('private', $disk->getConfig()['visibility']);
        $this->assertTrue($client->getConfig('use_path_style_endpoint'));
        $this->assertSame('auto', $client->getRegion());
        $this->assertSame('https://synthetic.r2.example.test', (string) $client->getEndpoint());
        // Path-style: the bucket is the first path segment, not a subdomain.
        $this->assertStringStartsWith('https://synthetic.r2.example.test/synthetic-bucket/media/1.txt?', $disk->temporaryUrl('media/1.txt', now()->addMinutes(5)));
    }

    public function test_media_is_on_the_private_local_disk_by_default_and_a_private_bucket_when_the_disk_is_s3(): void
    {
        // Development stores avatars on the private `local` disk; production sets MEDIA_DISK=s3 and the bucket's variables (see docs/deploy.md).
        $this->assertSame('local', config('media-library.disk_name'));
        $this->assertSame('local', config('filesystems.disks.local.driver'));
        $this->assertSame('private', config('filesystems.disks.s3.visibility'));
        $this->assertStringContainsString('MEDIA_DISK=local', (string) file_get_contents(dirname(__DIR__).'/.env.example'));
    }
}
