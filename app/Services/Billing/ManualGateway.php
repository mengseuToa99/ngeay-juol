<?php

namespace App\Services\Billing;

use App\Contracts\Billing\PaymentGateway;
use App\Models\Subscription;
use App\Support\Billing\GatewayChargeResult;

class ManualGateway implements PaymentGateway
{
    public function key(): string
    {
        return 'manual';
    }

    public function supportsAutoRenew(Subscription $subscription): bool
    {
        return false;
    }

    public function chargeSubscription(Subscription $subscription): GatewayChargeResult
    {
        return GatewayChargeResult::failed('Manual gateway requires an administrator-recorded payment.');
    }
}
