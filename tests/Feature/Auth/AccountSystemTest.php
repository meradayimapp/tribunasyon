<?php

namespace Tests\Feature\Auth;

use App\Enums\PostStatus;
use App\Enums\PostType;
use App\Enums\TeamStatus;
use App\Enums\UserRole;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Team;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

class AccountSystemTest extends TestCase
{
    use RefreshDatabase;

    public function test_usernames_are_normalized_unique_case_insensitively_and_reserved_or_invalid_names_are_rejected(): void
    {
        User::factory()->create(['username' => 'OrhanSevilir']);

        $base = [
            'name' => 'Yeni Üye',
            'email' => 'yeni@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ];

        $this->post('/kayit', $base + ['username' => 'ORHANSEVILIR'])
            ->assertSessionHasErrors('username');
        $this->post('/kayit', $base + ['username' => 'admin'])
            ->assertSessionHasErrors('username');
        $this->post('/kayit', $base + ['username' => 'boş kullanıcı'])
            ->assertSessionHasErrors('username');

        $this->post('/kayit', $base + ['username' => 'Yeni.Uye'])
            ->assertRedirect(route('onboarding.teams.edit'));
        $this->assertDatabaseHas('users', ['username' => 'yeni.uye']);
    }

    public function test_password_reset_request_accepts_email_or_username_without_enumeration_leak(): void
    {
        Notification::fake();
        $user = User::factory()->create([
            'username' => 'orhansevilir',
            'email' => 'orhan@example.com',
        ]);
        $message = 'Eğer bu bilgilerle eşleşen bir hesap varsa şifre sıfırlama bağlantısı e-posta adresine gönderildi.';

        $this->post(route('password.email'), ['identifier' => 'ORHAN@EXAMPLE.COM'])
            ->assertSessionHas('status', $message);
        Notification::assertSentToTimes($user, ResetPasswordNotification::class, 1);

        Password::broker()->deleteToken($user);
        Notification::fake();
        $this->post(route('password.email'), ['identifier' => 'ORHANSEVILIR'])
            ->assertSessionHas('status', $message);
        Notification::assertSentToTimes($user, ResetPasswordNotification::class, 1);

        Notification::fake();
        $this->post(route('password.email'), ['identifier' => 'olmayan-kullanici'])
            ->assertSessionHas('status', $message);
        Notification::assertNothingSent();
    }

    public function test_password_reset_request_is_rate_limited(): void
    {
        $payload = ['identifier' => 'nobody@example.com'];

        $this->post(route('password.email'), $payload)->assertRedirect();
        $this->post(route('password.email'), $payload)->assertRedirect();
        $this->post(route('password.email'), $payload)->assertRedirect();
        $this->post(route('password.email'), $payload)->assertTooManyRequests();
    }

    public function test_google_user_can_reset_password_then_use_password_and_google_login(): void
    {
        Notification::fake();
        $user = User::factory()->create([
            'email' => 'google@example.com',
            'google_id' => 'google-password-user',
            'password' => Hash::make('unknown-random-password'),
            'password_set_at' => null,
        ]);

        $this->post(route('password.email'), ['identifier' => $user->username]);
        $token = null;
        Notification::assertSentTo($user, ResetPasswordNotification::class, function (ResetPasswordNotification $notification) use (&$token): bool {
            $token = $notification->token;

            return true;
        });

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertRedirect(route('login'));

        $user->refresh();
        $this->assertTrue(Hash::check('new-password', $user->password));
        $this->assertNotNull($user->password_set_at);
        $this->assertSame('google-password-user', $user->google_id);

        $this->post(route('login'), ['email' => $user->email, 'password' => 'new-password'])
            ->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($user);
        auth()->logout();

        Socialite::fake('google', SocialiteUser::fake([
            'id' => 'google-password-user',
            'email' => $user->email,
            'email_verified' => true,
        ]));
        $this->get(route('auth.google.callback'))->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_reset_notification_is_turkish_branded_and_mentions_expiration(): void
    {
        $user = User::factory()->make(['email' => 'member@example.com']);
        $mail = (new ResetPasswordNotification('safe-token'))->toMail($user);

        $this->assertSame('Şifreni sıfırla', $mail->subject);
        $this->assertSame('Şifremi sıfırla', $mail->actionText);
        $lines = implode(' ', [...$mail->introLines, ...$mail->outroLines]);
        $this->assertStringContainsString('60 dakika', $lines);
        $this->assertStringContainsString('yok sayabilirsiniz', $lines);
    }

    public function test_google_avatar_is_fallback_and_manual_avatar_has_priority(): void
    {
        config(['media.disk' => 'public', 'media.legacy_disk' => 'public']);
        Storage::fake('public');
        $user = User::factory()->create([
            'google_avatar_url' => 'https://example.com/google.jpg',
            'avatar_path' => null,
        ]);

        $this->assertSame('https://example.com/google.jpg', $user->displayAvatarUrl());

        Storage::disk('public')->put('avatars/manual.jpg', 'avatar');
        $user->update(['avatar_path' => 'avatars/manual.jpg']);
        $this->assertSame(Storage::disk('public')->url('avatars/manual.jpg'), $user->displayAvatarUrl());

        $user->update(['google_avatar_url' => 'http://unsafe.example/avatar.jpg', 'avatar_path' => null]);
        $this->assertNull($user->displayAvatarUrl());
    }

    public function test_valid_avatar_upload_replaces_old_file_and_svg_is_rejected(): void
    {
        config(['media.disk' => 'public', 'media.legacy_disk' => 'public']);
        Storage::fake('public');
        Storage::disk('public')->put('avatars/old.jpg', 'old');
        $team = $this->createTeam();
        $user = User::factory()->create([
            'avatar_path' => 'avatars/old.jpg',
            'favorite_team_id' => $team->id,
        ]);

        $this->actingAs($user)->put(route('profile.update'), [
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'bio' => null,
            'favorite_team_id' => $team->id,
            'avatar' => UploadedFile::fake()->image('new.webp'),
        ])->assertRedirect();

        $newPath = $user->fresh()->avatar_path;
        $this->assertNotSame('avatars/old.jpg', $newPath);
        Storage::disk('public')->assertExists($newPath);
        Storage::disk('public')->assertMissing('avatars/old.jpg');

        $this->actingAs($user)->put(route('profile.update'), [
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'favorite_team_id' => $team->id,
            'avatar' => UploadedFile::fake()->create('unsafe.svg', 10, 'image/svg+xml'),
        ])->assertSessionHasErrors('avatar');
    }

    public function test_google_only_user_can_set_password_without_a_current_password(): void
    {
        $user = User::factory()->create([
            'google_id' => 'google-only',
            'password_set_at' => null,
        ]);

        $this->actingAs($user)->put(route('profile.password'), [
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
        $this->assertNotNull($user->fresh()->password_set_at);
    }

    public function test_google_only_member_can_delete_account_without_an_unknown_hidden_password(): void
    {
        $user = User::factory()->create([
            'google_id' => 'google-delete-only',
            'password_set_at' => null,
        ]);

        $this->actingAs($user)->delete(route('account.destroy'), [
            'confirmation' => '1',
        ])->assertRedirect(route('home'));

        $this->assertGuest();
        $this->assertNotNull($user->fresh()->anonymized_at);
        $this->assertNull($user->fresh()->google_id);
    }

    public function test_password_account_requires_the_current_password_before_deletion(): void
    {
        $user = User::factory()->create(['password' => 'password']);

        $this->actingAs($user)->delete(route('account.destroy'), [
            'confirmation' => '1',
            'current_password' => 'wrong-password',
        ])->assertSessionHasErrors('current_password', null, 'deleteAccount');

        $this->assertAuthenticatedAs($user);
        $this->assertNull($user->fresh()->anonymized_at);
    }

    public function test_member_account_is_anonymized_without_removing_community_content_or_other_users_data(): void
    {
        config(['media.disk' => 'public', 'media.legacy_disk' => 'public']);
        Storage::fake('public');
        Storage::disk('public')->put('avatars/member.jpg', 'avatar');
        $team = $this->createTeam();
        $user = User::factory()->create([
            'name' => 'Kişisel İsim',
            'username' => 'personal_user',
            'email' => 'personal@example.com',
            'password' => 'password',
            'avatar_path' => 'avatars/member.jpg',
            'google_id' => 'google-delete-me',
            'google_avatar_url' => 'https://example.com/avatar.jpg',
            'bio' => 'Kişisel bio',
            'favorite_team_id' => $team->id,
        ]);
        $other = User::factory()->create();
        $post = Post::query()->create([
            'team_id' => $team->id,
            'created_by' => $other->id,
            'type' => PostType::Text,
            'body' => 'Gönderi',
            'status' => PostStatus::Published,
            'published_at' => now(),
        ]);
        $comment = Comment::query()->create(['post_id' => $post->id, 'user_id' => $user->id, 'body' => 'Kalacak yorum']);
        $comment->likes()->create(['user_id' => $user->id]);
        $comment->likes()->create(['user_id' => $other->id]);
        $user->followedTeams()->attach($team);
        $other->followedTeams()->attach($team);

        $this->actingAs($user)->withSession(['private-marker' => 'remove-me'])->delete(route('account.destroy'), [
            'confirmation' => '1',
            'current_password' => 'password',
        ])->assertRedirect(route('home'))->assertSessionMissing('private-marker');

        $this->assertGuest();
        $user->refresh();
        $this->assertSame('Silinmiş kullanıcı', $user->name);
        $this->assertSame('silinmis_kullanici_'.$user->id, $user->username);
        $this->assertSame('silinmis-'.$user->id.'@anonim.tribunasyon.invalid', $user->email);
        $this->assertNull($user->google_id);
        $this->assertNull($user->google_avatar_url);
        $this->assertNull($user->avatar_path);
        $this->assertNull($user->bio);
        $this->assertNull($user->remember_token);
        $this->assertNotNull($user->anonymized_at);
        Storage::disk('public')->assertMissing('avatars/member.jpg');
        $this->assertDatabaseHas('comments', ['id' => $comment->id, 'user_id' => $user->id, 'body' => 'Kalacak yorum']);
        $this->assertDatabaseMissing('likes', ['user_id' => $user->id]);
        $this->assertDatabaseHas('likes', ['user_id' => $other->id]);
        $this->assertDatabaseMissing('team_follows', ['user_id' => $user->id]);
        $this->assertDatabaseHas('team_follows', ['user_id' => $other->id, 'team_id' => $team->id]);
        $this->get(route('posts.show', [$team, $post]))
            ->assertOk()
            ->assertSee('Silinmiş kullanıcı')
            ->assertDontSee('@silinmis_kullanici_'.$user->id);
    }

    public function test_privileged_accounts_cannot_self_delete(): void
    {
        foreach ([UserRole::Admin, UserRole::Moderator] as $role) {
            $user = User::factory()->create(['role' => $role, 'password' => 'password']);

            $this->actingAs($user)->delete(route('account.destroy'), [
                'confirmation' => '1',
                'current_password' => 'password',
            ])->assertSessionHasErrors('account', null, 'deleteAccount');

            $this->assertNull($user->fresh()->anonymized_at);
        }
    }

    private function createTeam(): Team
    {
        return Team::query()->create([
            'name' => 'Test Takımı',
            'slug' => 'test-takimi-'.uniqid(),
            'short_name' => 'TEST',
            'primary_color' => '#000000',
            'secondary_color' => '#ffffff',
            'status' => TeamStatus::Active,
        ]);
    }
}
