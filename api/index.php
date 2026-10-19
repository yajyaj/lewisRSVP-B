<?php

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