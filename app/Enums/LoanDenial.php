<?php

declare(strict_types=1);

namespace App\Enums;

enum LoanDenial: string
{
    case HasOverdueLoans = 'has_overdue_loans';
    case LoanLimitReached = 'loan_limit_reached';
    case AlreadyBorrowed = 'already_borrowed';
    case Unavailable = 'unavailable';

    public function message(): string
    {
        return match ($this) {
            self::HasOverdueLoans => 'You have overdue loans. Return them before borrowing another book.',
            self::LoanLimitReached => 'You have reached the maximum number of active loans.',
            self::AlreadyBorrowed => 'You already have an active loan for this book.',
            self::Unavailable => 'There are no copies of this book available.',
        };
    }
}
