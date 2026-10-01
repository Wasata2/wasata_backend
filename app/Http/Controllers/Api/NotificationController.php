<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    // GET /api/notifications — ?unread_only=1
    public function index(Request $request)
    {
        $query = Notification::where('user_id', $request->user()->id)->latest();

        if ($request->boolean('unread_only')) {
            $query->whereNull('read_at');
        }

        return response()->json(['notifications' => $query->get()]);
    }

    // GET /api/notifications/unread-count — for the bell badge
    public function unreadCount(Request $request)
    {
        $count = Notification::where('user_id', $request->user()->id)->whereNull('read_at')->count();

        return response()->json(['unread_count' => $count]);
    }

    // PATCH /api/notifications/{notification}/read
    public function markRead(Request $request, Notification $notification)
    {
        abort_unless($notification->user_id === $request->user()->id, 403, 'This is not your notification.');

        if (! $notification->read_at) {
            $notification->update(['read_at' => now()]);
        }

        return response()->json(['message' => 'Marked as read.', 'notification' => $notification]);
    }

    // PATCH /api/notifications/read-all
    public function markAllRead(Request $request)
    {
        Notification::where('user_id', $request->user()->id)->whereNull('read_at')->update(['read_at' => now()]);

        return response()->json(['message' => 'All notifications marked as read.']);
    }
}
