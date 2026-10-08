<?php

namespace App\Domain\Scheduling\Enums;

enum LineupStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
}
