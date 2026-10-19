<?php

$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['PHP_SELF'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = __DIR__.'/../public/index.php';

$directories = [
    '/tmp/views',
    '/tmp/laravel/framework/cache/data',
    '/tmp/laravel/framework/sessions',
    '/tmp/laravel/framework/views',
    '/tmp/laravel/logs',
];

foreach ($directories as $directory) {
    if (! is_dir($directory)) {
        mkdir($directory, 0775, true);
    }
}

require __DIR__.'/../public/index.php';
