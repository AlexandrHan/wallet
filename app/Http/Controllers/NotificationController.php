<?php

namespace App\Http\Controllers;

use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NotificationController extends Controller
{
    public function __construct(private NotificationService $svc) {}

    /** GET /api/notifications — list + unread count */
    public function index(Request $request): JsonResponse
    {
        $user  = auth()->user();
        $limit = min((int) $request->input('limit', 30), 100);

        $rows = DB::table('notifications')
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get(['id', 'type', 'title', 'message', 'data', 'is_read', 'created_at']);

        $rows->transform(function ($n) {
            $n->data = $n->data ? json_decode($n->data, true) : null;
            return $n;
        });

        return response()->json([
            'notifications' => $rows,
            'unread_count'  => $this->svc->unreadCount($user->id),
        ]);
    }

    /** GET /api/notifications/count */
    public function count(): JsonResponse
    {
        return response()->json([
            'unread_count' => $this->svc->unreadCount(auth()->id()),
        ]);
    }

    /** POST /api/notifications/read  { id? } — read one or all */
    public function markRead(Request $request): JsonResponse
    {
        $user = auth()->user();
        $id   = $request->input('id');

        if ($id) {
            $this->svc->markAsRead((int)$id, $user->id);
        } else {
            $this->svc->markAllRead($user->id);
        }

        return response()->json(['unread_count' => $this->svc->unreadCount($user->id)]);
    }

    /** POST /api/push-token  { token, device_type? } — save FCM token */
    public function savePushToken(Request $request): JsonResponse
    {
        $request->validate([
            'token'       => ['required', 'string', 'max:4096'],
            'device_type' => ['nullable', 'string', 'max:64'],
        ]);

        $userId     = auth()->id();
        $token      = $request->input('token');
        $deviceType = $request->input('device_type');

        // Upsert into user_push_tokens (no duplicates by token unique index)
        $exists = DB::table('user_push_tokens')->where('token', $token)->exists();
        if (!$exists) {
            DB::table('user_push_tokens')->insert([
                'user_id'     => $userId,
                'token'       => $token,
                'device_type' => $deviceType,
                'created_at'  => now()->toDateTimeString(),
            ]);
        }

        // Keep users.push_token in sync for backward compatibility
        DB::table('users')->where('id', $userId)->update(['push_token' => $token]);

        return response()->json(['ok' => true]);
    }
}
