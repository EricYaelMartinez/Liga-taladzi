<?php

namespace App\Domain\Scheduling\Enums;

enum MatchdayStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Completed = 'completed';
}
