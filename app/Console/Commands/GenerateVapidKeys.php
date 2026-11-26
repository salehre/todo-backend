<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlab\WebPush\VAPID;

class GenerateVapidKeys extends Command
{
    protected $signature = 'webpush:vapid';
    protected $description = 'ساخت کلیدهای VAPID برای نوتیفیکیشن (Web Push)';

    public function handle(): int
    {
        $keys = VAPID::createVapidKeys();

        $this->info('این سه خط رو توی .env بک‌اند بذار (فقط یک بار بساز؛ با عوض شدنشون همه‌ی اشتراک‌های قبلی از کار می‌افتن):');
        $this->newLine();
        $this->line('VAPID_PUBLIC_KEY=' . $keys['publicKey']);
        $this->line('VAPID_PRIVATE_KEY=' . $keys['privateKey']);
        $this->line('VAPID_SUBJECT=mailto:you@example.com');

        return self::SUCCESS;
    }
}
