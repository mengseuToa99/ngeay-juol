<?php

namespace App\Enums;

enum MoveInRequirementStatus: string
{
    case Outstanding = 'outstanding';
    case PartiallyPaid = 'partially_paid';
    case Satisfied = 'satisfied';
    case Overridden = 'overridden';
    case Cancelled = 'cancelled';
}
