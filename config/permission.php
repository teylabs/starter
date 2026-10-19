<?php

$defaults = require dirname(__DIR__).'/vendor/spatie/laravel-permission/config/permission.php';
$defaults['teams'] = true;
$defaults['column_names']['team_foreign_key'] = 'workspace_id';
$defaults['cache']['store'] = 'array';

return $defaults;
