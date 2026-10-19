<?php

use Illuminate\Filesystem\AwsS3V3Adapter;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter as FlysystemAdapter;

require dirname(__DIR__).'/vendor/autoload.php';

/** The private s3 disk builds on the S3 adapter from Laravel Cloud's bucket variables, and media is stored on it. Nothing is sent. */
final class StorageTest extends TestCase
{
    private const BUCKET = ['AWS_ACCESS_KEY_ID' => 'synthetic-key', 'AWS_SECRET_ACCESS_KEY' => 'synthetic-secret', 'AWS_DEFAULT_REGION' => 'auto', 'AWS_BUCKET' => 'synthetic-bucket', 'AWS_ENDPOINT' => 'https://synthetic.r2.example.test', 'AWS_URL' => 'https://synthetic.r2.example.test/synthetic-bucket', 'AWS_USE_PATH_STYLE_ENDPOINT' => 'true'];

    public function createApplication()
    {
        require __DIR__.'/environment.php';
        foreach (self::BUCKET as $key => $value) {
            putenv($key.'='.$value);
            $_ENV[$key] = $_SERVER[$key] = $value;
        }

        return require dirname(__DIR__).'/bootstrap/app.php';
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

    public function test_media_is_stored_on_the_private_disk(): void
    {
        $this->assertSame('s3', config('media-library.disk_name'));
        $this->assertSame('private', config('filesystems.disks.'.config('media-library.disk_name').'.visibility'));
    }
}
