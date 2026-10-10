<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Role;
use App\Models\Loan;
use App\Models\User;

class LoanPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->role === Role::Member;
    }

    public function return(User $user, Loan $loan): bool
    {
        return $user->isAdmin() || $loan->user()->is($user);
    }
}
