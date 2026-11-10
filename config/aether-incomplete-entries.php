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

    /*
    |--------------------------------------------------------------------------
    | Custom field conditions
    |--------------------------------------------------------------------------
    |
    | The add-on evaluates the custom field conditions a blueprint names with
    | `custom <name>:<params>` the way the Control Panel does, so a field the editor
    | hides is not reported as incomplete. It ships ports of seven conditions; map the
    | name of each condition your site registers in JavaScript to a class that
    | implements `UAMSWeb\Aether\IncompleteEntries\Contracts\CustomCondition`:
    |
    |     'custom_conditions' => ['hasVideo' => \App\Conditions\HasVideo::class],
    |
    | A name with no port reads as hidden and is logged. A registered name wins over a
    | shipped one of the same name. An entry that is not a name mapped to such a class
    | throws when the audit runs.
    |
    */

    'custom_conditions' => [],

];
