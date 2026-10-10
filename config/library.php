<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Loan rules
    |--------------------------------------------------------------------------
    */

    'max_active_loans' => (int) env('LIBRARY_MAX_ACTIVE_LOANS', 3),

    'loan_days' => (int) env('LIBRARY_LOAN_DAYS', 14),

    'fine_per_day_cents' => (int) env('LIBRARY_FINE_PER_DAY_CENTS', 100),

];
