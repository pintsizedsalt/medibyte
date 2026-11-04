<?php

namespace App\Http\Controllers;

use App\Console\Commands\SendReminders;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->reminders_enabled && Cache::add('medibyte:reminder-sweep:user:'.$user->id, true, 300)) {
            try {
                app(SendReminders::class)->sweepUser($user);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $user->notifications()->latest()->limit(50)->get();
    }

    public function markRead(Request $request, $id)
    {
        $notification = $request->user()->notifications()->where('id', $id)->firstOrFail();
        $notification->markAsRead();

        return response()->json(['message' => 'Read']);
    }

    public function markAllRead(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->json(['message' => 'All read']);
    }
}
