<?php

namespace App\Domain\Scheduling\Services;

use App\Domain\Competition\Models\Competition;
use App\Domain\Identity\Models\User;
use App\Domain\Scheduling\Models\GameMatch;
use App\Domain\Scheduling\Models\PlayingField;
use App\Domain\Scheduling\Models\ScheduleTimeSlot;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class AutomaticScheduleAllocator
{
    public function __construct(private readonly MatchSchedulingService $scheduler) {}

    public function allocate(Competition $competition, iterable $matches, Carbon $date, User $actor, string $reason): void
    {
        $leagueId = $competition->tournament->season->league_id;
        $fields = PlayingField::query()->where('status', 'active')
            ->whereHas('venue', fn ($query) => $query->where('league_id', $leagueId)->where('status', 'active'))
            ->with(['venue.league.setting', 'divisions:id', 'scheduleTimeSlots'])
            ->orderBy('id')->get()
            ->filter(fn (PlayingField $field) => $field->divisions->isEmpty() || $field->divisions->contains('id', $competition->division_id));
        $generalSlots = ScheduleTimeSlot::where('league_id', $leagueId)->whereNull('playing_field_id')
            ->where('weekday', $date->isoWeekday())->where('is_active', true)->orderBy('starts_at')->get();

        if ($fields->isEmpty() || $generalSlots->isEmpty() && $fields->every(fn (PlayingField $field) => $field->scheduleTimeSlots->where('weekday', $date->isoWeekday())->isEmpty())) {
            throw ValidationException::withMessages(['auto_schedule' => 'No existen canchas u horarios estándar configurados para el día seleccionado.']);
        }

        foreach ($matches as $match) {
            $scheduled = false;
            foreach ($this->candidates($fields, $generalSlots, $date) as [$field, $startsAt]) {
                try {
                    $this->scheduler->updateSchedule($match, [
                        'scheduled_at' => $startsAt,
                        'playing_field_id' => $field->id,
                        'public_notes' => $match->public_notes,
                        'reason' => $reason,
                    ], $actor);
                    $scheduled = true;
                    break;
                } catch (ValidationException) {
                    continue;
                }
            }
            if (! $scheduled) {
                throw ValidationException::withMessages([
                    'auto_schedule' => "No hay capacidad disponible para programar {$match->homeParticipation->registered_name} vs {$match->awayParticipation->registered_name} el {$date->format('d/m/Y')}.",
                ]);
            }
        }
    }

    private function candidates($fields, $generalSlots, Carbon $date): array
    {
        $candidates = [];
        foreach ($fields as $field) {
            $specific = $field->scheduleTimeSlots->where('weekday', $date->isoWeekday());
            $slots = $specific->isNotEmpty() ? $specific->where('is_active', true)->sortBy('starts_at') : $generalSlots;
            foreach ($slots as $slot) {
                $candidates[] = [
                    $field,
                    Carbon::parse($date->toDateString().' '.$slot->starts_at, config('app.timezone')),
                ];
            }
        }
        usort($candidates, fn (array $left, array $right) => $left[1]->equalTo($right[1])
            ? $left[0]->id <=> $right[0]->id : $left[1]->getTimestamp() <=> $right[1]->getTimestamp());
        return $candidates;
    }
}
