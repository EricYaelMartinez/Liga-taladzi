<?php

namespace App\Domain\Scheduling\Enums;

enum LineupPlayerRole: string
{
    case Starter = 'starter';
    case Substitute = 'substitute';
}
