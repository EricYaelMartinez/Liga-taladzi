<?php

return [
    'directory' => env('BACKUP_DIRECTORY', 'backups'),
    'retention_count' => (int) env('BACKUP_RETENTION_COUNT', 8),
    'schedule_day' => env('BACKUP_SCHEDULE_DAY', 'sunday'),
    'schedule_time' => env('BACKUP_SCHEDULE_TIME', '03:00'),
];
