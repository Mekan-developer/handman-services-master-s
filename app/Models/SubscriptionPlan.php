<?php

namespace App\Models;

use Database\Factories\SubscriptionPlanFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SubscriptionPlan extends Model
{
    /** @use HasFactory<SubscriptionPlanFactory> */
    use HasFactory, SoftDeletes;

    /** @var array<int, string> */
    protected $fillable = [
        'name_ru',
        'name_tk',
        'description_ru',
        'description_tk',
        'duration_days',
        'price',
        'is_active',
        'sort_order',
    ];

    /** @var array<int, string> */
    protected $appends = ['name', 'description'];

    protected function casts(): array
    {
        return [
            'duration_days' => 'integer',
            'price' => 'decimal:2',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /** Localized plan name resolved from the current app locale, falling back to Russian. */
    protected function name(): Attribute
    {
        return Attribute::get(fn (): string => $this->localized('name') ?? '');
    }

    protected function description(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->localized('description'));
    }

    /**
     * Pick the `{attribute}_{locale}` value for the active locale, falling back
     * to the Russian variant when the localized one is empty or not loaded.
     */
    private function localized(string $attribute): ?string
    {
        $locale = app()->getLocale();

        return $this->attributes["{$attribute}_{$locale}"]
            ?? $this->attributes["{$attribute}_ru"]
            ?? null;
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(MasterSubscription::class);
    }
}
