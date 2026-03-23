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

    /** POST /api/push-token  { token } — save FCM token */
    public function savePushToken(Request $request): JsonResponse
    {
        $request->validate(['token' => ['required', 'string', 'max:512']]);
        DB::table('users')
            ->where('id', auth()->id())
            ->update(['push_token' => $request->input('token')]);
        return response()->json(['ok' => true]);
    }
}
