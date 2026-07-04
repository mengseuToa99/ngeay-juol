<?php

namespace App\Support\Notifications;

use App\Models\User;
use Illuminate\Support\Collection;

class NotificationRecipients
{
    /** @return Collection<int, User> */
    public static function landlordOperators(int $landlordId, ?int $exceptUserId = null): Collection
    {
        return User::query()
            ->where(function ($query) use ($landlordId) {
                $query->whereKey($landlordId)
                    ->orWhere('manages_landlord_id', $landlordId);
            })
            ->get()
            ->filter(fn (User $user) => $user->hasAnyRole(['landlord', 'landlord_manager']))
            ->reject(fn (User $user) => $exceptUserId !== null && (int) $user->id === $exceptUserId)
            ->values();
    }
}
