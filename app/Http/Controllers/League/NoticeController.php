<?php

namespace App\Http\Controllers\League;

use App\Domain\Identity\Models\Role;
use App\Domain\League\Models\League;
use App\Domain\Notification\Enums\NoticeStatus;
use App\Domain\Notification\Models\LeagueNotice;
use App\Domain\Notification\Models\LeagueNoticeRecipient;
use App\Domain\Notification\Models\NotificationPreference;
use App\Domain\Notification\Services\NoticePublicationService;
use App\Domain\Team\Models\Team;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLeagueNoticeRequest;
use App\Http\Requests\UpdateNotificationPreferencesRequest;
use App\Support\AuditLogger;
use App\Support\LeagueContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class NoticeController extends Controller
{
    public function __construct(
        private readonly LeagueContext $context,
        private readonly NoticePublicationService $publication,
        private readonly AuditLogger $audit,
    ) {}

    public function index(Request $request): Response
    {
        $league = $this->league($request);
        $canManage = $this->hasPermission($request, $league, 'notifications.manage');
        $preferences = NotificationPreference::firstOrNew([
            'league_id' => $league->id,
            'user_id' => $request->user()->id,
        ]);

        $inbox = LeagueNoticeRecipient::query()
            ->where('user_id', $request->user()->id)
            ->whereHas('notice', fn ($query) => $query
                ->where('league_id', $league->id)
                ->where('status', NoticeStatus::Published)
                ->where('published_at', '<=', now())
                ->where(fn ($expiry) => $expiry->whereNull('expires_at')->orWhere('expires_at', '>', now())))
            ->with(['notice.creator:id,name'])
            ->orderByRaw('CASE WHEN read_at IS NULL THEN 0 ELSE 1 END')
            ->latest('delivered_at')
            ->paginate(15, ['*'], 'inbox_page')
            ->withQueryString();

        return Inertia::render('League/Notices/Index', [
            'inbox' => $inbox,
            'managedNotices' => $canManage ? LeagueNotice::query()
                ->where('league_id', $league->id)
                ->with('creator:id,name')
                ->withCount([
                    'recipients',
                    'recipients as read_count' => fn ($query) => $query->whereNotNull('read_at'),
                ])
                ->latest('id')
                ->limit(100)
                ->get() : [],
            'permissions' => ['manage' => $canManage],
            'roles' => $canManage ? Role::where('scope', 'league')->orderBy('name')->get(['slug', 'name']) : [],
            'teams' => $canManage ? Team::where('league_id', $league->id)->where('status', 'active')->orderBy('name')->get(['id', 'name']) : [],
            'preferences' => [
                'general_enabled' => $preferences->general_enabled ?? true,
                'schedule_enabled' => $preferences->schedule_enabled ?? true,
                'finance_enabled' => $preferences->finance_enabled ?? true,
                'discipline_enabled' => $preferences->discipline_enabled ?? true,
            ],
        ]);
    }

    public function store(StoreLeagueNoticeRequest $request): RedirectResponse
    {
        $league = $this->league($request);
        $this->assertManage($request, $league);
        $data = $this->validatedAudience($request->validated(), $league);
        $notice = LeagueNotice::create([
            ...$data,
            'league_id' => $league->id,
            'status' => NoticeStatus::Draft,
            'created_by' => $request->user()->id,
        ]);

        $this->audit->log($request, 'notification.notice.created', $notice, $league, [], $notice->toArray());

        return back()->with('success', 'Aviso guardado como borrador. Revísalo antes de publicarlo.');
    }

    public function update(StoreLeagueNoticeRequest $request, LeagueNotice $notice): RedirectResponse
    {
        $league = $this->league($request);
        $this->assertManage($request, $league);
        $this->assertNotice($notice, $league);
        if ($notice->status !== NoticeStatus::Draft) {
            throw ValidationException::withMessages(['notice' => 'Solo se pueden editar avisos en borrador.']);
        }
        $old = $notice->toArray();
        $notice->update($this->validatedAudience($request->validated(), $league));
        $this->audit->log($request, 'notification.notice.updated', $notice, $league, $old, $notice->fresh()->toArray());

        return back()->with('success', 'Borrador actualizado.');
    }

    public function publish(Request $request, LeagueNotice $notice): RedirectResponse
    {
        $league = $this->league($request);
        $this->assertManage($request, $league);
        $this->assertNotice($notice, $league);
        $published = $this->publication->publish($notice, $request->user()->id);
        $this->audit->log(
            $request,
            'notification.notice.published',
            $published,
            $league,
            ['status' => 'draft'],
            ['status' => 'published', 'recipients' => $published->recipients_count],
        );

        return back()->with('success', "Aviso publicado para {$published->recipients_count} destinatarios.");
    }

    public function withdraw(Request $request, LeagueNotice $notice): RedirectResponse
    {
        $league = $this->league($request);
        $this->assertManage($request, $league);
        $this->assertNotice($notice, $league);
        if ($notice->status !== NoticeStatus::Published) {
            throw ValidationException::withMessages(['notice' => 'Solo se puede retirar un aviso publicado.']);
        }

        $notice->update(['status' => NoticeStatus::Withdrawn, 'withdrawn_at' => now()]);
        $this->audit->log($request, 'notification.notice.withdrawn', $notice, $league, ['status' => 'published'], ['status' => 'withdrawn']);

        return back()->with('success', 'Aviso retirado. Dejó de aparecer en las bandejas, pero se conservó el historial.');
    }

    public function markRead(Request $request, LeagueNoticeRecipient $recipient): RedirectResponse
    {
        $league = $this->league($request);
        abort_unless($recipient->user_id === $request->user()->id, 404);
        abort_unless($recipient->notice()->where('league_id', $league->id)->exists(), 404);
        if ($recipient->read_at === null) {
            $recipient->update(['read_at' => now()]);
        }

        return back();
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $league = $this->league($request);
        LeagueNoticeRecipient::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->whereHas('notice', fn ($query) => $query
                ->where('league_id', $league->id)
                ->where('status', NoticeStatus::Published)
                ->where(fn ($expiry) => $expiry->whereNull('expires_at')->orWhere('expires_at', '>', now())))
            ->update(['read_at' => now(), 'updated_at' => now()]);

        return back()->with('success', 'Todos los avisos visibles fueron marcados como leídos.');
    }

    public function updatePreferences(UpdateNotificationPreferencesRequest $request): RedirectResponse
    {
        $league = $this->league($request);
        NotificationPreference::updateOrCreate(
            ['league_id' => $league->id, 'user_id' => $request->user()->id],
            $request->validated(),
        );

        return back()->with('success', 'Preferencias de avisos actualizadas. Los avisos urgentes siempre se entregarán.');
    }

    private function validatedAudience(array $data, League $league): array
    {
        $data['audience_role_slugs'] = $data['audience_type'] === 'roles' ? array_values(array_unique($data['audience_role_slugs'] ?? [])) : null;
        $data['audience_team_ids'] = $data['audience_type'] === 'teams' ? array_values(array_unique(array_map('intval', $data['audience_team_ids'] ?? []))) : null;

        if ($data['audience_type'] === 'teams') {
            $valid = Team::where('league_id', $league->id)->where('status', 'active')->whereIn('id', $data['audience_team_ids'])->count();
            if ($valid !== count($data['audience_team_ids'])) {
                throw ValidationException::withMessages(['audience_team_ids' => 'Uno o más equipos no están activos o no pertenecen a esta liga.']);
            }
        }

        return $data;
    }

    private function league(Request $request): League { return $this->context->current($request)['league']; }
    private function assertNotice(LeagueNotice $notice, League $league): void { abort_unless($notice->league_id === $league->id, 404); }
    private function assertManage(Request $request, League $league): void { abort_unless($this->hasPermission($request, $league, 'notifications.manage'), 403); }
    private function hasPermission(Request $request, League $league, string $permission): bool
    {
        $roleId = (int) $request->session()->get(LeagueContext::ROLE_KEY);
        return $request->user()->hasPermission($permission) || $request->user()->hasLeaguePermission($permission, $league->id, $roleId);
    }
}
