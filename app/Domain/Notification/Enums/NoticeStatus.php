<?php

namespace App\Domain\Notification\Enums;

enum NoticeStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Withdrawn = 'withdrawn';
}
