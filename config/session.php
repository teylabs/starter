<?php

return ['driver' => env('SESSION_DRIVER', 'file'), 'files' => storage_path('framework/sessions'), 'lifetime' => 120, 'expire_on_close' => false, 'encrypt' => false, 'lottery' => [2, 100], 'cookie' => env('SESSION_COOKIE', 'workspace_starter_session'), 'path' => '/', 'domain' => env('SESSION_DOMAIN'), 'secure' => (bool) env('SESSION_SECURE_COOKIE', true), 'http_only' => true, 'same_site' => 'lax'];
