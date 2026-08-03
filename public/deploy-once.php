<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo '<pre>';
\Illuminate\Support\Facades\Artisan::call('config:clear');
echo \Illuminate\Support\Facades\Artisan::output();
\Illuminate\Support\Facades\Artisan::call('config:cache');
echo \Illuminate\Support\Facades\Artisan::output();
echo '</pre>';
