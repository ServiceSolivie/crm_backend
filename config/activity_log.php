<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Activity journal
    |--------------------------------------------------------------------------
    |
    | Operations with no activity for longer than this are deleted every
    | night with their logs (activity-logs:prune). The payments, refunds,
    | leads and users themselves are never touched.
    |
    */

    'retention_days' => (int) env('ACTIVITY_LOG_RETENTION_DAYS', 365),

];
