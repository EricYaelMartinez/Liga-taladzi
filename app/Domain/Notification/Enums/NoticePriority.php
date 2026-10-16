<?php

namespace App\Domain\Notification\Enums;

enum NoticePriority: string
{
    case Normal = 'normal';
    case Important = 'important';
    case Urgent = 'urgent';
}
