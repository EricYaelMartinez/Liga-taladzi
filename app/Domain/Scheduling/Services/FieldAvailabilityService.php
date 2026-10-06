<?php

namespace App\Domain\Scheduling\Services;

use App\Domain\Scheduling\Models\PlayingField;
use Carbon\CarbonInterface;

class FieldAvailabilityService
{
    public function isAvailable(PlayingField $field, CarbonInterface $startsAt, CarbonInterface $endsAt): bool
    {
        $field->loadMissing('venue.league.setting');
        if ($field->status->value !== 'active' || $field->venue->status->value !== 'active') return false;
        if ($endsAt->lessThanOrEqualTo($startsAt) || ! $startsAt->isSameDay($endsAt)) return false;

        $buffer = (int) ($field->venue->league->setting?->schedule_buffer_minutes ?? 0);
        $reservedUntil = $endsAt->copy()->addMinutes($buffer);
        if (! $startsAt->isSameDay($reservedUntil)) return false;
        $date = $startsAt->toDateString();
        $available = $field->availabilities()
            ->where('weekday', $startsAt->isoWeekday())
            ->where('starts_at', '<=', $startsAt->format('H:i'))
            ->where('ends_at', '>=', $reservedUntil->format('H:i'))
            ->where(fn ($query) => $query->whereNull('valid_from')->orWhereDate('valid_from', '<=', $date))
            ->where(fn ($query) => $query->whereNull('valid_until')->orWhereDate('valid_until', '>=', $date))
            ->exists();
        if (! $available) return false;

        return ! $field->blocks()
            ->where('starts_at', '<', $reservedUntil)
            ->where('ends_at', '>', $startsAt)
            ->exists();
    }
}
