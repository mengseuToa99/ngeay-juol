<?php

namespace App\Models;

use App\Notifications\MaintenanceMessagePostedNotification;
use App\Support\Notifications\NotificationRecipients;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceMessage extends Model
{
    protected $fillable = [
        'request_id',
        'sender_id',
        'body',
    ];

    protected static function booted(): void
    {
        static::created(function (MaintenanceMessage $message) {
            $message->loadMissing('request.tenant', 'sender');
            $request = $message->request;

            if (! $request) {
                return;
            }

            if ($message->sender?->hasRole('tenant')) {
                NotificationRecipients::landlordOperators((int) $request->landlord_id, (int) $message->sender_id)
                    ->each(fn (User $user) => $user->notify(new MaintenanceMessagePostedNotification($message)));

                return;
            }

            if ($request->tenant_id && (int) $request->tenant_id !== (int) $message->sender_id) {
                $request->tenant?->notify(new MaintenanceMessagePostedNotification($message));
            }
        });
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(MaintenanceRequest::class, 'request_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
