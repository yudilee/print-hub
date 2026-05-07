<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    /**
     * Display a paginated list of notifications for the current user.
     */
    public function index(Request $request): View
    {
        $query = Notification::where('user_id', auth()->id())
            ->orWhereNull('user_id')
            ->latest();

        // Optional type filter
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        // Optional unread-only filter
        if ($request->boolean('unread')) {
            $query->unread();
        }

        $notifications = $query->paginate(30);

        return view('admin.notifications.index', compact('notifications'));
    }

    /**
     * Mark a single notification as read.
     */
    public function markAsRead(Notification $notification): RedirectResponse
    {
        // Ensure the notification belongs to the current user or is global
        if ($notification->user_id !== null && $notification->user_id !== auth()->id()) {
            abort(403);
        }

        $notification->markAsRead();

        return redirect()->back()->with('success', 'Notification marked as read.');
    }

    /**
     * Mark all notifications as read for the current user.
     */
    public function markAllAsRead(): RedirectResponse
    {
        Notification::where(function ($q) {
            $q->where('user_id', auth()->id())
              ->orWhereNull('user_id');
        })->unread()->update(['read_at' => now()]);

        return redirect()->back()->with('success', 'All notifications marked as read.');
    }

    /**
     * Return the unread notification count as JSON (for the badge).
     */
    public function unreadCount(): JsonResponse
    {
        $count = Notification::where(function ($q) {
            $q->where('user_id', auth()->id())
              ->orWhereNull('user_id');
        })->unread()->count();

        return response()->json(['count' => $count]);
    }
}
