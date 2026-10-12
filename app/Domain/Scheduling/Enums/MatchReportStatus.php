<?php

namespace App\Domain\Scheduling\Enums;

enum MatchReportStatus: string
{
    case Draft = 'draft';
    case AwaitingConfirmations = 'awaiting_confirmations';
    case CorrectionRequired = 'correction_required';
    case UnderReview = 'under_review';
    case Approved = 'approved';
}
