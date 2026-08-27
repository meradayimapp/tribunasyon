<?php

namespace Tests\Feature\Auth;

use App\Enums\TeamStatus;
use App\Enums\UserStatus;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_creates_member_and_follows_favorite_team(): void
    {
        $team = $this->team();
        $response = $this->post('/kayit', [
            'name' => 'Yeni Üye', 'username' => 'yeniuye', 'email' => 'yeni@example.com',
            'favorite_team_id' => $team->id, 'password' => 'password', 'password_confirmation' => 'password',
        ]);

        $response->assertRedirect(route('home'));
        $this->assertAuthenticated();
        $user = User::whereEmail('yeni@example.com')->firstOrFail();
        $this->assertTrue($user->followedTeams()->whereKey($team->id)->exists());
    }

    public function test_suspended_user_cannot_log_in(): void
    {
        $user = User::factory()->create(['status' => UserStatus::Suspended, 'password' => 'password']);
        $this->post('/giris', ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    private function team(): Team
    {
        return Team::create(['name' => 'Test Takımı', 'slug' => 'test-takimi', 'short_name' => 'TT', 'primary_color' => '#111111', 'secondary_color' => '#ffffff', 'status' => TeamStatus::Active]);
    }
}
