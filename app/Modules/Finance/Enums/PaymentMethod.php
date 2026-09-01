<?php

namespace App\Modules\Finance\Enums;

enum PaymentMethod: string
{
    case CASH = 'cash';
    case BANK_TRANSFER = 'bank_transfer';
    case POS = 'pos';
    case PAYSTACK = 'paystack';
    case STRIPE = 'stripe';
    case FLUTTERWAVE = 'flutterwave';
    case LEGACY = 'legacy';
    case OTHER = 'other';
}