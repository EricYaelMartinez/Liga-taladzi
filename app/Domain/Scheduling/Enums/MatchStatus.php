<?php

namespace App\Domain\Scheduling\Enums;

enum MatchStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case InProgress = 'in_progress';
    case Finished = 'finished';
    case Suspended = 'suspended';
    case Postponed = 'postponed';
    case Cancelled = 'cancelled';
}
