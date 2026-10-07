<?php

namespace App\Domain\Scheduling\Enums;

enum MatchPhase: string
{
    case Regular = 'regular';
    case Group = 'group';
    case Knockout = 'knockout';
    case Custom = 'custom';
}
