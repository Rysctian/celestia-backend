<?php

return [
    'timezone' => 'Asia/Manila',
    'duplicate_seconds' => 90,
    'arrival_window_seconds' => 4 * 3600,
    'departure_window_seconds' => 6 * 3600,
    'night_start' => '22:00:00',
    'night_end' => '06:00:00',
    'credited_exceptions' => ['leave', 'official_business', 'holiday'],
];
