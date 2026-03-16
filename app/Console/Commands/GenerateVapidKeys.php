<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

class GenerateVapidKeys extends Command
{
    protected $signature = 'push:generate-vapid';

    protected $description = 'Generate VAPID keys for Web Push notifications';

    public function handle(): void
    {
        $keys = VAPID::createVapidKeys();

        $this->info('VAPID keys generated. Add these to your .env:');
        $this->line('');
        $this->line('VAPID_PUBLIC_KEY='.$keys['publicKey']);
        $this->line('VAPID_PRIVATE_KEY='.$keys['privateKey']);
        $this->line('VITE_VAPID_PUBLIC_KEY='.$keys['publicKey']);
        $this->line('');
        $this->warn('Also run: php artisan config:clear after updating .env');
    }
}
