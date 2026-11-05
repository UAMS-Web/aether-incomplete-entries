<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Scheduled scan
    |--------------------------------------------------------------------------
    |
    | The dashboard widget only reads the result of the `cp:scan-incomplete-published-entries`
    | command; it never scans inside a request, because a scan costs over a minute. With
    | `enabled` on, the add-on schedules that command hourly, in the background and without
    | overlapping itself. Turn it off to schedule the command from the application instead.
    |
    */

    'schedule' => [
        'enabled' => true,
    ],

];
