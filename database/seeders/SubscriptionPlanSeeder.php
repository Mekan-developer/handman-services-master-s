<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    /** Placeholder prices in manat — the service owner sets the real ones in the admin panel. */
    private const PRICE_MONTHLY = 150.00;

    private const PRICE_QUARTERLY = 400.00;

    private const PRICE_SEMIANNUAL = 750.00;

    /**
     * @var array<int, array{name_ru: string, name_tk: string, duration_days: int, price: float, sort_order: int}>
     */
    private array $plans = [
        [
            'name_ru' => '1 месяц',
            'name_tk' => '1 aý',
            'duration_days' => 30,
            'price' => self::PRICE_MONTHLY,
            'sort_order' => 1,
        ],
        [
            'name_ru' => '3 месяца',
            'name_tk' => '3 aý',
            'duration_days' => 90,
            'price' => self::PRICE_QUARTERLY,
            'sort_order' => 2,
        ],
        [
            'name_ru' => '6 месяцев',
            'name_tk' => '6 aý',
            'duration_days' => 180,
            'price' => self::PRICE_SEMIANNUAL,
            'sort_order' => 3,
        ],
    ];

    public function run(): void
    {
        foreach ($this->plans as $plan) {
            SubscriptionPlan::updateOrCreate(
                ['duration_days' => $plan['duration_days']],
                [
                    'name_ru' => $plan['name_ru'],
                    'name_tk' => $plan['name_tk'],
                    'price' => $plan['price'],
                    'is_active' => true,
                    'sort_order' => $plan['sort_order'],
                ]
            );
        }
    }
}
