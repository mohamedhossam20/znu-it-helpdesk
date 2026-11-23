<?php

return [
    /*
    | Public URL of the requester PWA (Vue). Room QR codes point to
    | {frontend_url}/r/{qr_token}, so set this before printing labels.
    */
    'frontend_url' => env('FRONTEND_URL', 'http://localhost:5173'),

    /*
    | Name printed on QR labels.
    */
    'organization_name' => env('HELPDESK_ORGANIZATION_NAME', 'جامعة الزقازيق الأهلية'),

    /*
    | Ticket numbers look like IT-2026-000152.
    */
    'ticket_prefix' => env('HELPDESK_TICKET_PREFIX', 'IT'),

    /*
    | Working time used to compute due dates. Days use Carbon's numbering
    | (0 = Sunday … 6 = Saturday). Holidays are Y-m-d dates.
    */
    'working_hours' => [
        'timezone' => env('HELPDESK_TIMEZONE', 'Africa/Cairo'),
        'days' => [0, 1, 2, 3, 4],
        'start' => '08:00',
        'end' => '16:00',
        'holidays' => array_filter(explode(',', (string) env('HELPDESK_HOLIDAYS', ''))),
    ],

    /*
    | Targets in working minutes per priority: time to first response
    | (acknowledged) and time to resolution (used for due_at).
    */
    'sla' => [
        'urgent' => ['response' => 15, 'resolution' => 120],
        'high' => ['response' => 60, 'resolution' => 480],
        'normal' => ['response' => 240, 'resolution' => 960],
        'low' => ['response' => 480, 'resolution' => 2400],
    ],

    /*
    | "manual": new tickets wait for the supervisor to assign them.
    | "category": assigned to the least busy technician of the problem type.
    */
    'assignment_mode' => env('HELPDESK_ASSIGNMENT_MODE', 'manual'),

    /*
    | A resolved ticket closes by itself after this many days, and the
    | requester can reopen it within reopen_window_days of resolution.
    */
    'auto_close_after_days' => 3,
    'reopen_window_days' => 7,

    'attachments' => [
        'max_files' => 5,
        'max_kilobytes' => 5120,
    ],

    /*
    | New tickets a requester may open per hour.
    */
    'tickets_per_hour' => 10,
];
