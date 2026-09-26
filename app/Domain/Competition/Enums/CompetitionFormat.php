<?php

namespace App\Domain\Competition\Enums;

enum CompetitionFormat: string
{
    case RoundRobin = 'round_robin';
    case DoubleRoundRobin = 'double_round_robin';
    case Knockout = 'knockout';
    case GroupsKnockout = 'groups_knockout';
    case Manual = 'manual';
}
