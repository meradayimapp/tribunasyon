<?php

namespace Tests\Feature\Livewire;

use App\Enums\TeamStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Livewire\FootballMatchChat;
use App\Models\FootballCompetition;
use App\Models\FootballMatch;
use App\Models\FootballTeam;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Tests\TestCase;

class FootballMatchChatTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_read_but_rooms_are_isolated_and_guest_cannot_send(): void
    {
        [$first, $second] = $this->chatMatches();
        $user = User::factory()->create();
        $first->chatMessages()->create(['user_id' => $user->id, 'body' => 'Birinci oda']);
        $second->chatMessages()->create(['user_id' => $user->id, 'body' => 'İkinci oda']);

        Livewire::test(FootballMatchChat::class, ['footballMatch' => $first])
            ->assertSee('Birinci oda')->assertDontSee('İkinci oda')
            ->set('body', 'Gönderilemez')->call('send')->assertRedirect(route('login'));
        $this->assertDatabaseCount('football_match_chat_messages', 2);
    }

    public function test_send_matches_player_chat_validation_escape_rate_limit_and_suspension(): void
    {
        [$first, $second] = $this->chatMatches();
        $user = User::factory()->create();
        $this->clearLimits($user);

        Livewire::actingAs($user)->test(FootballMatchChat::class, ['footballMatch' => $first])
            ->set('body', '   ')->call('send')->assertHasErrors('body')
            ->set('body', str_repeat('x', 501))->call('send')->assertHasErrors('body')
            ->set('body', '  <b>güvenli</b>  ')->call('send')->assertHasNoErrors()->assertSee('&lt;b&gt;güvenli&lt;/b&gt;', false);

        Livewire::actingAs($user)->test(FootballMatchChat::class, ['footballMatch' => $second])
            ->set('body', 'Limit aşılamaz')->call('send')->assertHasErrors('body');
        $this->assertDatabaseCount('football_match_chat_messages', 1);

        $suspended = User::factory()->create(['status' => UserStatus::Suspended]);
        Livewire::actingAs($suspended)->test(FootballMatchChat::class, ['footballMatch' => $first])
            ->set('body', 'Gönderilemez')->call('send')->assertForbidden();
    }

    public function test_owner_admin_and_linked_team_moderator_deletion_matches_player_chat(): void
    {
        [$match] = $this->chatMatches();
        $community = Team::create([
            'name' => 'Takım', 'slug' => 'takim', 'short_name' => 'TKM',
            'primary_color' => '#111111', 'secondary_color' => '#ffffff', 'status' => TeamStatus::Active,
        ]);
        $match->homeTeam->update(['team_id' => $community->id]);
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $message = $match->chatMessages()->create(['user_id' => $owner->id, 'body' => 'Sahip']);

        Livewire::actingAs($other)->test(FootballMatchChat::class, ['footballMatch' => $match])->call('deleteMessage', $message->id)->assertForbidden();
        Livewire::actingAs($owner)->test(FootballMatchChat::class, ['footballMatch' => $match])->call('deleteMessage', $message->id);
        $this->assertSoftDeleted($message);

        $message = $match->chatMessages()->create(['user_id' => $owner->id, 'body' => 'Mod']);
        $moderator = User::factory()->create(['role' => UserRole::Moderator]);
        $moderator->moderatedTeams()->attach($community);
        Livewire::actingAs($moderator)->test(FootballMatchChat::class, ['footballMatch' => $match])->call('deleteMessage', $message->id);
        $this->assertSoftDeleted($message);

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $message = $match->chatMessages()->create(['user_id' => $owner->id, 'body' => 'Admin']);
        Livewire::actingAs($admin)->test(FootballMatchChat::class, ['footballMatch' => $match])->call('deleteMessage', $message->id);
        $this->assertSoftDeleted($message);

        $adminMessage = $match->chatMessages()->create(['user_id' => $admin->id, 'body' => 'Korunan yönetici']);
        Livewire::actingAs($moderator)->test(FootballMatchChat::class, ['footballMatch' => $match])
            ->call('deleteMessage', $adminMessage->id)->assertForbidden();
        $this->assertNotSoftDeleted($adminMessage);
    }

    private function chatMatches(): array
    {
        $competition = FootballCompetition::create([
            'provider' => 'live-football-api', 'provider_league_id' => 'league-chat',
            'name' => 'Lig', 'slug' => 'lig-chat', 'is_active' => true,
        ]);
        $teams = collect(['a', 'b', 'c'])->map(fn (string $id): FootballTeam => FootballTeam::create([
            'provider' => 'live-football-api', 'provider_team_id' => $id,
            'provider_name' => strtoupper($id), 'is_active' => true,
        ]));
        $base = [
            'competition_id' => $competition->id, 'provider' => 'live-football-api',
            'kickoff_at' => '2026-09-09 17:00:00', 'status' => 'scheduled', 'is_live' => false,
        ];

        return [
            FootballMatch::create($base + ['provider_match_id' => 'chat-1', 'home_football_team_id' => $teams[0]->id, 'away_football_team_id' => $teams[1]->id]),
            FootballMatch::create($base + ['provider_match_id' => 'chat-2', 'home_football_team_id' => $teams[1]->id, 'away_football_team_id' => $teams[2]->id]),
        ];
    }

    private function clearLimits(User $user): void
    {
        RateLimiter::clear('football-match-chat:burst:'.$user->id);
        RateLimiter::clear('football-match-chat:minute:'.$user->id);
    }
}
