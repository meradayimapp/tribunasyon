<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use RuntimeException;
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
            ->assertSee('Google ile devam et')
            ->assertSee('href="'.route('auth.google.redirect').'"', false);

        $this->get(route('register'))
            ->assertOk()
            ->assertSee('href="'.route('auth.google.redirect').'"', false);

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

    public function test_google_redirect_sends_the_guest_to_the_provider(): void
    {
        Socialite::fake('google');

        $this->get(route('auth.google.redirect'))
            ->assertRedirect('https://socialite.fake/google/authorize');
    }

    public function test_verified_google_email_links_an_existing_account_without_changing_its_role(): void
    {
        $user = User::factory()->create([
            'email' => 'moderator@example.com',
            'role' => UserRole::Moderator,
        ]);
        Socialite::fake('google', SocialiteUser::fake([
            'id' => 'google-moderator',
            'email' => 'MODERATOR@example.com',
            'name' => 'Başka Bir İsim',
            'avatar' => 'https://example.com/moderator.jpg',
            'email_verified' => true,
        ]));

        $this->withSession(['url.intended' => route('teams.index')])
            ->get(route('auth.google.callback'))
            ->assertRedirect(route('teams.index'));

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseCount('users', 1);
        $user->refresh();
        $this->assertSame('google-moderator', $user->google_id);
        $this->assertSame('https://example.com/moderator.jpg', $user->google_avatar_url);
        $this->assertSame(UserRole::Moderator, $user->role);
    }

    public function test_existing_google_id_logs_into_the_same_account_without_changing_role_or_email(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@example.com',
            'google_id' => 'google-admin',
            'role' => UserRole::Admin,
        ]);
        Socialite::fake('google', SocialiteUser::fake([
            'id' => 'google-admin',
            'email' => 'changed@example.com',
            'email_verified' => false,
        ]));

        $this->get(route('auth.google.callback'))->assertRedirect(route('home'));

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseCount('users', 1);
        $user->refresh();
        $this->assertSame('admin@example.com', $user->email);
        $this->assertSame(UserRole::Admin, $user->role);
    }

    public function test_first_verified_google_login_creates_active_member_and_redirects_to_onboarding(): void
    {
        Socialite::fake('google', SocialiteUser::fake([
            'id' => 'google-new-member',
            'email' => 'google@example.com',
            'name' => 'Google Üyesi',
            'avatar' => 'https://example.com/google-member.jpg',
            'email_verified' => true,
            'token' => 'must-not-be-stored',
            'refreshToken' => 'must-not-be-stored-either',
        ]));

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('onboarding.teams.edit'));

        $user = User::whereEmail('google@example.com')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertSame(UserRole::Member, $user->role);
        $this->assertSame(UserStatus::Active, $user->status);
        $this->assertNotNull($user->email_verified_at);
        $this->assertSame('google', $user->username);
        $this->assertSame('google-new-member', $user->google_id);
        $this->assertSame('https://example.com/google-member.jpg', $user->google_avatar_url);
        $this->assertTrue(Hash::isHashed($user->password));
        $this->assertNotSame('must-not-be-stored', $user->password);
        $this->assertArrayNotHasKey('token', $user->getAttributes());
        $this->assertArrayNotHasKey('refreshToken', $user->getAttributes());
    }

    public function test_suspended_user_cannot_bypass_status_with_google(): void
    {
        $user = User::factory()->create([
            'email' => 'suspended@example.com',
            'status' => UserStatus::Suspended,
        ]);
        Socialite::fake('google', SocialiteUser::fake([
            'id' => 'google-suspended',
            'email' => 'suspended@example.com',
            'email_verified' => true,
        ]));

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertDatabaseCount('users', 1);
        $this->assertSame($user->id, User::firstOrFail()->id);
        $this->assertNull($user->fresh()->google_id);
    }

    public function test_unverified_google_email_cannot_link_an_existing_account(): void
    {
        $user = User::factory()->create(['email' => 'unverified@example.com']);
        Socialite::fake('google', SocialiteUser::fake([
            'id' => 'google-unverified',
            'email' => 'unverified@example.com',
            'email_verified' => false,
        ]));

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertDatabaseCount('users', 1);
        $this->assertNull($user->fresh()->google_id);
    }

    public function test_google_callback_does_not_create_duplicate_provider_users(): void
    {
        $googleUser = SocialiteUser::fake([
            'id' => 'google-repeat',
            'email' => 'repeat@example.com',
            'email_verified' => true,
        ]);
        Socialite::fake('google', $googleUser);

        $this->get(route('auth.google.callback'))->assertRedirect(route('onboarding.teams.edit'));
        Auth::logout();

        Socialite::fake('google', $googleUser);
        $this->get(route('auth.google.callback'))->assertRedirect(route('home'));

        $this->assertAuthenticatedAs(User::where('google_id', 'google-repeat')->firstOrFail());
        $this->assertDatabaseCount('users', 1);
    }

    public function test_google_callback_failure_returns_to_login_without_logging_sensitive_values(): void
    {
        Log::spy();
        Socialite::fake('google', function (): never {
            throw new RuntimeException('authorization_code=must-not-be-logged');
        });

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors([
                'email' => 'Google ile giriş tamamlanamadı. Lütfen tekrar deneyin.',
            ]);

        $this->assertGuest();
        Log::shouldHaveReceived('warning')->once()->with(
            'Google OAuth callback failed.',
            Mockery::on(fn (array $context): bool => $context === ['exception' => RuntimeException::class]),
        );
    }

    public function test_google_callback_rejects_a_missing_email(): void
    {
        Socialite::fake('google', SocialiteUser::fake([
            'id' => 'google-no-email',
            'email' => null,
            'email_verified' => true,
        ]));

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }
}
