<?php

namespace Tests\Feature\League;

use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\League\Models\League;
use App\Domain\League\Models\LeagueMembership;
use App\Domain\Notification\Models\LeagueNotice;
use App\Domain\Notification\Models\LeagueNoticeRecipient;
use App\Domain\Notification\Models\NotificationPreference;
use App\Domain\Team\Models\Team;
use App\Domain\Team\Models\TeamRepresentative;
use App\Support\LeagueContext;
use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NoticeManagementTest extends TestCase
{
    use RefreshDatabase;

    private League $league;
    private User $admin;
    private Role $adminRole;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(IdentitySeeder::class);
        $this->league = League::create([
            'name' => 'Liga de Avisos',
            'slug' => 'liga-avisos-'.uniqid(),
            'primary_color' => '#125444',
            'secondary_color' => '#d9a928',
            'status' => 'active',
        ]);
        $this->admin = User::factory()->create();
        $this->adminRole = Role::where('slug', 'league_admin')->firstOrFail();
        $this->membership($this->admin, $this->adminRole);
    }

    public function test_administrator_can_create_and_publish_a_notice_for_the_whole_league(): void
    {
        $player = User::factory()->create();
        $this->membership($player, Role::where('slug', 'player')->firstOrFail());

        $this->asAdmin()->post('/liga/avisos', $this->payload())
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $notice = LeagueNotice::firstOrFail();
        $this->assertSame('draft', $notice->status->value);
        $this->assertDatabaseHas('audit_logs', ['action' => 'notification.notice.created', 'league_id' => $this->league->id]);

        $this->asAdmin()->post("/liga/avisos/{$notice->id}/publicar")
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('published', $notice->fresh()->status->value);
        $this->assertDatabaseHas('league_notice_recipients', ['league_notice_id' => $notice->id, 'user_id' => $this->admin->id]);
        $this->assertDatabaseHas('league_notice_recipients', ['league_notice_id' => $notice->id, 'user_id' => $player->id]);
        $this->assertDatabaseCount('league_notice_recipients', 2);
    }

    public function test_normal_notice_respects_preferences_but_urgent_notice_is_mandatory(): void
    {
        $player = User::factory()->create();
        $this->membership($player, Role::where('slug', 'player')->firstOrFail());
        NotificationPreference::create([
            'league_id' => $this->league->id,
            'user_id' => $player->id,
            'general_enabled' => false,
            'schedule_enabled' => true,
            'finance_enabled' => true,
            'discipline_enabled' => true,
        ]);

        $this->asAdmin()->post('/liga/avisos', $this->payload());
        $normal = LeagueNotice::firstOrFail();
        $this->asAdmin()->post("/liga/avisos/{$normal->id}/publicar")->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('league_notice_recipients', ['league_notice_id' => $normal->id, 'user_id' => $player->id]);

        $this->asAdmin()->post('/liga/avisos', $this->payload(['title' => 'Aviso urgente', 'priority' => 'urgent']));
        $urgent = LeagueNotice::latest('id')->firstOrFail();
        $this->asAdmin()->post("/liga/avisos/{$urgent->id}/publicar")->assertSessionHasNoErrors();
        $this->assertDatabaseHas('league_notice_recipients', ['league_notice_id' => $urgent->id, 'user_id' => $player->id]);
    }

    public function test_role_audience_only_delivers_to_selected_active_role(): void
    {
        $referee = User::factory()->create();
        $player = User::factory()->create();
        $this->membership($referee, Role::where('slug', 'referee')->firstOrFail());
        $this->membership($player, Role::where('slug', 'player')->firstOrFail());

        $this->asAdmin()->post('/liga/avisos', $this->payload([
            'audience_type' => 'roles',
            'audience_role_slugs' => ['referee'],
        ]));
        $notice = LeagueNotice::firstOrFail();
        $this->asAdmin()->post("/liga/avisos/{$notice->id}/publicar")->assertSessionHasNoErrors();

        $this->assertDatabaseHas('league_notice_recipients', ['league_notice_id' => $notice->id, 'user_id' => $referee->id]);
        $this->assertDatabaseMissing('league_notice_recipients', ['league_notice_id' => $notice->id, 'user_id' => $player->id]);
        $this->assertDatabaseMissing('league_notice_recipients', ['league_notice_id' => $notice->id, 'user_id' => $this->admin->id]);
    }

    public function test_team_audience_only_delivers_to_people_linked_to_selected_team(): void
    {
        $representativeRole = Role::where('slug', 'team_representative')->firstOrFail();
        $selectedRepresentative = User::factory()->create();
        $otherRepresentative = User::factory()->create();
        $selectedMembership = $this->membership($selectedRepresentative, $representativeRole);
        $otherMembership = $this->membership($otherRepresentative, $representativeRole);
        $selectedTeam = $this->team('Águilas');
        $otherTeam = $this->team('Halcones');
        $this->representative($selectedTeam, $selectedRepresentative, $selectedMembership);
        $this->representative($otherTeam, $otherRepresentative, $otherMembership);

        $this->asAdmin()->post('/liga/avisos', $this->payload([
            'audience_type' => 'teams',
            'audience_team_ids' => [$selectedTeam->id],
        ]));
        $notice = LeagueNotice::firstOrFail();
        $this->asAdmin()->post("/liga/avisos/{$notice->id}/publicar")->assertSessionHasNoErrors();

        $this->assertDatabaseHas('league_notice_recipients', ['league_notice_id' => $notice->id, 'user_id' => $selectedRepresentative->id]);
        $this->assertDatabaseMissing('league_notice_recipients', ['league_notice_id' => $notice->id, 'user_id' => $otherRepresentative->id]);
    }

    public function test_recipient_can_mark_own_notice_as_read_but_not_another_users_notice(): void
    {
        $player = User::factory()->create();
        $other = User::factory()->create();
        $playerRole = Role::where('slug', 'player')->firstOrFail();
        $this->membership($player, $playerRole);
        $this->membership($other, $playerRole);
        $notice = LeagueNotice::create([
            ...$this->payload(),
            'league_id' => $this->league->id,
            'status' => 'published',
            'created_by' => $this->admin->id,
            'published_by' => $this->admin->id,
            'published_at' => now(),
        ]);
        $recipient = LeagueNoticeRecipient::create(['league_notice_id' => $notice->id, 'user_id' => $player->id, 'delivered_at' => now()]);
        $otherRecipient = LeagueNoticeRecipient::create(['league_notice_id' => $notice->id, 'user_id' => $other->id, 'delivered_at' => now()]);

        $this->actingAs($player)->withSession($this->leagueSession($playerRole))
            ->post("/liga/avisos/{$recipient->id}/leer")
            ->assertRedirect();
        $this->assertNotNull($recipient->fresh()->read_at);

        $this->actingAs($player)->withSession($this->leagueSession($playerRole))
            ->post("/liga/avisos/{$otherRecipient->id}/leer")
            ->assertNotFound();
    }

    public function test_non_administrator_cannot_create_notice_or_access_another_leagues_notice(): void
    {
        $player = User::factory()->create();
        $playerRole = Role::where('slug', 'player')->firstOrFail();
        $this->membership($player, $playerRole);
        $this->actingAs($player)->withSession($this->leagueSession($playerRole))
            ->post('/liga/avisos', $this->payload())
            ->assertForbidden();

        $otherLeague = League::create(['name' => 'Otra Liga', 'slug' => 'otra-'.uniqid(), 'primary_color' => '#111111', 'secondary_color' => '#eeeeee', 'status' => 'active']);
        $foreign = LeagueNotice::create([...$this->payload(), 'league_id' => $otherLeague->id, 'status' => 'draft', 'created_by' => $this->admin->id]);
        $this->asAdmin()->post("/liga/avisos/{$foreign->id}/publicar")->assertNotFound();
    }

    private function payload(array $overrides = []): array
    {
        return [
            'title' => 'Comunicado general',
            'body' => 'Este es un aviso interno para los integrantes de la liga.',
            'category' => 'general',
            'priority' => 'normal',
            'audience_type' => 'all',
            'audience_role_slugs' => [],
            'audience_team_ids' => [],
            'expires_at' => null,
            ...$overrides,
        ];
    }

    private function membership(User $user, Role $role): LeagueMembership
    {
        return LeagueMembership::create(['league_id' => $this->league->id, 'user_id' => $user->id, 'role_id' => $role->id, 'status' => 'active', 'started_at' => now()]);
    }

    private function team(string $name): Team
    {
        return Team::create(['league_id' => $this->league->id, 'name' => $name, 'short_name' => $name, 'slug' => str($name.'-'.uniqid())->slug(), 'status' => 'active']);
    }

    private function representative(Team $team, User $user, LeagueMembership $membership): void
    {
        TeamRepresentative::create(['team_id' => $team->id, 'league_membership_id' => $membership->id, 'user_id' => $user->id, 'photo_path' => 'private/photo.png', 'ine_path' => 'private/ine.png', 'status' => 'active', 'started_at' => now(), 'assigned_by' => $this->admin->id]);
    }

    private function leagueSession(Role $role): array
    {
        return [LeagueContext::LEAGUE_KEY => $this->league->id, LeagueContext::ROLE_KEY => $role->id];
    }

    private function asAdmin(): static
    {
        return $this->actingAs($this->admin)->withSession($this->leagueSession($this->adminRole));
    }
}
