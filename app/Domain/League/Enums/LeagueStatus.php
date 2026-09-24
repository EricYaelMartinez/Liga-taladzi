<?php

namespace App\Domain\League\Enums;

enum LeagueStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Inactive = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Activa',
            self::Suspended => 'Suspendida',
            self::Inactive => 'Inactiva',
        };
    }
}
