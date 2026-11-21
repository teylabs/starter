<?php

// Media (avatars) is stored on the PRIVATE `media` disk (config/filesystems.php): storage/app/private/media in development, and in production
// (MEDIA_DISK=s3) the app's one bucket under `media/`, leaving the rest of the bucket to the app. Nothing is served from a public path: avatars go
// out through the app's cached /avatars/{token} route. The package merges its own defaults for every other key.
return [
    'disk_name' => 'media',
];
