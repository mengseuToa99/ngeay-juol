<?php

namespace App\Support\Notifications;

use App\Models\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;

class NotificationDeduplicator
{
    public static function sendOnce(User $user, Notification $notification, array $keys): bool
    {
        $exists = DB::table('notifications')
            ->where('type', $notification::class)
            ->where('notifiable_type', $user->getMorphClass())
            ->where('notifiable_id', $user->getKey())
            ->where(function ($query) use ($keys) {
                foreach ($keys as $key => $value) {
                    $query->where('data->'.$key, $value);
                }
            })
            ->exists();

        if ($exists) {
            return false;
        }

        $user->notify($notification);

        return true;
    }
}
