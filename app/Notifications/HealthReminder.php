<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class HealthReminder extends Notification
{
    /**
     * How long the markers below outlive the reminder they describe. A swept
     * window is hours wide, so thirty days is generous, and the notification row
     * itself is the durable record once a marker ages out.
     */
    public const MARKER_TTL_DAYS = 30;

    public function __construct(public array $payload) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return $this->payload;
    }

    public function toDatabase(object $notifiable): array
    {
        return $this->payload;
    }

    /**
     * The note that a reminder with this key has already been delivered. The
     * notification row cannot be the only record because throwing a reminder away
     * deletes it, and a dismissed reminder must not come back on the next sweep.
     */
    public static function claimMarker(string $key): string
    {
        return 'medibyte:reminder-sent:'.$key;
    }

    /** The note that a reminder was thrown away without being opened. */
    public static function dismissalMarker(string $key): string
    {
        return 'medibyte:reminder-dismissed:'.$key;
    }

    public static function markerTtl(): \DateTimeInterface
    {
        return now()->addDays(self::MARKER_TTL_DAYS);
    }
}
