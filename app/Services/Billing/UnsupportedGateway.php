<?php

namespace App\Services\Billing;

use App\Contracts\Billing\PaymentGateway;
use App\Models\Subscription;
use App\Support\Billing\GatewayChargeResult;

class UnsupportedGateway implements PaymentGateway
{
    public function __construct(private string $gatewayKey) {}

    public function key(): string
    {
        return $this->gatewayKey;
    }

    public function supportsAutoRenew(Subscription $subscription): bool
    {
        return false;
    }

    public function chargeSubscription(Subscription $subscription): GatewayChargeResult
    {
        return GatewayChargeResult::failed("Gateway [{$this->gatewayKey}] is not configured for automatic renewal.");
    }
}
