<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;

class SimpleLandlordMode
{
    public static function canUse(?User $user): bool
    {
        return (bool) $user?->hasAnyRole(['landlord', 'landlord_manager', 'super_admin']);
    }

    public static function enabledFor(?User $user): bool
    {
        return self::canUse($user) && (bool) $user?->prefers_simple_landlord_mode;
    }

    public static function shouldRedirectToSimple(Request $request): bool
    {
        if (! $request->isMethodSafe()) {
            return false;
        }

        if (! self::enabledFor($request->user())) {
            return false;
        }

        return ($request->is('landlord') || $request->is('landlord/*'))
            && ! $request->is('landlord/simple', 'landlord/simple/*');
    }
}
