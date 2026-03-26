<?php

namespace App\Console\Commands;

use App\Services\PushService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PushTest extends Command
{
    protected $signature = 'push:test {--user= : User ID (default: first user with push_token)} {--token= : FCM device token (bypasses DB lookup)}';
    protected $description = 'Send a test FCM push notification';

    public function handle(PushService $push): int
    {
        // Direct token mode — bypasses DB
        if ($directToken = $this->option('token')) {
            $this->line('Using token from --token option');
            $deviceToken = $directToken;
            $label       = 'manual';
        } else {
            $userId = $this->option('user');
            $user   = $userId
                ? DB::table('users')->where('id', $userId)->first()
                : DB::table('users')->whereNotNull('push_token')->where('push_token', '!=', '')->first();

            if (!$user) {
                $this->error('No user with push_token found.');
                $this->line('');
                $this->line('Options:');
                $this->line('  1. Open the site in a browser → grant notification permission → token saves automatically');
                $this->line('  2. Pass a token directly:  php artisan push:test --token=<FCM_TOKEN>');
                $this->line('  3. Set manually:           php artisan tinker');
                $this->line("     DB::table('users')->where('id',1)->update(['push_token' => '<token>']);");
                return self::FAILURE;
            }

            if (empty($user->push_token)) {
                $this->error("User {$user->name} (id={$user->id}) has no push_token.");
                $this->line('Open the site → grant notification permission, or use --token=<FCM_TOKEN>');
                return self::FAILURE;
            }

            $deviceToken = $user->push_token;
            $label       = "{$user->name} (id={$user->id})";
        }

        $this->line("Sending push to <info>{$label}</info>");
        $this->line('Token: ' . substr($deviceToken, 0, 30) . '…');

        $result = $push->send(
            deviceToken: $deviceToken,
            title:       '🔔 Test Push',
            body:        'SolarGlass push-сповіщення працює!',
            url:         '/messages',
            type:        'system',
        );

        if ($result['success']) {
            $this->info('✅ Push sent successfully!');
            return self::SUCCESS;
        }

        $this->error('❌ Push failed: ' . ($result['error'] ?? 'unknown error'));
        if (isset($result['status'])) {
            $this->line('HTTP status: ' . $result['status']);
        }
        return self::FAILURE;
    }
}
