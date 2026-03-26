<?php

namespace App\Http\Controllers;

use App\Events\NewMessageEvent;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MessageController extends Controller
{
    public function __construct(private NotificationService $notifications) {}

    /** GET /messages — chat UI */
    public function index()
    {
        $users = DB::table('users')
            ->where('id', '!=', auth()->id())
            ->orderBy('name')
            ->get(['id', 'name', 'role', 'avatar_path']);

        // Attach unread count per user
        $myId   = auth()->id();
        $unread = DB::table('messages')
            ->where('to_user_id', $myId)
            ->whereNull('read_at')
            ->selectRaw('from_user_id, COUNT(*) as cnt')
            ->groupBy('from_user_id')
            ->pluck('cnt', 'from_user_id');

        // Last message per conversation
        $lastMsg = DB::table('messages')
            ->where(fn($q) => $q->where('from_user_id', $myId)->orWhere('to_user_id', $myId))
            ->orderByDesc('created_at')
            ->get()
            ->groupBy(fn($m) => $m->from_user_id === $myId ? $m->to_user_id : $m->from_user_id)
            ->map->first();

        $users->transform(function ($u) use ($unread, $lastMsg) {
            $u->unread_count = $unread[$u->id] ?? 0;
            $lm = $lastMsg[$u->id] ?? null;
            $u->last_message = $lm ? mb_substr($lm->message, 0, 60) : null;
            $u->last_at      = $lm?->created_at;
            return $u;
        });

        // Sort: users with recent messages first
        $users = $users->sortByDesc('last_at')->values();

        return view('messages.index', compact('users'));
    }

    /** GET /api/messages/{userId} — conversation history */
    public function history(int $userId): JsonResponse
    {
        $myId = auth()->id();

        // Mark received messages as read
        DB::table('messages')
            ->where('from_user_id', $userId)
            ->where('to_user_id', $myId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        // Mark related notifications as read (keeps badge in sync)
        DB::table('notifications')
            ->where('user_id', $myId)
            ->where('type', 'message')
            ->where('is_read', false)
            ->whereRaw("JSON_EXTRACT(data, '$.from_user_id') = ?", [$userId])
            ->update(['is_read' => true]);

        $rows = DB::table('messages as m')
            ->where(function ($q) use ($myId, $userId) {
                $q->where(fn($q2) => $q2->where('m.from_user_id', $myId)->where('m.to_user_id', $userId))
                  ->orWhere(fn($q2) => $q2->where('m.from_user_id', $userId)->where('m.to_user_id', $myId));
            })
            ->orderBy('m.created_at')
            ->limit(100)
            ->get(['m.id', 'm.from_user_id', 'm.to_user_id', 'm.message', 'm.created_at', 'm.read_at']);

        return response()->json(['messages' => $rows]);
    }

    /** POST /api/messages — send message */
    public function send(Request $request): JsonResponse
    {
        $request->validate([
            'to_user_id' => ['required', 'integer'],
            'message'    => ['required', 'string', 'max:2000'],
        ]);

        $myId    = auth()->id();
        $toId    = (int) $request->input('to_user_id');
        $text    = trim($request->input('message'));
        $me      = auth()->user();

        $id = DB::table('messages')->insertGetId([
            'from_user_id' => $myId,
            'to_user_id'   => $toId,
            'message'      => $text,
            'created_at'   => now(),
        ]);

        $payload = [
            'id'           => $id,
            'from_user_id' => $myId,
            'to_user_id'   => $toId,
            'message'      => $text,
            'created_at'   => now()->toISOString(),
            'sender_name'  => $me->name,
        ];

        // Broadcast via WebSocket (real-time)
        broadcast(new NewMessageEvent($payload))->toOthers();

        // In-app notification to recipient
        $this->notifications->send(
            $toId,
            '💬 ' . $me->name,
            mb_substr($text, 0, 100),
            'message',
            ['from_user_id' => $myId]
        );

        return response()->json($payload);
    }

    /** GET /api/messages/unread — total unread count */
    public function unread(): JsonResponse
    {
        $cnt = DB::table('messages')
            ->where('to_user_id', auth()->id())
            ->whereNull('read_at')
            ->count();
        return response()->json(['unread_count' => $cnt]);
    }
}
