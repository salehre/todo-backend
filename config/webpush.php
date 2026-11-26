<?php

return [
    // کلیدها رو با `php artisan webpush:vapid` بساز و توی .env بذار
    'public_key'  => env('VAPID_PUBLIC_KEY'),
    'private_key' => env('VAPID_PRIVATE_KEY'),

    // باید mailto: یا آدرس https باشه
    'subject'     => env('VAPID_SUBJECT', 'mailto:admin@example.com'),
];
