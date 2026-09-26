<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NotificationWebController extends Controller
{
    /**
     * Mark a notification as read.
     */
    public function markAsRead(Request $request, string $id): RedirectResponse
    {
        $notification = $request->user()->notifications()->where('id', $id)->first();
        if ($notification) {
            $notification->markAsRead();
        }

        return redirect()->back();
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return redirect()->back();
    }
}
