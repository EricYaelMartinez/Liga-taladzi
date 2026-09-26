<?php

namespace App\Domain\Competition\Enums;

enum RegulationStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case InUse = 'in_use';
    case Retired = 'retired';
}
