<?php

namespace App\Modules\Finance\Enums;

enum PaymentStatus: string
{
    case PENDING = 'pending';
    case SUCCESSFUL = 'successful';
    case PAID = 'paid';
    case FAILED = 'failed';
    case REFUNDED = 'refunded';
}