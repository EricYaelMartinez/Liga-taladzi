<?php

namespace App\Domain\Competition\Enums;

enum SeasonStatus: string
{
    case Planning = 'planning';
    case Registration = 'registration';
    case Active = 'active';
    case Finished = 'finished';
    case Archived = 'archived';
    case Cancelled = 'cancelled';
}
