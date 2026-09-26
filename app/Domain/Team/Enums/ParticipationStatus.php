<?php

namespace App\Domain\Team\Enums;

enum ParticipationStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Rejected = 'rejected';
    case Suspended = 'suspended';
    case Inactive = 'inactive';
    case Deregistered = 'deregistered';
}
