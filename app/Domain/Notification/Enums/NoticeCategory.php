<?php

namespace App\Domain\Notification\Enums;

enum NoticeCategory: string
{
    case General = 'general';
    case Schedule = 'schedule';
    case Finance = 'finance';
    case Discipline = 'discipline';
}
