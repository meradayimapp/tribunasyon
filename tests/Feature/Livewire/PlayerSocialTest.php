<?php

namespace Tests\Feature\Livewire;

use App\Enums\TeamStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Livewire\PlayerChat;
use App\Livewire\PlayerFollowButton;
use App\Models\Player;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Tests\TestCase;

class PlayerSocialTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_member_can_follow_and_unfollow_without_duplicates(): void
    {
        $user = User::factory()->create();
        $player = Player::factory()->create();

        Livewire::actingAs($user)->test(PlayerFollowButton::class, ['player' => $player])->call('toggle');
        $this->assertDatabaseHas('player_follows', ['user_id' => $user->id, 'player_id' => $player->id]);
        $user->followedPlayers()->syncWithoutDetaching([$player->id]);
        $this->assertDatabaseCount('player_follows', 1);
        Livewire::actingAs($user)->test(PlayerFollowButton::class, ['player' => $player])->call('toggle');
        $this->assertDatabaseMissing('player_follows', ['user_id' => $user->id, 'player_id' => $player->id]);
    }

    public function test_guest_is_redirected_and_suspended_user_cannot_follow(): void
    {
        $player = Player::factory()->create();
        Livewire::test(PlayerFollowButton::class, ['player' => $player])->call('toggle')->assertRedirect(route('login'));
        $suspended = User::factory()->create(['status' => UserStatus::Suspended]);
        Livewire::actingAs($suspended)->test(PlayerFollowButton::class, ['player' => $player])->call('toggle')->assertForbidden();
    }

    public function test_guest_can_read_but_cannot_send_chat_message(): void
    {
        $player = Player::factory()->create();
        $user = User::factory()->create();
        $player->chatMessages()->create(['user_id' => $user->id, 'body' => 'Herkese açık mesaj']);

        Livewire::test(PlayerChat::class, ['player' => $player])
            ->assertSee('Herkese açık mesaj')
            ->set('body', 'Gönderilemez')
            ->call('send')
            ->assertRedirect(route('login'));
        $this->assertDatabaseCount('player_chat_messages', 1);
    }

    public function test_send_trims_validates_and_escapes_body(): void
    {
        [$player, $user] = $this->records();
        $this->clearLimits($user);

        Livewire::actingAs($user)->test(PlayerChat::class, ['player' => $player])
            ->set('body', '   ')->call('send')->assertHasErrors('body')
            ->set('body', str_repeat('x', 501))->call('send')->assertHasErrors('body')
            ->set('body', '  <b>güvenli</b>  ')->call('send')->assertHasNoErrors()->assertSee('&lt;b&gt;güvenli&lt;/b&gt;', false);
        $this->assertDatabaseHas('player_chat_messages', ['body' => '<b>güvenli</b>']);
    }

    public function test_global_rate_limit_cannot_be_bypassed_by_switching_players(): void
    {
        $user = User::factory()->create();
        $first = Player::factory()->create();
        $second = Player::factory()->create();
        $this->clearLimits($user);

        Livewire::actingAs($user)->test(PlayerChat::class, ['player' => $first])->set('body', 'Bir')->call('send')->assertHasNoErrors();
        Livewire::actingAs($user)->test(PlayerChat::class, ['player' => $second])->set('body', 'İki')->call('send')->assertHasErrors('body');
        $this->assertDatabaseCount('player_chat_messages', 1);
    }

    public function test_suspended_user_cannot_send_chat_message(): void
    {
        $player = Player::factory()->create();
        $user = User::factory()->create(['status' => UserStatus::Suspended]);

        Livewire::actingAs($user)->test(PlayerChat::class, ['player' => $player])
            ->set('body', 'Gönderilemez')->call('send')->assertForbidden();
        $this->assertDatabaseCount('player_chat_messages', 0);
    }

    public function test_poll_fetches_only_messages_after_last_known_id_without_profile_queries(): void
    {
        [$player, $user] = $this->records();
        for ($i = 1; $i <= 50; $i++) {
            $player->chatMessages()->create(['user_id' => $user->id, 'body' => "Eski {$i}"]);
        }
        $component = Livewire::actingAs($user)->test(PlayerChat::class, ['player' => $player]);
        $player->chatMessages()->create(['user_id' => $user->id, 'body' => 'Yeni mesaj']);

        DB::enableQueryLog();
        $component->call('poll')->assertSee('Yeni mesaj');
        $queries = collect(DB::getQueryLog())->pluck('query');

        $this->assertTrue($queries->contains(fn (string $sql): bool => str_contains($sql, 'player_chat_messages') && str_contains($sql, '"id" > ?')));
        $this->assertFalse($queries->contains(fn (string $sql): bool => str_contains($sql, 'player_follows') || str_contains($sql, 'from "teams"')));
        $this->assertCount(51, $component->get('messages'));
        $this->assertSame('Yeni mesaj', $component->get('messages')[0]['body']);
    }

    public function test_messages_are_always_rendered_newest_first(): void
    {
        [$player, $user] = $this->records();
        for ($i = 1; $i <= 60; $i++) {
            $player->chatMessages()->create(['user_id' => $user->id, 'body' => "Mesaj {$i}"]);
        }

        $component = Livewire::actingAs($user)->test(PlayerChat::class, ['player' => $player]);
        $this->assertSame('Mesaj 60', $component->get('messages')[0]['body']);
        $this->assertSame('Mesaj 11', $component->get('messages')[49]['body']);

        $component->call('loadOlder');
        $messages = $component->get('messages');
        $this->assertSame('Mesaj 60', $messages[0]['body']);
        $this->assertSame('Mesaj 1', $messages[array_key_last($messages)]['body']);

        $this->clearLimits($user);
        $component->set('body', 'En yeni')->call('send');
        $this->assertSame('En yeni', $component->get('messages')[0]['body']);
    }

    public function test_chat_window_never_exceeds_250_messages(): void
    {
        [$player, $user] = $this->records();
        for ($i = 1; $i <= 400; $i++) {
            $player->chatMessages()->create(['user_id' => $user->id, 'body' => "Mesaj {$i}"]);
        }

        $component = Livewire::test(PlayerChat::class, ['player' => $player]);
        $this->assertCount(50, $component->get('messages'));
        for ($i = 0; $i < 5; $i++) {
            $component->call('loadOlder');
        }
        $this->assertCount(250, $component->get('messages'));
        $this->assertTrue($component->get('viewingHistory'));
        $component->call('goLatest');
        $this->assertCount(50, $component->get('messages'));
        $this->assertFalse($component->get('viewingHistory'));
    }

    public function test_message_deletion_policy_covers_owner_admin_and_team_moderator(): void
    {
        $team = Team::create(['name' => 'Takım', 'slug' => 'takim', 'short_name' => 'TKM', 'primary_color' => '#111111', 'secondary_color' => '#ffffff', 'status' => TeamStatus::Active]);
        $player = Player::factory()->create(['current_team_id' => $team->id]);
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $message = $player->chatMessages()->create(['user_id' => $owner->id, 'body' => 'Silinecek']);

        Livewire::actingAs($other)->test(PlayerChat::class, ['player' => $player])->call('deleteMessage', $message->id)->assertForbidden();
        Livewire::actingAs($owner)->test(PlayerChat::class, ['player' => $player])->call('deleteMessage', $message->id);
        $this->assertSoftDeleted($message);

        $message = $player->chatMessages()->create(['user_id' => $owner->id, 'body' => 'Admin']);
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        Livewire::actingAs($admin)->test(PlayerChat::class, ['player' => $player])->call('deleteMessage', $message->id);
        $this->assertSoftDeleted($message);

        $message = $player->chatMessages()->create(['user_id' => $owner->id, 'body' => 'Moderatör']);
        $moderator = User::factory()->create(['role' => UserRole::Moderator]);
        $moderator->moderatedTeams()->attach($team);
        Livewire::actingAs($moderator)->test(PlayerChat::class, ['player' => $player])->call('deleteMessage', $message->id);
        $this->assertSoftDeleted($message);

        $adminMessage = $player->chatMessages()->create(['user_id' => $admin->id, 'body' => 'Yönetici mesajı']);
        $moderatorComponent = Livewire::actingAs($moderator)->test(PlayerChat::class, ['player' => $player]);
        $adminMessageState = collect($moderatorComponent->get('messages'))->firstWhere('id', $adminMessage->id);
        $this->assertFalse($adminMessageState['can_delete']);
        $moderatorComponent
            ->assertSee('Yönetici')
            ->call('deleteMessage', $adminMessage->id)
            ->assertForbidden();
        $this->assertNotSoftDeleted($adminMessage);

        $adminComponent = Livewire::actingAs($admin)->test(PlayerChat::class, ['player' => $player]);
        $this->assertTrue(collect($adminComponent->get('messages'))->firstWhere('id', $adminMessage->id)['can_delete']);
        $adminComponent->call('deleteMessage', $adminMessage->id);
        $this->assertSoftDeleted($adminMessage);

        $otherModerator = User::factory()->create(['role' => UserRole::Moderator]);
        $moderatorMessage = $player->chatMessages()->create(['user_id' => $otherModerator->id, 'body' => 'Diğer moderatör']);
        Livewire::actingAs($moderator)->test(PlayerChat::class, ['player' => $player])
            ->call('deleteMessage', $moderatorMessage->id)
            ->assertForbidden();
        $this->assertNotSoftDeleted($moderatorMessage);

        $message = $player->chatMessages()->create(['user_id' => $owner->id, 'body' => 'Yanlış moderatör']);
        $otherTeam = Team::create(['name' => 'Diğer', 'slug' => 'diger', 'short_name' => 'DGR', 'primary_color' => '#222222', 'secondary_color' => '#ffffff', 'status' => TeamStatus::Active]);
        $wrongModerator = User::factory()->create(['role' => UserRole::Moderator]);
        $wrongModerator->moderatedTeams()->attach($otherTeam);
        Livewire::actingAs($wrongModerator)->test(PlayerChat::class, ['player' => $player])->call('deleteMessage', $message->id)->assertForbidden();
        $this->assertNotSoftDeleted($message);
    }

    public function test_moderator_messages_have_role_badge_and_brand_class(): void
    {
        $player = Player::factory()->create();
        $moderator = User::factory()->create(['role' => UserRole::Moderator]);
        $player->chatMessages()->create(['user_id' => $moderator->id, 'body' => 'Resmî moderatör mesajı']);

        Livewire::test(PlayerChat::class, ['player' => $player])
            ->assertSee('Moderatör')
            ->assertSeeHtml('role-moderator');
    }

    public function test_player_soft_delete_preserves_chat_and_force_delete_cascades_it(): void
    {
        [$player, $user] = $this->records();
        $message = $player->chatMessages()->create(['user_id' => $user->id, 'body' => 'Korunacak']);
        $player->delete();
        $this->assertDatabaseHas('player_chat_messages', ['id' => $message->id]);
        $player->forceDelete();
        $this->assertDatabaseMissing('player_chat_messages', ['id' => $message->id]);
    }

    public function test_chat_author_restrict_matches_existing_comment_retention(): void
    {
        [$player, $user] = $this->records();
        $player->chatMessages()->create(['user_id' => $user->id, 'body' => 'Sahipli içerik']);

        $this->expectException(QueryException::class);
        $user->delete();
    }

    private function records(): array
    {
        return [Player::factory()->create(), User::factory()->create()];
    }

    private function clearLimits(User $user): void
    {
        RateLimiter::clear('player-chat:burst:'.$user->id);
        RateLimiter::clear('player-chat:minute:'.$user->id);
    }
}
