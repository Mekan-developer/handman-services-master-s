<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Oblast;
use Database\Seeders\CitySeeder;
use Database\Seeders\OblastSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CitySeederTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_cities_are_linked_to_oblasts_by_name_on_empty_database(): void
    {
        $this->seed([OblastSeeder::class, CitySeeder::class]);

        $this->assertSame(16, City::count());
        $this->assertSame('Ahal', City::where('name', 'Ашхабад')->firstOrFail()->oblast->name);
        $this->assertSame('Mary', City::where('name', 'Мары')->firstOrFail()->oblast->name);
        $this->assertSame('Lebap', City::where('name', 'Туркменабат')->firstOrFail()->oblast->name);
    }

    public function test_cities_are_linked_correctly_when_oblast_ids_are_shifted(): void
    {
        Oblast::create(['name' => 'Temporary', 'is_active' => true])->delete();

        $this->seed([OblastSeeder::class, CitySeeder::class]);

        $this->assertSame('Lebap', City::where('name', 'Газачак')->firstOrFail()->oblast->name);
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed([OblastSeeder::class, CitySeeder::class]);
        $this->seed([OblastSeeder::class, CitySeeder::class]);

        $this->assertSame(16, City::count());
        $this->assertSame(5, Oblast::count());
    }

    public function test_seeder_fails_when_oblasts_are_missing(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->seed(CitySeeder::class);
    }
}
