<?php

namespace App\Domain\Team\Enums;

enum TeamStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Suspended = 'suspended';
    case Inactive = 'inactive';
    case Deregistered = 'deregistered';
}
