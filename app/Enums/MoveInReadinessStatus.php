<?php

namespace App\Enums;

enum MoveInReadinessStatus: string
{
    case Draft = 'draft';
    case AwaitingPayment = 'awaiting_payment';
    case ReadyForMoveIn = 'ready_for_move_in';
    case Active = 'active';
}
