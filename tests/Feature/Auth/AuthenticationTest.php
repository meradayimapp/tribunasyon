<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_opens_and_password_login_still_works(): void
    {
        $user = User::factory()->create(['password' => 'password']);

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Tribüne dön')
            ->assertSee('Google ile devam et');

        $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_registration_creates_member_and_redirects_to_team_onboarding(): void
    {
        $response = $this->post('/kayit', [
            'name' => 'Yeni Üye', 'username' => 'yeniuye', 'email' => 'yeni@example.com',
            'password' => 'password', 'password_confirmation' => 'password',
        ]);

        $response->assertRedirect(route('onboarding.teams.edit'));
        $this->assertAuthenticated();
        $user = User::whereEmail('yeni@example.com')->firstOrFail();
        $this->assertSame(UserRole::Member, $user->role);
        $this->assertSame(UserStatus::Active, $user->status);
        $this->assertNull($user->favorite_team_id);
        $this->assertFalse($user->followedTeams()->exists());
    }

    public function test_suspended_user_cannot_log_in(): void
    {
        $user = User::factory()->create(['status' => UserStatus::Suspended, 'password' => 'password']);
        $this->post('/giris', ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_google_login_uses_existing_account_without_changing_its_role(): void
    {
        $user = User::factory()->create([
            'email' => 'moderator@example.com',
            'role' => UserRole::Moderator,
        ]);
        Socialite::fake('google', SocialiteUser::fake([
            'email' => 'MODERATOR@example.com',
            'name' => 'Başka Bir İsim',
        ]));

        $this->withSession(['url.intended' => route('teams.index')])
            ->get(route('auth.google.callback'))
            ->assertRedirect(route('teams.index'));

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseCount('users', 1);
        $this->assertSame(UserRole::Moderator, $user->fresh()->role);
    }

    public function test_first_google_login_creates_active_member_and_redirects_to_onboarding(): void
    {
        Socialite::fake('google', SocialiteUser::fake([
            'email' => 'google@example.com',
            'name' => 'Google Üyesi',
            'token' => 'must-not-be-stored',
        ]));

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('onboarding.teams.edit'));

        $user = User::whereEmail('google@example.com')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertSame(UserRole::Member, $user->role);
        $this->assertSame(UserStatus::Active, $user->status);
        $this->assertNotNull($user->email_verified_at);
        $this->assertSame('google', $user->username);
        $this->assertArrayNotHasKey('token', $user->getAttributes());
    }

    public function test_suspended_user_cannot_bypass_status_with_google(): void
    {
        User::factory()->create(['email' => 'suspended@example.com', 'status' => UserStatus::Suspended]);
        Socialite::fake('google', SocialiteUser::fake(['email' => 'suspended@example.com']));

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertDatabaseCount('users', 1);
    }

    public function test_unverified_google_email_is_rejected(): void
    {
        Socialite::fake('google', SocialiteUser::fake([
            'email' => 'unverified@example.com',
            'email_verified' => false,
        ]));

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }
}
