<?php

declare(strict_types=1);

namespace App\Enums;

enum LoanStatus: string
{
    case Active = 'active';
    case Overdue = 'overdue';
    case Returned = 'returned';
}
