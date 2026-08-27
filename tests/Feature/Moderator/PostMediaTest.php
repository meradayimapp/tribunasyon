<?php

namespace Tests\Feature\Moderator;

use App\Enums\PostStatus;
use App\Enums\PostType;
use App\Enums\TeamStatus;
use App\Enums\UserRole;
use App\Models\Post;
use App\Models\Team;
use App\Models\User;
use App\Services\PostMediaBackfillService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PostMediaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_moderator_can_create_post_with_single_image(): void
    {
        [$team, $moderator] = $this->moderatedTeam();

        $this->actingAs($moderator)
            ->post(route('moderator.posts.store'), $this->createPayload($team, $this->images(1)))
            ->assertRedirect(route('moderator.posts.index'));

        $post = Post::latest('id')->firstOrFail();
        $this->assertSame(PostType::Image, $post->type);
        $this->assertCount(1, $post->media);
        Storage::disk('public')->assertExists($post->media->first()->path);
    }

    public function test_moderator_can_create_post_with_multiple_images(): void
    {
        [$team, $moderator] = $this->moderatedTeam();

        $this->actingAs($moderator)
            ->post(route('moderator.posts.store'), $this->createPayload($team, $this->images(3)))
            ->assertRedirect(route('moderator.posts.index'));

        $this->assertCount(3, Post::latest('id')->firstOrFail()->media);
    }

    public function test_maximum_ten_images_are_accepted(): void
    {
        [$team, $moderator] = $this->moderatedTeam();

        $this->actingAs($moderator)
            ->post(route('moderator.posts.store'), $this->createPayload($team, $this->images(10)))
            ->assertSessionHasNoErrors();

        $this->assertCount(10, Post::latest('id')->firstOrFail()->media);
    }

    public function test_eleventh_image_is_rejected(): void
    {
        [$team, $moderator] = $this->moderatedTeam();

        $this->actingAs($moderator)
            ->post(route('moderator.posts.store'), $this->createPayload($team, $this->images(11)))
            ->assertSessionHasErrors('images');

        $this->assertDatabaseCount('posts', 0);
        $this->assertSame([], Storage::disk('public')->allFiles('posts'));
    }

    public function test_images_are_stored_with_correct_sort_order(): void
    {
        [$team, $moderator] = $this->moderatedTeam();
        $images = $this->images(3);
        $expectedPaths = collect($images)->map(fn (UploadedFile $image): string => 'posts/'.$image->hashName())->all();

        $this->actingAs($moderator)->post(route('moderator.posts.store'), $this->createPayload($team, $images));

        $media = Post::latest('id')->firstOrFail()->media;
        $this->assertSame([1, 2, 3], $media->pluck('sort_order')->all());
        $this->assertSame($expectedPaths, $media->pluck('path')->all());
    }

    public function test_existing_media_can_be_reordered(): void
    {
        [$team, $moderator] = $this->moderatedTeam();
        $post = $this->createPost($team, $moderator);
        $first = $post->media()->create(['type' => 'image', 'path' => 'posts/first.jpg', 'sort_order' => 1]);
        $second = $post->media()->create(['type' => 'image', 'path' => 'posts/second.jpg', 'sort_order' => 2]);
        $third = $post->media()->create(['type' => 'image', 'path' => 'posts/third.jpg', 'sort_order' => 3]);

        $this->actingAs($moderator)
            ->put(route('moderator.posts.update', $post), $this->updatePayload($post, ["existing:{$third->id}", "existing:{$first->id}", "existing:{$second->id}"]))
            ->assertRedirect(route('moderator.posts.index'));

        $this->assertSame([$third->id, $first->id, $second->id], $post->fresh()->media->pluck('id')->all());
        $this->assertSame([1, 2, 3], $post->fresh()->media->pluck('sort_order')->all());
    }

    public function test_single_media_can_be_removed_and_its_file_is_deleted(): void
    {
        [$team, $moderator] = $this->moderatedTeam();
        $post = $this->createPost($team, $moderator);
        Storage::disk('public')->put('posts/keep.jpg', 'keep');
        Storage::disk('public')->put('posts/remove.jpg', 'remove');
        $keep = $post->media()->create(['type' => 'image', 'path' => 'posts/keep.jpg', 'sort_order' => 1]);
        $remove = $post->media()->create(['type' => 'image', 'path' => 'posts/remove.jpg', 'sort_order' => 2]);

        $this->actingAs($moderator)
            ->put(route('moderator.posts.update', $post), $this->updatePayload($post, ["existing:{$keep->id}"]))
            ->assertRedirect(route('moderator.posts.index'));

        $this->assertDatabaseMissing('post_media', ['id' => $remove->id]);
        Storage::disk('public')->assertMissing('posts/remove.jpg');
        Storage::disk('public')->assertExists('posts/keep.jpg');
    }

    public function test_removing_media_does_not_delete_file_used_by_another_post(): void
    {
        [$team, $moderator] = $this->moderatedTeam();
        $post = $this->createPost($team, $moderator);
        $otherPost = $this->createPost($team, $moderator);
        Storage::disk('public')->put('posts/shared.jpg', 'shared');
        $media = $post->media()->create(['type' => 'image', 'path' => 'posts/shared.jpg', 'sort_order' => 1]);
        $otherPost->media()->create(['type' => 'image', 'path' => 'posts/shared.jpg', 'sort_order' => 1]);

        $this->actingAs($moderator)
            ->put(route('moderator.posts.update', $post), $this->updatePayload($post, []))
            ->assertRedirect(route('moderator.posts.index'));

        $this->assertDatabaseMissing('post_media', ['id' => $media->id]);
        Storage::disk('public')->assertExists('posts/shared.jpg');
    }

    public function test_new_image_can_be_added_to_existing_post(): void
    {
        [$team, $moderator] = $this->moderatedTeam();
        $post = $this->createPost($team, $moderator);
        $existing = $post->media()->create(['type' => 'image', 'path' => 'posts/existing.jpg', 'sort_order' => 1]);
        $newImage = $this->images(1)[0];
        $payload = $this->updatePayload($post, ["existing:{$existing->id}", 'new:0']);
        $payload['images'] = [$newImage];

        $this->actingAs($moderator)
            ->put(route('moderator.posts.update', $post), $payload)
            ->assertRedirect(route('moderator.posts.index'));

        $this->assertSame(['posts/existing.jpg', 'posts/'.$newImage->hashName()], $post->fresh()->media->pluck('path')->all());
    }

    public function test_other_team_moderator_cannot_change_media(): void
    {
        [$team, $owner] = $this->moderatedTeam('Sahip Takım', 'sahip');
        [, $otherModerator] = $this->moderatedTeam('Diğer Takım', 'diger');
        $post = $this->createPost($team, $owner);
        $media = $post->media()->create(['type' => 'image', 'path' => 'posts/protected.jpg', 'sort_order' => 1]);

        $this->actingAs($otherModerator)
            ->put(route('moderator.posts.update', $post), $this->updatePayload($post, ["existing:{$media->id}"]))
            ->assertForbidden();

        $this->assertDatabaseHas('post_media', ['id' => $media->id, 'sort_order' => 1]);
    }

    public function test_media_id_from_another_post_is_rejected(): void
    {
        [$team, $moderator] = $this->moderatedTeam();
        $post = $this->createPost($team, $moderator);
        $otherPost = $this->createPost($team, $moderator);
        $ownMedia = $post->media()->create(['type' => 'image', 'path' => 'posts/own.jpg', 'sort_order' => 1]);
        $otherMedia = $otherPost->media()->create(['type' => 'image', 'path' => 'posts/other.jpg', 'sort_order' => 1]);

        $this->actingAs($moderator)
            ->put(route('moderator.posts.update', $post), $this->updatePayload($post, ["existing:{$otherMedia->id}"]))
            ->assertSessionHasErrors('media_order');

        $this->assertDatabaseHas('post_media', ['id' => $ownMedia->id, 'post_id' => $post->id, 'sort_order' => 1]);
        $this->assertDatabaseHas('post_media', ['id' => $otherMedia->id, 'post_id' => $otherPost->id, 'sort_order' => 1]);
    }

    public function test_single_image_does_not_render_carousel_counter(): void
    {
        [$team, $moderator] = $this->moderatedTeam();
        $post = $this->createPost($team, $moderator);
        $post->media()->create(['type' => 'image', 'path' => 'posts/single.jpg', 'sort_order' => 1]);

        $this->get(route('posts.show', [$team, $post]))
            ->assertOk()
            ->assertSee('data-media-total="1"', false)
            ->assertDontSee('post-media-counter', false);
    }

    public function test_three_image_post_renders_total_media_information(): void
    {
        [$team, $moderator] = $this->moderatedTeam();
        $post = $this->createPost($team, $moderator);

        foreach (range(1, 3) as $order) {
            $post->media()->create(['type' => 'image', 'path' => "posts/{$order}.jpg", 'sort_order' => $order]);
        }

        $this->get(route('posts.show', [$team, $post]))
            ->assertOk()
            ->assertSee('data-media-total="3"', false)
            ->assertSee('post-media-counter', false)
            ->assertSee('/3');
    }

    public function test_search_result_uses_only_first_ordered_media(): void
    {
        [$team, $moderator] = $this->moderatedTeam();
        $post = $this->createPost($team, $moderator, ['body' => 'Aramada kapak medya kontrolü']);
        $post->media()->create(['type' => 'image', 'path' => 'posts/cover.jpg', 'sort_order' => 1]);
        $post->media()->create(['type' => 'image', 'path' => 'posts/second.jpg', 'sort_order' => 2]);

        $this->get(route('search.index', ['q' => 'kapak medya']))
            ->assertOk()
            ->assertSee(Storage::url('posts/cover.jpg'), false)
            ->assertDontSee(Storage::url('posts/second.jpg'), false);
    }

    public function test_legacy_single_image_is_backfilled_without_data_loss(): void
    {
        [$team, $moderator] = $this->moderatedTeam();
        $post = $this->createPost($team, $moderator, ['image_path' => 'posts/legacy.jpg']);

        app(PostMediaBackfillService::class)->run();
        app(PostMediaBackfillService::class)->run();

        $this->assertDatabaseHas('post_media', ['post_id' => $post->id, 'path' => 'posts/legacy.jpg', 'sort_order' => 1]);
        $this->assertSame(1, $post->media()->count());
        $this->assertSame('posts/legacy.jpg', $post->fresh()->image_path);
    }

    public function test_soft_deleting_post_keeps_media_for_restore(): void
    {
        [$team, $moderator] = $this->moderatedTeam();
        $post = $this->createPost($team, $moderator);
        Storage::disk('public')->put('posts/restorable.jpg', 'image');
        $media = $post->media()->create(['type' => 'image', 'path' => 'posts/restorable.jpg', 'sort_order' => 1]);

        $this->actingAs($moderator)->delete(route('moderator.posts.destroy', $post))->assertRedirect();

        $this->assertSoftDeleted($post);
        $this->assertDatabaseHas('post_media', ['id' => $media->id]);
        Storage::disk('public')->assertExists('posts/restorable.jpg');
    }

    public function test_force_deleting_post_removes_unshared_media_file(): void
    {
        [$team, $moderator] = $this->moderatedTeam();
        $post = $this->createPost($team, $moderator);
        Storage::disk('public')->put('posts/permanent.jpg', 'image');
        $post->media()->create(['type' => 'image', 'path' => 'posts/permanent.jpg', 'sort_order' => 1]);

        $post->forceDelete();

        $this->assertDatabaseMissing('post_media', ['post_id' => $post->id]);
        Storage::disk('public')->assertMissing('posts/permanent.jpg');
    }

    private function moderatedTeam(string $name = 'Medya Takımı', string $slug = 'medya-takimi'): array
    {
        $team = Team::create([
            'name' => $name,
            'slug' => $slug,
            'short_name' => strtoupper(substr($slug, 0, 3)),
            'primary_color' => '#172554',
            'secondary_color' => '#ffffff',
            'status' => TeamStatus::Active,
        ]);
        $moderator = User::factory()->create(['role' => UserRole::Moderator]);
        $moderator->moderatedTeams()->attach($team);

        return [$team, $moderator];
    }

    private function createPost(Team $team, User $moderator, array $attributes = []): Post
    {
        return Post::create(array_merge([
            'team_id' => $team->id,
            'created_by' => $moderator->id,
            'type' => PostType::Text,
            'body' => 'Çoklu medya gönderisi.',
            'status' => PostStatus::Published,
            'published_at' => now(),
        ], $attributes));
    }

    private function images(int $count): array
    {
        return collect(range(1, $count))
            ->map(fn (int $index): UploadedFile => UploadedFile::fake()->image("image-{$index}.jpg", 1080, 1350)->size(300))
            ->all();
    }

    private function createPayload(Team $team, array $images): array
    {
        return [
            'team_id' => $team->id,
            'body' => 'Yeni çoklu medya gönderisi.',
            'status' => PostStatus::Published->value,
            'images' => $images,
        ];
    }

    private function updatePayload(Post $post, array $order): array
    {
        return [
            'team_id' => $post->team_id,
            'body' => $post->body,
            'status' => PostStatus::Published->value,
            'media_editor_present' => '1',
            'media_order' => $order,
        ];
    }
}
