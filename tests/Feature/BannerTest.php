<?php

namespace Tests\Feature;

use App\Models\Banner;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BannerTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function actingAsAdmin(): User
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        return $user;
    }

    // ── Index ─────────────────────────────────────────────────────────────────

    public function test_banners_index_requires_authentication(): void
    {
        $this->get(route('banners.index'))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_banners_index(): void
    {
        $this->actingAsAdmin();
        Banner::factory()->count(2)->create();

        $this->get(route('banners.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Banners/Index')->has('banners'));
    }

    // ── Store ─────────────────────────────────────────────────────────────────

    public function test_user_can_create_a_banner(): void
    {
        Storage::fake('public');
        $this->actingAsAdmin();

        $file = UploadedFile::fake()->image('banner.jpg', 1200, 400);

        $this->post(route('banners.store'), [
            'image' => $file,
            'url' => 'https://example.com',
            'is_active' => true,
            'sort_order' => 0,
        ])->assertRedirect(route('banners.index'));

        $this->assertDatabaseHas('banners', ['url' => 'https://example.com', 'is_active' => true]);
    }

    public function test_banner_store_requires_image(): void
    {
        $this->actingAsAdmin();

        $this->post(route('banners.store'), [
            'url' => 'https://example.com',
            'is_active' => true,
        ])->assertSessionHasErrors('image');
    }

    public function test_banner_store_accepts_null_url(): void
    {
        Storage::fake('public');
        $this->actingAsAdmin();

        $file = UploadedFile::fake()->image('banner.jpg', 1200, 400);

        $this->post(route('banners.store'), [
            'image' => $file,
            'url' => null,
            'is_active' => true,
        ])->assertRedirect(route('banners.index'));

        $this->assertDatabaseHas('banners', ['url' => null]);
    }

    // ── Update ────────────────────────────────────────────────────────────────

    public function test_user_can_update_a_banner_without_changing_image(): void
    {
        Storage::fake('public');
        $this->actingAsAdmin();
        $banner = Banner::factory()->create(['url' => 'https://old.com', 'is_active' => true]);

        $this->post(route('banners.update', $banner), [
            'url' => 'https://new.com',
            'is_active' => false,
        ])->assertRedirect(route('banners.index'));

        $this->assertDatabaseHas('banners', ['id' => $banner->id, 'url' => 'https://new.com', 'is_active' => false]);
    }

    public function test_user_can_update_a_banner_with_new_image(): void
    {
        Storage::fake('public');
        $this->actingAsAdmin();
        $banner = Banner::factory()->create(['image_path' => 'banners/old.jpg']);

        $newFile = UploadedFile::fake()->image('new.jpg', 1200, 400);

        $this->post(route('banners.update', $banner), [
            'image' => $newFile,
            'is_active' => true,
        ])->assertRedirect(route('banners.index'));

        $banner->refresh();
        $this->assertNotEquals('banners/old.jpg', $banner->image_path);
    }

    // ── Toggle ────────────────────────────────────────────────────────────────

    public function test_user_can_toggle_banner_status(): void
    {
        Storage::fake('public');
        $this->actingAsAdmin();
        $banner = Banner::factory()->create(['is_active' => true]);

        $this->post(route('banners.toggle', $banner))->assertRedirect(route('banners.index'));

        $this->assertDatabaseHas('banners', ['id' => $banner->id, 'is_active' => false]);
    }

    // ── Destroy ───────────────────────────────────────────────────────────────

    public function test_user_can_delete_a_banner(): void
    {
        Storage::fake('public');
        $this->actingAsAdmin();
        $banner = Banner::factory()->create();

        $this->delete(route('banners.destroy', $banner))->assertRedirect(route('banners.index'));

        $this->assertDatabaseMissing('banners', ['id' => $banner->id]);
    }

    // ── Access by role ────────────────────────────────────────────────────────

    public function test_manager_can_view_banners_index(): void
    {
        $this->actingAs(User::factory()->manager()->create());

        $this->get(route('banners.index'))->assertOk();
    }

    public function test_manager_can_create_a_banner(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->manager()->create());

        $this->post(route('banners.store'), $this->validPayload())
            ->assertRedirect(route('banners.index'));

        $this->assertDatabaseCount('banners', 1);
    }

    public function test_operator_cannot_view_banners_index(): void
    {
        $this->actingAs(User::factory()->operator()->create());

        $this->get(route('banners.index'))->assertForbiddenRedirect('profile.edit');
    }

    public function test_operator_cannot_create_a_banner(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->operator()->create());

        $this->post(route('banners.store'), $this->validPayload())
            ->assertForbiddenRedirect('profile.edit');

        $this->assertDatabaseCount('banners', 0);
    }

    public function test_operator_cannot_update_a_banner(): void
    {
        $this->actingAs(User::factory()->operator()->create());
        $banner = Banner::factory()->create(['url' => 'https://old.com', 'is_active' => true]);

        $this->post(route('banners.update', $banner), ['url' => 'https://new.com', 'is_active' => true])
            ->assertForbiddenRedirect('profile.edit');

        $this->assertSame('https://old.com', $banner->fresh()->url);
    }

    public function test_operator_cannot_toggle_a_banner(): void
    {
        $this->actingAs(User::factory()->operator()->create());
        $banner = Banner::factory()->active()->create();

        $this->post(route('banners.toggle', $banner))->assertForbiddenRedirect('profile.edit');

        $this->assertTrue($banner->fresh()->is_active);
    }

    public function test_operator_cannot_delete_a_banner(): void
    {
        $this->actingAs(User::factory()->operator()->create());
        $banner = Banner::factory()->create();

        $this->delete(route('banners.destroy', $banner))->assertForbiddenRedirect('profile.edit');

        $this->assertModelExists($banner);
    }

    public function test_guest_cannot_create_a_banner(): void
    {
        $this->post(route('banners.store'), [])->assertRedirect(route('login'));
    }

    // ── Index contents ────────────────────────────────────────────────────────

    public function test_index_sorts_by_sort_order_then_id(): void
    {
        $this->actingAsAdmin();
        $last = Banner::factory()->create(['sort_order' => 5]);
        $firstById = Banner::factory()->create(['sort_order' => 1]);
        $secondById = Banner::factory()->create(['sort_order' => 1]);

        $this->get(route('banners.index'))
            ->assertInertia(fn ($page) => $page
                ->where('banners.data.0.id', $firstById->id)
                ->where('banners.data.1.id', $secondById->id)
                ->where('banners.data.2.id', $last->id));
    }

    public function test_index_includes_inactive_banners(): void
    {
        $this->actingAsAdmin();
        Banner::factory()->active()->create();
        Banner::factory()->inactive()->create();

        $this->get(route('banners.index'))
            ->assertInertia(fn ($page) => $page->has('banners.data', 2));
    }

    public function test_index_exposes_the_banner_fields_the_page_uses(): void
    {
        $this->actingAsAdmin();
        $banner = Banner::factory()->active()->create([
            'image_path' => 'banners/promo.jpg',
            'url' => 'https://example.com',
            'sort_order' => 3,
            'created_at' => '2026-08-20 11:52:00',
        ]);

        $this->get(route('banners.index'))
            ->assertInertia(fn ($page) => $page
                ->where('banners.data.0.id', $banner->id)
                ->where('banners.data.0.image_url', Storage::url('banners/promo.jpg'))
                ->where('banners.data.0.url', 'https://example.com')
                ->where('banners.data.0.is_active', true)
                ->where('banners.data.0.sort_order', 3)
                ->where('banners.data.0.created_at', '20.08.2026 11:52'));
    }

    public function test_index_shows_empty_list_when_there_are_no_banners(): void
    {
        $this->actingAsAdmin();

        $this->get(route('banners.index'))
            ->assertInertia(fn ($page) => $page->has('banners.data', 0));
    }

    // ── Store: files, defaults, notification ──────────────────────────────────

    public function test_store_saves_the_image_on_the_public_disk(): void
    {
        Storage::fake('public');
        $this->actingAsAdmin();

        $this->post(route('banners.store'), $this->validPayload());

        $banner = Banner::firstOrFail();
        $this->assertStringStartsWith('banners/', $banner->image_path);
        Storage::disk('public')->assertExists($banner->image_path);
    }

    public function test_store_defaults_sort_order_to_zero(): void
    {
        Storage::fake('public');
        $this->actingAsAdmin();

        $payload = $this->validPayload();
        unset($payload['sort_order']);

        $this->post(route('banners.store'), $payload)->assertSessionHasNoErrors();

        $this->assertSame(0, Banner::firstOrFail()->sort_order);
    }

    public function test_store_flashes_a_success_notification(): void
    {
        Storage::fake('public');
        $this->actingAsAdmin();

        $this->post(route('banners.store'), $this->validPayload())
            ->assertSessionHas('notification', [
                'type' => 'success',
                'message' => __('notifications.created', ['resource' => __('resources.banner')]),
            ]);
    }

    // ── Store: validation ─────────────────────────────────────────────────────

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function invalidStorePayloads(): array
    {
        return [
            'url is not a url' => [['url' => 'not-a-url'], 'url'],
            'url is too long' => [['url' => 'https://example.com/'.str_repeat('a', 2040)], 'url'],
            'is_active is missing' => [['is_active' => null], 'is_active'],
            'is_active is not boolean' => [['is_active' => 'yes'], 'is_active'],
            'sort_order is negative' => [['sort_order' => -1], 'sort_order'],
            'sort_order is too large' => [['sort_order' => 10000], 'sort_order'],
            'sort_order is not integer' => [['sort_order' => 'first'], 'sort_order'],
        ];
    }

    /**
     * @param  array<string, mixed>  $override
     */
    #[DataProvider('invalidStorePayloads')]
    public function test_store_rejects_invalid_fields(array $override, string $field): void
    {
        Storage::fake('public');
        $this->actingAsAdmin();

        $this->post(route('banners.store'), array_merge($this->validPayload(), $override))
            ->assertSessionHasErrors($field);

        $this->assertDatabaseCount('banners', 0);
    }

    public function test_store_rejects_a_file_that_is_not_an_image(): void
    {
        Storage::fake('public');
        $this->actingAsAdmin();

        $this->post(route('banners.store'), $this->validPayload([
            'image' => UploadedFile::fake()->create('banner.pdf', 100, 'application/pdf'),
        ]))->assertSessionHasErrors('image');
    }

    public function test_store_rejects_an_unsupported_image_format(): void
    {
        Storage::fake('public');
        $this->actingAsAdmin();

        $this->post(route('banners.store'), $this->validPayload([
            'image' => UploadedFile::fake()->image('banner.gif'),
        ]))->assertSessionHasErrors('image');
    }

    public function test_store_rejects_an_image_larger_than_five_megabytes(): void
    {
        Storage::fake('public');
        $this->actingAsAdmin();

        $this->post(route('banners.store'), $this->validPayload([
            'image' => UploadedFile::fake()->image('banner.jpg')->size(5121),
        ]))->assertSessionHasErrors('image');
    }

    public function test_store_accepts_an_image_of_exactly_five_megabytes(): void
    {
        Storage::fake('public');
        $this->actingAsAdmin();

        $this->post(route('banners.store'), $this->validPayload([
            'image' => UploadedFile::fake()->image('banner.webp')->size(5120),
        ]))->assertSessionHasNoErrors();
    }

    // ── Update: files, defaults, validation, notification ─────────────────────

    public function test_update_with_new_image_replaces_the_file_on_disk(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('banners/old.jpg', 'old');
        $this->actingAsAdmin();
        $banner = Banner::factory()->create(['image_path' => 'banners/old.jpg']);

        $this->post(route('banners.update', $banner), [
            'image' => UploadedFile::fake()->image('new.jpg', 1200, 400),
            'is_active' => true,
        ]);

        Storage::disk('public')->assertMissing('banners/old.jpg');
        Storage::disk('public')->assertExists($banner->fresh()->image_path);
    }

    public function test_update_without_image_keeps_the_existing_file(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('banners/keep.jpg', 'keep');
        $this->actingAsAdmin();
        $banner = Banner::factory()->create(['image_path' => 'banners/keep.jpg']);

        $this->post(route('banners.update', $banner), ['url' => 'https://new.com', 'is_active' => true]);

        $this->assertSame('banners/keep.jpg', $banner->fresh()->image_path);
        Storage::disk('public')->assertExists('banners/keep.jpg');
    }

    public function test_update_keeps_sort_order_when_it_is_not_sent(): void
    {
        $this->actingAsAdmin();
        $banner = Banner::factory()->create(['sort_order' => 7]);

        $this->post(route('banners.update', $banner), ['is_active' => true]);

        $this->assertSame(7, $banner->fresh()->sort_order);
    }

    public function test_update_can_change_sort_order(): void
    {
        $this->actingAsAdmin();
        $banner = Banner::factory()->create(['sort_order' => 7]);

        $this->post(route('banners.update', $banner), ['is_active' => true, 'sort_order' => 2]);

        $this->assertSame(2, $banner->fresh()->sort_order);
    }

    public function test_update_clears_the_url_when_it_is_sent_empty(): void
    {
        $this->actingAsAdmin();
        $banner = Banner::factory()->create(['url' => 'https://old.com']);

        $this->post(route('banners.update', $banner), ['url' => '', 'is_active' => true]);

        $this->assertNull($banner->fresh()->url);
    }

    public function test_update_requires_is_active(): void
    {
        $this->actingAsAdmin();
        $banner = Banner::factory()->create();

        $this->post(route('banners.update', $banner), ['url' => 'https://new.com'])
            ->assertSessionHasErrors('is_active');
    }

    public function test_update_rejects_invalid_url(): void
    {
        $this->actingAsAdmin();
        $banner = Banner::factory()->create(['url' => 'https://old.com']);

        $this->post(route('banners.update', $banner), ['url' => 'not-a-url', 'is_active' => true])
            ->assertSessionHasErrors('url');

        $this->assertSame('https://old.com', $banner->fresh()->url);
    }

    public function test_update_rejects_a_file_that_is_not_an_image(): void
    {
        Storage::fake('public');
        $this->actingAsAdmin();
        $banner = Banner::factory()->create(['image_path' => 'banners/old.jpg']);

        $this->post(route('banners.update', $banner), [
            'image' => UploadedFile::fake()->create('banner.pdf', 100, 'application/pdf'),
            'is_active' => true,
        ])->assertSessionHasErrors('image');

        $this->assertSame('banners/old.jpg', $banner->fresh()->image_path);
    }

    public function test_update_flashes_a_success_notification(): void
    {
        $this->actingAsAdmin();
        $banner = Banner::factory()->create();

        $this->post(route('banners.update', $banner), ['is_active' => true])
            ->assertSessionHas('notification', [
                'type' => 'success',
                'message' => __('notifications.updated', ['resource' => __('resources.banner')]),
            ]);
    }

    // ── Toggle ────────────────────────────────────────────────────────────────

    public function test_toggle_activates_an_inactive_banner(): void
    {
        $this->actingAsAdmin();
        $banner = Banner::factory()->inactive()->create();

        $this->post(route('banners.toggle', $banner))->assertRedirect(route('banners.index'));

        $this->assertTrue($banner->fresh()->is_active);
    }

    public function test_toggle_changes_only_the_status(): void
    {
        $this->actingAsAdmin();
        $banner = Banner::factory()->active()->create(['url' => 'https://keep.com', 'sort_order' => 4]);

        $this->post(route('banners.toggle', $banner));

        $fresh = $banner->fresh();
        $this->assertSame('https://keep.com', $fresh->url);
        $this->assertSame(4, $fresh->sort_order);
    }

    // ── Destroy ───────────────────────────────────────────────────────────────

    public function test_delete_removes_the_image_from_disk(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('banners/gone.jpg', 'gone');
        $this->actingAsAdmin();
        $banner = Banner::factory()->create(['image_path' => 'banners/gone.jpg']);

        $this->delete(route('banners.destroy', $banner));

        Storage::disk('public')->assertMissing('banners/gone.jpg');
    }

    public function test_delete_flashes_a_success_notification(): void
    {
        Storage::fake('public');
        $this->actingAsAdmin();
        $banner = Banner::factory()->create();

        $this->delete(route('banners.destroy', $banner))
            ->assertSessionHas('notification', [
                'type' => 'success',
                'message' => __('notifications.deleted', ['resource' => __('resources.banner')]),
            ]);
    }

    // ── Missing banner ────────────────────────────────────────────────────────

    public function test_update_of_missing_banner_returns_not_found(): void
    {
        $this->actingAsAdmin();

        $this->post(route('banners.update', 999999), ['is_active' => true])->assertNotFound();
    }

    public function test_toggle_of_missing_banner_returns_not_found(): void
    {
        $this->actingAsAdmin();

        $this->post(route('banners.toggle', 999999))->assertNotFound();
    }

    public function test_delete_of_missing_banner_returns_not_found(): void
    {
        $this->actingAsAdmin();

        $this->delete(route('banners.destroy', 999999))->assertNotFound();
    }

    /**
     * @param  array<string, mixed>  $override
     * @return array<string, mixed>
     */
    private function validPayload(array $override = []): array
    {
        return array_merge([
            'image' => UploadedFile::fake()->image('banner.jpg', 1200, 400),
            'url' => 'https://example.com',
            'is_active' => true,
            'sort_order' => 0,
        ], $override);
    }
}
