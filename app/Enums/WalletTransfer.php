<?php

namespace App\Enums;

enum WalletTransfer: string
{
    case Debit = 'debit';
    case Credit = 'credit';
}
