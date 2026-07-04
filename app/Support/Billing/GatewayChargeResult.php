<?php

namespace App\Support\Billing;

class GatewayChargeResult
{
    public function __construct(
        public bool $succeeded,
        public ?string $transactionId = null,
        public ?string $reference = null,
        public ?string $failureReason = null,
    ) {}

    public static function success(string $transactionId, ?string $reference = null): self
    {
        return new self(true, $transactionId, $reference);
    }

    public static function failed(string $reason): self
    {
        return new self(false, failureReason: $reason);
    }
}
