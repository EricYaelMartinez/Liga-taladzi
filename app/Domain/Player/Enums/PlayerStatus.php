<?php

namespace App\Domain\Player\Enums;

enum PlayerStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Suspended = 'suspended';
    case Inactive = 'inactive';
}
