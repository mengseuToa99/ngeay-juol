<?php

namespace App\Contracts\Billing;

use App\Models\Subscription;
use App\Support\Billing\GatewayChargeResult;

interface PaymentGateway
{
    public function key(): string;

    public function supportsAutoRenew(Subscription $subscription): bool;

    public function chargeSubscription(Subscription $subscription): GatewayChargeResult;
}
