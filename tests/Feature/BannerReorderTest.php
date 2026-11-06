<?php

namespace Tests\Feature;

use App\Models\Banner;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class BannerReorderTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_admin_can_reorder_banners(): void
    {
        $this->actingAs(User::factory()->administrator()->create());
        [$first, $second, $third] = Banner::factory()->count(3)->sequence(
            ['sort_order' => 1],
            ['sort_order' => 2],
            ['sort_order' => 3],
        )->create();

        $this->post(route('banners.reorder'), ['ids' => [$third->id, $first->id, $second->id]])
            ->assertRedirect(route('banners.index'))
            ->assertSessionHas('notification', [
                'type' => 'success',
                'message' => __('notifications.saved', ['resource' => __('resources.banner_order')]),
            ]);

        $this->assertSame(1, $third->fresh()->sort_order);
        $this->assertSame(2, $first->fresh()->sort_order);
        $this->assertSame(3, $second->fresh()->sort_order);
    }

    public function test_manager_can_reorder_banners(): void
    {
        $this->actingAs(User::factory()->manager()->create());
        [$first, $second] = Banner::factory()->count(2)->create();

        $this->post(route('banners.reorder'), ['ids' => [$second->id, $first->id]])
            ->assertRedirect(route('banners.index'));

        $this->assertSame(1, $second->fresh()->sort_order);
    }

    public function test_index_lists_banners_in_the_new_order_without_pagination(): void
    {
        $this->actingAs(User::factory()->administrator()->create());
        $banners = Banner::factory()->count(20)->create();
        $reversedIds = $banners->pluck('id')->reverse()->values()->all();

        $this->post(route('banners.reorder'), ['ids' => $reversedIds]);

        $this->get(route('banners.index'))
            ->assertInertia(fn ($page) => $page
                ->component('Banners/Index')
                ->has('banners.data', 20)
                ->where('banners.data.0.id', $reversedIds[0])
                ->where('banners.data.19.id', $reversedIds[19]));
    }

    public function test_banners_left_out_of_the_request_keep_their_order(): void
    {
        $this->actingAs(User::factory()->administrator()->create());
        $untouched = Banner::factory()->create(['sort_order' => 50]);
        [$first, $second] = Banner::factory()->count(2)->create();

        $this->post(route('banners.reorder'), ['ids' => [$second->id, $first->id]]);

        $this->assertSame(50, $untouched->fresh()->sort_order);
    }

    public function test_reorder_requires_ids(): void
    {
        $this->actingAs(User::factory()->administrator()->create());

        $this->post(route('banners.reorder'), [])->assertSessionHasErrors('ids');
        $this->post(route('banners.reorder'), ['ids' => []])->assertSessionHasErrors('ids');
    }

    public function test_reorder_rejects_unknown_ids(): void
    {
        $this->actingAs(User::factory()->administrator()->create());
        $banner = Banner::factory()->create(['sort_order' => 7]);

        $this->post(route('banners.reorder'), ['ids' => [$banner->id, 999999]])
            ->assertSessionHasErrors('ids.1');

        $this->assertSame(7, $banner->fresh()->sort_order);
    }

    public function test_reorder_rejects_duplicate_ids(): void
    {
        $this->actingAs(User::factory()->administrator()->create());
        $banner = Banner::factory()->create();

        $this->post(route('banners.reorder'), ['ids' => [$banner->id, $banner->id]])
            ->assertSessionHasErrors(['ids.0', 'ids.1']);
    }

    public function test_reorder_rejects_non_integer_ids(): void
    {
        $this->actingAs(User::factory()->administrator()->create());

        $this->post(route('banners.reorder'), ['ids' => ['abc']])
            ->assertSessionHasErrors('ids.0');
    }

    public function test_guest_cannot_reorder_banners(): void
    {
        $this->post(route('banners.reorder'), ['ids' => [1]])->assertRedirect(route('login'));
    }

    public function test_operator_cannot_reorder_banners(): void
    {
        $this->actingAs(User::factory()->operator()->create());
        [$first, $second] = Banner::factory()->count(2)->sequence(['sort_order' => 1], ['sort_order' => 2])->create();

        $this->post(route('banners.reorder'), ['ids' => [$second->id, $first->id]])
            ->assertForbiddenRedirect('profile.edit');

        $this->assertSame(1, $first->fresh()->sort_order);
    }

    public function test_reorder_route_does_not_clash_with_banner_update(): void
    {
        $this->actingAs(User::factory()->administrator()->create());
        $banner = Banner::factory()->create(['url' => 'https://old.com', 'is_active' => true]);

        $this->post(route('banners.update', $banner), ['url' => 'https://new.com', 'is_active' => true])
            ->assertRedirect(route('banners.index'));

        $this->assertSame('https://new.com', $banner->fresh()->url);
    }
}
