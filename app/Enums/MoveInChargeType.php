<?php

namespace App\Enums;

enum MoveInChargeType: string
{
    case FirstPeriodRent = 'first_period_rent';
    case LastMonthRentCredit = 'last_month_rent_credit';
    case SecurityDeposit = 'security_deposit';
    case OtherRefundableDeposit = 'other_refundable_deposit';
    case NonRefundableFee = 'non_refundable_fee';

    public function isRefundable(): bool
    {
        return in_array($this, [self::LastMonthRentCredit, self::SecurityDeposit, self::OtherRefundableDeposit], true);
    }
}
