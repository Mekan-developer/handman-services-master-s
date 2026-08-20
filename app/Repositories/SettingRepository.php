<?php

namespace App\Repositories;

use App\Models\Setting;

class SettingRepository
{
    public function get(string $key, ?string $default = null): ?string
    {
        $value = Setting::where('key', $key)->value('value');

        return $value === null || $value === '' ? $default : $value;
    }

    public function set(string $key, ?string $value): void
    {
        Setting::updateOrCreate(['key' => $key], ['value' => $value]);
    }

    /** Rules/terms of the mobile app — raw HTML, empty string when never filled in. */
    public function clientAppRules(): string
    {
        return $this->get(Setting::CLIENT_APP_RULES) ?? '';
    }

    /**
     * Both auto-search radii in a single round trip — the scheduler reads them
     * once per tick and hands them to every order it processes.
     *
     * @return array{initial: int, max: int}
     */
    public function searchRadii(): array
    {
        $values = Setting::whereIn('key', [
            Setting::MASTER_SEARCH_INITIAL_RADIUS_KM,
            Setting::MASTER_SEARCH_MAX_RADIUS_KM,
        ])->pluck('value', 'key');

        return [
            'initial' => (int) ($values->get(Setting::MASTER_SEARCH_INITIAL_RADIUS_KM)
                ?: Setting::DEFAULT_SEARCH_INITIAL_RADIUS_KM),
            'max' => (int) ($values->get(Setting::MASTER_SEARCH_MAX_RADIUS_KM)
                ?: Setting::DEFAULT_SEARCH_MAX_RADIUS_KM),
        ];
    }

    /** Hours a pending order can wait for a client-approved response before it auto-cancels. */
    public function orderAutoCancelHours(): int
    {
        return (int) ($this->get(Setting::ORDER_AUTO_CANCEL_HOURS) ?: Setting::DEFAULT_ORDER_AUTO_CANCEL_HOURS);
    }
}
