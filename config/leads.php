<?php

return [
    'auto_assignment' => [
        'enabled' => env('LEADS_AUTO_ASSIGNMENT_ENABLED', true),
        'strategy' => env('LEADS_ASSIGNMENT_STRATEGY', 'priority_round_robin'),
        'recent_days' => (int) env('LEADS_ASSIGNMENT_RECENT_DAYS', 30),
    ],
];
