<?php

// The private `s3` disk reads the bucket variables Laravel Cloud injects for an attached bucket (Cloudflare R2:
// region `auto`, path-style endpoint). Files are private; hand out temporary URLs, never public ones. The `s3` disk is the whole bucket, for the
// app's own files (exports, imports, generated files); Media Library writes only through the `media` disk, the same bucket under `media/` when
// MEDIA_DISK names the bucket's disk, and storage/app/private/media in development (MEDIA_DISK=local). The media disk is scoped over the disk
// MEDIA_DISK names rather than a copy of it, so it uses whatever bucket and credentials that disk holds when it is built. Laravel Cloud injects an
// attached bucket as the disk name you chose when attaching it (LARAVEL_CLOUD_DISK_CONFIG) and doesn't fill `s3`, so with MEDIA_DISK unset the media
// disk scopes over the attached default bucket (else the first one); MEDIA_DISK still wins. R2 disks get request checksums only when required (see
// AppServiceProvider): AWS SDK 3.337+ checksums every request by default, which R2 rejects.
$cloudDisks = array_values(array_filter((array) json_decode((string) env('LARAVEL_CLOUD_DISK_CONFIG', '[]'), true), fn ($disk) => is_array($disk) && isset($disk['disk']) && empty($disk['scoped_disk'])));
$mediaDisk = env('MEDIA_DISK') ?: (collect($cloudDisks)->firstWhere('is_default', true)['disk'] ?? $cloudDisks[0]['disk'] ?? 'local');
$s3 = [
    'driver' => 's3',
    'key' => env('AWS_ACCESS_KEY_ID'),
    'secret' => env('AWS_SECRET_ACCESS_KEY'),
    'region' => env('AWS_DEFAULT_REGION', 'auto'),
    'bucket' => env('AWS_BUCKET'),
    'url' => env('AWS_URL'),
    'endpoint' => env('AWS_ENDPOINT'),
    'use_path_style_endpoint' => (bool) env('AWS_USE_PATH_STYLE_ENDPOINT', true),
    'visibility' => 'private',
    'throw' => true,
    'report' => false,
    'request_checksum_calculation' => 'when_required',
    'response_checksum_validation' => 'when_required',
];

return [
    'default' => env('FILESYSTEM_DISK', 'local'),
    'disks' => [
        'local' => ['driver' => 'local', 'root' => storage_path('app/private'), 'serve' => true, 'throw' => false, 'report' => false],
        'public' => ['driver' => 'local', 'root' => storage_path('app/public'), 'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage', 'visibility' => 'public', 'throw' => false, 'report' => false],
        's3' => $s3,
        'media' => $mediaDisk !== 'local'
            ? ['driver' => 'scoped', 'disk' => $mediaDisk, 'prefix' => 'media']
            : ['driver' => 'local', 'root' => storage_path('app/private/media'), 'visibility' => 'private', 'throw' => false, 'report' => false],
    ],
    'links' => [public_path('storage') => storage_path('app/public')],
];
