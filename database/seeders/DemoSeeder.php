<?php

namespace Database\Seeders;

use App\Enums\PostStatus;
use App\Enums\PostType;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['stadium.png', 'matchday.png'] as $file) {
            $source = database_path('seeders/assets/'.$file);
            if (is_file($source)) {
                Storage::disk('public')->put('demo/'.$file, file_get_contents($source));
            }
        }

        $teams = Team::orderBy('id')->get();
        $admin = $this->user('admin@tribun.test', 'admin', 'Sistem Yöneticisi', UserRole::Admin, $teams[0]);
        $fener = $this->user('fener.mod@tribun.test', 'fener.mod', 'Fener Moderatörü', UserRole::Moderator, $teams[0]);
        $gala = $this->user('gala.mod@tribun.test', 'gala.mod', 'Gala Moderatörü', UserRole::Moderator, $teams[1]);
        $multi = $this->user('multi.mod@tribun.test', 'multi.mod', 'Çoklu Takım Moderatörü', UserRole::Moderator, $teams[2]);
        $member = $this->user('uye@tribun.test', 'taraftar', 'Tribün Taraftarı', UserRole::Member, $teams[0]);
        $member2 = $this->user('deniz@tribun.test', 'deniz', 'Deniz Yılmaz', UserRole::Member, $teams[1]);

        $fener->moderatedTeams()->sync([$teams[0]->id]);
        $gala->moderatedTeams()->sync([$teams[1]->id]);
        $multi->moderatedTeams()->sync([$teams[2]->id, $teams[3]->id]);
        $member->followedTeams()->sync([$teams[0]->id, $teams[2]->id]);
        $member2->followedTeams()->sync([$teams[1]->id, $teams[3]->id]);

        $creators = [$fener, $gala, $multi, $multi];
        $messages = [
            ['Yeni sezon hazırlıkları yüksek tempoyla devam ediyor. Takım bugün dayanıklılık ve pas organizasyonları üzerinde çalıştı.', 'Günün antrenmanından kareler. Sahada enerji yüksek, hedefler büyük.', 'Bu akşam tribünün sesi yine bizimle. Maç önü görüşlerinizi yorumlarda paylaşın.', 'Haftanın programı belli oldu. Takımımız çalışmalarına yarın sabah devam edecek.'],
            ['Takımımız yeni hafta hazırlıklarına başladı. İlk bölümde kondisyon, ikinci bölümde taktik çalışma yapıldı.', 'Birlikte daha güçlüyüz. Sezonun en önemli virajlarından birine hazırlanıyoruz.', 'Maç günü atmosferi şimdiden hissediliyor. Sence karşılaşmanın kilit oyuncusu kim olacak?', 'Altyapıdan gelen genç oyuncular bugün A takımla çalıştı.'],
            ['Siyah ve beyazın peşinde yeni bir hafta. Hazırlıklar tüm hızıyla sürüyor.', 'Takımımız taktik antrenmanda dar alan oyunları gerçekleştirdi.', 'Tribün hazır, takım hazır. Maç önü yorumlarını bekliyoruz.', 'Bugünkü çalışmadan öne çıkan notlar topluluk sayfamızda.'],
            ['Yeni haftaya güçlü bir başlangıç. Takımımız sahada yoğun tempoda çalıştı.', 'Bordo mavi enerji antrenmanın her anındaydı.', 'Maç öncesi son hazırlıklar tamamlandı. Senin ilk on birin nasıl?', 'Genç oyuncuların performansı teknik ekibin yüzünü güldürdü.'],
        ];

        foreach ($teams as $index => $team) {
            foreach ($messages[$index] as $position => $body) {
                Post::create([
                    'team_id' => $team->id,
                    'created_by' => $creators[$index]->id,
                    'type' => $position === 3 ? PostType::Text : PostType::Image,
                    'body' => $body,
                    'image_path' => $position === 3 ? null : ($position % 2 === 0 ? 'demo/stadium.png' : 'demo/matchday.png'),
                    'status' => PostStatus::Published,
                    'published_at' => now()->subHours(($index * 5) + $position + 1),
                ]);
            }
        }

        Post::published()->get()->each(function (Post $post) use ($member, $member2, $admin): void {
            $comment = Comment::create(['post_id' => $post->id, 'user_id' => $member->id, 'body' => 'Enerji harika görünüyor, başarılar!']);
            Comment::create(['post_id' => $post->id, 'user_id' => $member2->id, 'parent_id' => $comment->id, 'body' => 'Kesinlikle, güzel bir maç bekliyorum.']);
            $post->likes()->createMany([['user_id' => $member->id], ['user_id' => $member2->id], ['user_id' => $admin->id]]);
            $comment->likes()->create(['user_id' => $member2->id]);
        });
    }

    private function user(string $email, string $username, string $name, UserRole $role, Team $favorite): User
    {
        return User::updateOrCreate(['email' => $email], [
            'username' => $username,
            'name' => $name,
            'password' => Hash::make('password'),
            'favorite_team_id' => $favorite->id,
            'role' => $role,
            'status' => UserStatus::Active,
            'bio' => 'Futbolu toplulukla birlikte yaşayan bir taraftar.',
            'email_verified_at' => now(),
        ]);
    }
}
