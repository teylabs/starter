<?php

// Media (avatars) is stored on the local `public` disk by default; set MEDIA_DISK=s3 (with the bucket's AWS_* variables) in production.
// The package merges its own defaults for every other key.
return [
    'disk_name' => env('MEDIA_DISK', 'public'),
];
