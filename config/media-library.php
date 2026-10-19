<?php

// Media is stored on the private `s3` disk by default. The package merges its own defaults for every other key.
return [
    'disk_name' => env('MEDIA_DISK', 's3'),
];
