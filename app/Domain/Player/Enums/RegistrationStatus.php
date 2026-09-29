<?php

namespace App\Domain\Player\Enums;

enum RegistrationStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Rejected = 'rejected';
    case Suspended = 'suspended';
    case Released = 'released';
}
