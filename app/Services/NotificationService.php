<?php

namespace App\Services;

use App\Events\NewNotificationEvent;
use App\Services\PushService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * Send an in-app notification (+ push if user has token).
     */
    public function send(
        int    $userId,
        string $title,
        string $message,
        string $type = 'system',
        array  $data = []
    ): int {
        $id = DB::table('notifications')->insertGetId([
            'user_id'    => $userId,
            'type'       => $type,
            'title'      => $title,
            'message'    => $message,
            'data'       => $data ? json_encode($data) : null,
            'is_read'    => false,
            'created_at' => now(),
        ]);

        $payload = [
            'id'         => $id,
            'user_id'    => $userId,
            'type'       => $type,
            'title'      => $title,
            'message'    => $message,
            'data'       => $data ?: null,
            'is_read'    => false,
            'created_at' => now()->toISOString(),
        ];

        // Broadcast via WebSocket (real-time badge + dropdown update)
        try {
            broadcast(new NewNotificationEvent($payload));
        } catch (\Throwable $e) {
            Log::debug('NotificationService: broadcast failed', ['err' => $e->getMessage()]);
        }

        // Fire push if user has a token
        $pushToken = DB::table('users')->where('id', $userId)->value('push_token');
        if ($pushToken) {
            $url   = ($type === 'message') ? '/messages' : '/';
            $badge = DB::table('notifications')
                ->where('user_id', $userId)
                ->where('is_read', false)
                ->count();
            (new PushService())->send($pushToken, $title, $message, $url, $type, array_merge($data, ['badge' => $badge, 'notification_id' => $id]));
        }

        return $id;
    }

    /**
     * Send notification to all users with a given role.
     */
    public function sendToRole(string $role, string $title, string $message, string $type = 'system', array $data = []): void
    {
        $users = DB::table('users')->where('role', $role)->pluck('id');
        foreach ($users as $uid) {
            $this->send((int)$uid, $title, $message, $type, $data);
        }
    }

    /**
     * Mark one notification as read.
     */
    public function markAsRead(int $notificationId, int $userId): bool
    {
        return (bool) DB::table('notifications')
            ->where('id', $notificationId)
            ->where('user_id', $userId)
            ->update(['is_read' => true]);
    }

    /**
     * Mark all notifications as read for a user.
     */
    public function markAllRead(int $userId): void
    {
        DB::table('notifications')
            ->where('user_id', $userId)
            ->where('is_read', false)
            ->update(['is_read' => true]);
    }

    /**
     * Count unread notifications for a user.
     */
    public function unreadCount(int $userId): int
    {
        return (int) DB::table('notifications')
            ->where('user_id', $userId)
            ->where('is_read', false)
            ->count();
    }

    /**
     * Send Firebase Cloud Messaging push notification.
     * Requires FIREBASE_SERVER_KEY in .env
     */
    public function sendPush(
        string $token,
        string $title,
        string $body,
        string $type = 'system',
        array  $data = [],
        int    $notificationId = 0
    ): void {
        $key = config('services.firebase.server_key');
        if (!$key) return;

        $url = match($type) {
            'message' => '/messages',
            default   => '/',
        };

        try {
            Http::timeout(5)
                ->withHeaders(['Authorization' => "key={$key}"])
                ->post('https://fcm.googleapis.com/fcm/send', [
                    'to'       => $token,
                    'priority' => 'high',
                    'notification' => [
                        'title' => $title,
                        'body'  => $body,
                        'icon'  => '/img/logo.png',
                        'badge' => '/img/logo.png',
                        'sound' => 'default',
                        'click_action' => $url,
                    ],
                    'data' => array_merge([
                        'url'             => $url,
                        'type'            => $type,
                        'notification_id' => (string) $notificationId,
                    ], array_map('strval', $data)),
                ]);
        } catch (\Throwable $e) {
            Log::warning('NotificationService: FCM push failed', ['err' => $e->getMessage()]);
        }
    }
}
