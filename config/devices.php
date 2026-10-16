<?php

$frontend = env('FRONTEND_URL', env('APP_URL', 'http://localhost'));

return [

    /*
    |--------------------------------------------------------------------------
    | Approved machines (passkeys locked to the office PCs)
    |--------------------------------------------------------------------------
    |
    | A machine proves itself with a passkey kept by Windows Hello. The passkey
    | is tied to the address the CRM is opened on: `rp_id` is that address's
    | host, `origins` the exact addresses a signature may come from.
    |
    | Whether the restriction applies (off / prepare / on) is not set here: it
    | is the switch of the "Appareils" page, stored in app_settings.
    |
    */

    'rp_name' => env('APP_NAME', 'CRM'),

    'rp_id' => env('DEVICE_RP_ID', parse_url($frontend, PHP_URL_HOST) ?: 'localhost'),

    'origins' => array_values(array_filter(array_map(
        fn (string $origin) => rtrim(trim($origin), '/'),
        explode(',', (string) env('DEVICE_ORIGINS', $frontend)),
    ))),

    // Roles that log in from any machine: they can never be locked out
    'exempt_roles' => ['super_admin'],

    // Seconds a login ticket (password accepted, machine not proven yet) stays valid
    'ticket_ttl' => 300,

    // Seconds the Windows prompt may stay open
    'prompt_timeout' => 60,

    // Waiting requests one user may leave: stops a password holder flooding the list
    'max_pending_per_user' => 5,

    // After a PIN check, the browser is remembered this many days: the next
    // logins skip the PIN until then (the countdown is not extended by them)
    'remember_days' => 30,

];
