<?php

namespace App\Enums;

enum MoveInCalculationType: string
{
    case FixedAmount = 'fixed_amount';
    case RentMultiplier = 'rent_multiplier';
    case PercentageOfRent = 'percentage_of_rent';
    case FirstPeriodCalculation = 'first_period_calculation';
    case ManualPerRental = 'manual_per_rental';
}
