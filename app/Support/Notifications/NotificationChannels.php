<?php

namespace App\Support\Notifications;

use App\Models\User;

class NotificationChannels
{
    /** @return list<string> */
    public static function for(User $notifiable, bool $allowMail = false): array
    {
        $channels = ['database'];

        if ($allowMail && static::mailIsConfigured() && filled($notifiable->email)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public static function mailIsConfigured(): bool
    {
        $mailer = (string) config('mail.default', 'log');
        $transport = (string) data_get(config("mail.mailers.{$mailer}"), 'transport', $mailer);
        $from = (string) config('mail.from.address');

        return ! in_array($transport, ['array', 'log'], true)
            && filled($from)
            && $from !== 'hello@example.com';
    }
}
