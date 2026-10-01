<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Oblast;
use Illuminate\Database\Seeder;

class CitySeeder extends Seeder
{
    public function run(): void
    {
        $citiesByOblast = [
            'Ahal' => ['Ашхабад', 'Аннау', 'Бахарлы', 'Теджен'],
            'Mary' => ['Мары', 'Байрамали', 'Йолётен'],
            'Daşoguz' => ['Дашогуз', 'Куняургенч', 'Акдепе'],
            'Balkan' => ['Балканабат', 'Туркменбаши', 'Гарабогаз'],
            'Lebap' => ['Туркменабат', 'Сейди', 'Газачак'],
        ];

        foreach ($citiesByOblast as $oblastName => $cityNames) {
            $oblast = Oblast::where('name', $oblastName)->firstOrFail();

            foreach ($cityNames as $cityName) {
                City::updateOrCreate(
                    ['name' => $cityName],
                    ['is_active' => true, 'oblast_id' => $oblast->id],
                );
            }
        }
    }
}
