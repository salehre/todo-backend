<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// این فایل قبلاً بدون هیچ محافظتی برای همه در دسترس بود؛ الان پشت همون
// DEPLOY_SECRET قفل شده که /GInstall هم استفاده می‌کنه.
$secret = $_GET['secret'] ?? '';
if (!$secret || !hash_equals((string) env('DEPLOY_SECRET', ''), (string) $secret)) {
    http_response_code(403);
    exit('Forbidden');
}

echo '<pre>';
