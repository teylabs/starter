<?php

// Media (avatars) is stored on the PRIVATE media disk: `local` (storage/app/private) in development, and in production MEDIA_DISK=s3 with the bucket's
// AWS_* variables. Nothing is served from a public path: avatars go out through the app's cached /avatars/{token} route. The package merges its own
// defaults for every other key.
return [
    'disk_name' => env('MEDIA_DISK', 'local'),
];
