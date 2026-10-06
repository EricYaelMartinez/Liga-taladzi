<?php

namespace App\Domain\Scheduling\Enums;

enum FieldStatus: string
{
    case Active = 'active';
    case Maintenance = 'maintenance';
    case Inactive = 'inactive';
}
