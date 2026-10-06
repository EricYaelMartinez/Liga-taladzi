<?php

namespace App\Domain\Scheduling\Enums;

enum VenueStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
