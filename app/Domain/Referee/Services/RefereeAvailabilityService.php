<?php

namespace App\Domain\Referee\Services;

use App\Domain\Referee\Models\Referee;
use Carbon\CarbonInterface;

class RefereeAvailabilityService
{
    public function isAvailable(Referee $referee, CarbonInterface $startsAt, CarbonInterface $endsAt): bool
    {
        if ($referee->status->value !== 'active') return false;
        if ($endsAt->lessThanOrEqualTo($startsAt) || ! $startsAt->isSameDay($endsAt)) return false;

        $date = $startsAt->toDateString();

        return $referee->availabilities()
            ->where('weekday', $startsAt->isoWeekday())
            ->where('starts_at', '<=', $startsAt->format('H:i'))
            ->where('ends_at', '>=', $endsAt->format('H:i'))
            ->where(fn ($query) => $query->whereNull('valid_from')->orWhereDate('valid_from', '<=', $date))
            ->where(fn ($query) => $query->whereNull('valid_until')->orWhereDate('valid_until', '>=', $date))
            ->exists();
    }
}
