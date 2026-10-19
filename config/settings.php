<?php

// The settings cache stores serialize()d settings objects and unserializes them unrestricted, so it stays off (CacheGuardTest).

return ['settings' => [], 'auto_discover_settings' => [], 'cache' => ['enabled' => false, 'store' => 'array', 'prefix' => 'settings', 'ttl' => 60]];
