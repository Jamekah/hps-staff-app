<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Annual Allowances
    |--------------------------------------------------------------------------
    |
    | Working days (Mon–Fri) each staff member may take per calendar year, by
    | leave type. A type that is not listed here has no limit: it is counted
    | and displayed, but never warned about.
    |
    | These are deliberately not hard-coded in the enum or the UI — HR numbers
    | change, and every count in the app reads them from here through
    | LeaveType::allowance().
    |
    | There is no carry-over: everyone starts each calendar year at the full
    | allowance, and leave spanning New Year counts its own days in each year.
    |
    */

    'allowances' => [
        'annual' => (int) env('LEAVE_ANNUAL_DAYS', 15),
        'sick' => (int) env('LEAVE_SICK_DAYS', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Caution Threshold
    |--------------------------------------------------------------------------
    |
    | The fraction of an allowance at which the stats panel switches to its
    | caution style, before the limit is actually reached.
    |
    */

    'caution_threshold' => 0.8,

];
