<?php

namespace App\Domain\Notification\Services;

use App\Domain\League\Models\LeagueMembership;
use App\Domain\Notification\Enums\NoticePriority;
use App\Domain\Notification\Enums\NoticeStatus;
use App\Domain\Notification\Models\LeagueNotice;
use App\Domain\Notification\Models\NotificationPreference;
use App\Domain\Player\Models\Player;
use App\Domain\Team\Models\TeamRepresentative;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class NoticePublicationService
{
    public function publish(LeagueNotice $notice, int $actorUserId): LeagueNotice
    {
        return DB::transaction(function () use ($notice, $actorUserId): LeagueNotice {
            $locked = LeagueNotice::query()->lockForUpdate()->findOrFail($notice->id);

            if ($locked->status !== NoticeStatus::Draft) {
                throw ValidationException::withMessages(['notice' => 'Solo se puede publicar un aviso que esté en borrador.']);
            }

            $userIds = $this->resolveAudience($locked);
            if ($locked->priority !== NoticePriority::Urgent) {
                $userIds = $this->applyPreferences($locked, $userIds);
            }

            if ($userIds->isEmpty()) {
                throw ValidationException::withMessages([
                    'audience_type' => 'La audiencia seleccionada no tiene usuarios activos que acepten esta categoría de avisos.',
                ]);
            }

            $now = now();
            $locked->recipients()->insert($userIds->map(fn (int $userId): array => [
                'league_notice_id' => $locked->id,
                'user_id' => $userId,
                'delivered_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all());

            $locked->update([
                'status' => NoticeStatus::Published,
                'published_by' => $actorUserId,
                'published_at' => $now,
            ]);

            return $locked->refresh()->loadCount('recipients');
        });
    }

    private function resolveAudience(LeagueNotice $notice): Collection
    {
        if ($notice->audience_type === 'teams') {
            $teamIds = collect($notice->audience_team_ids)->map(fn ($id): int => (int) $id)->unique()->values();

            $representatives = TeamRepresentative::query()
                ->whereIn('team_id', $teamIds)
                ->where('status', 'active')
                ->whereHas('team', fn ($query) => $query->where('league_id', $notice->league_id))
                ->whereHas('membership', fn ($query) => $query->where('league_id', $notice->league_id)->where('status', 'active'))
                ->whereHas('user', fn ($query) => $query->where('status', 'active'))
                ->pluck('user_id');

            $players = Player::query()
                ->where('league_id', $notice->league_id)
                ->where('status', 'active')
                ->whereNotNull('user_id')
                ->whereHas('user', fn ($query) => $query->where('status', 'active'))
                ->whereHas('user.leagueMemberships', fn ($query) => $query->where('league_id', $notice->league_id)->where('status', 'active'))
                ->whereHas('registrations', fn ($query) => $query->whereIn('team_id', $teamIds)->where('status', 'active'))
                ->pluck('user_id');

            return $representatives->concat($players)->map(fn ($id): int => (int) $id)->unique()->values();
        }

        return LeagueMembership::query()
            ->where('league_id', $notice->league_id)
            ->where('status', 'active')
            ->when(
                $notice->audience_type === 'roles',
                fn ($query) => $query->whereHas('role', fn ($roles) => $roles->whereIn('slug', $notice->audience_role_slugs ?? [])),
            )
            ->whereHas('user', fn ($query) => $query->where('status', 'active'))
            ->pluck('user_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();
    }

    private function applyPreferences(LeagueNotice $notice, Collection $userIds): Collection
    {
        $column = $notice->category->value.'_enabled';
        $disabled = NotificationPreference::query()
            ->where('league_id', $notice->league_id)
            ->whereIn('user_id', $userIds)
            ->where($column, false)
            ->pluck('user_id');

        return $userIds->diff($disabled)->values();
    }
}
