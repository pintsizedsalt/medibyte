<?php

namespace App\Http\Controllers;

use App\Console\Commands\SendReminders;
use App\Notifications\HealthReminder;
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

    public function destroy(Request $request, $id)
    {
        $notification = $request->user()->notifications()->where('id', $id)->firstOrFail();
        $data = $notification->data;

        // Throwing a reminder away deletes its row, and the row is what says the
        // reminder already happened. Keep the answer in the cache so a dismissed
        // reminder neither returns on the next sweep nor invites a follow-up — a
        // follow-up carries the key of the schedule it repeats, so it answers for it.
        foreach (array_filter([$data['dedupe_key'] ?? null, $data['anchor_key'] ?? null]) as $key) {
            Cache::put(HealthReminder::dismissalMarker($key), true, HealthReminder::markerTtl());
        }

        $notification->delete();

        return response()->json(['message' => 'Dismissed']);
    }
}
