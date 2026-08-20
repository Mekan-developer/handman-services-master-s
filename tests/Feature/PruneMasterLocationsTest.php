<?php

namespace Tests\Feature;

use App\Models\Master;
use App\Models\MasterLocation;
use App\Models\Order;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class PruneMasterLocationsTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function ping(Master $master, string $recordedAt, ?Order $order = null): MasterLocation
    {
        return MasterLocation::factory()->create([
            'master_id' => $master->id,
            'order_id' => $order?->id,
            'recorded_at' => $recordedAt,
        ]);
    }

    public function test_it_deletes_stale_untagged_pings(): void
    {
        $master = Master::factory()->create();
        $stale = $this->ping($master, now()->subDays(30)->toDateTimeString());
        $recent = $this->ping($master, now()->subHours(2)->toDateTimeString());

        $this->artisan('locations:prune')->assertSuccessful();

        $this->assertDatabaseMissing('master_locations', ['id' => $stale->id]);
        $this->assertDatabaseHas('master_locations', ['id' => $recent->id]);
    }

    /** Without a dot on the map a master looks like they never existed. */
    public function test_it_never_deletes_a_masters_newest_ping(): void
    {
        $master = Master::factory()->create();
        $onlyPing = $this->ping($master, now()->subYear()->toDateTimeString());

        $this->artisan('locations:prune')->assertSuccessful();

        $this->assertDatabaseHas('master_locations', ['id' => $onlyPing->id]);
    }

    public function test_it_keeps_pings_of_a_recently_closed_order(): void
    {
        $master = Master::factory()->create();
        $order = Order::factory()->forMaster($master)->completed()->create([
            'completed_at' => now()->subDays(3),
        ]);
        $ping = $this->ping($master, now()->subDays(3)->toDateTimeString(), $order);

        $this->artisan('locations:prune')->assertSuccessful();

        $this->assertDatabaseHas('master_locations', ['id' => $ping->id]);
    }

    public function test_it_deletes_pings_of_a_long_closed_order(): void
    {
        $master = Master::factory()->create();
        $order = Order::factory()->forMaster($master)->completed()->create([
            'completed_at' => now()->subDays(60),
        ]);
        $ping = $this->ping($master, now()->subDays(60)->toDateTimeString(), $order);

        // A newer untagged ping keeps the "never delete the latest" rule from
        // shielding the row under test.
        $this->ping($master, now()->toDateTimeString());

        $this->artisan('locations:prune')->assertSuccessful();

        $this->assertDatabaseMissing('master_locations', ['id' => $ping->id]);
    }

    public function test_it_deletes_pings_of_a_long_cancelled_order(): void
    {
        $master = Master::factory()->create();
        $order = Order::factory()->forMaster($master)->cancelled()->create([
            'cancelled_at' => now()->subDays(45),
        ]);
        $ping = $this->ping($master, now()->subDays(45)->toDateTimeString(), $order);
        $this->ping($master, now()->toDateTimeString());

        $this->artisan('locations:prune')->assertSuccessful();

        $this->assertDatabaseMissing('master_locations', ['id' => $ping->id]);
    }

    /** An open job is evidence in the making — age alone must not take it. */
    public function test_it_keeps_pings_of_an_order_that_is_still_running(): void
    {
        $master = Master::factory()->create();
        $order = Order::factory()->forMaster($master)->inProgress()->create();
        $ping = $this->ping($master, now()->subDays(90)->toDateTimeString(), $order);
        $this->ping($master, now()->toDateTimeString());

        $this->artisan('locations:prune')->assertSuccessful();

        $this->assertDatabaseHas('master_locations', ['id' => $ping->id]);
    }

    public function test_retention_windows_are_configurable(): void
    {
        $master = Master::factory()->create();
        $ping = $this->ping($master, now()->subDays(3)->toDateTimeString());
        $this->ping($master, now()->toDateTimeString());

        $this->artisan('locations:prune', ['--ping-days' => 1])->assertSuccessful();

        $this->assertDatabaseMissing('master_locations', ['id' => $ping->id]);
    }

    public function test_it_rejects_a_zero_retention_window(): void
    {
        $this->artisan('locations:prune', ['--ping-days' => 0])->assertFailed();
    }

    public function test_it_deletes_across_chunk_boundaries(): void
    {
        $master = Master::factory()->create();
        MasterLocation::factory()->count(30)->create([
            'master_id' => $master->id,
            'recorded_at' => now()->subDays(30),
        ]);
        $this->ping($master, now()->toDateTimeString());

        $this->artisan('locations:prune')->assertSuccessful();

        // Only the fresh ping survives.
        $this->assertDatabaseCount('master_locations', 1);
    }
}
