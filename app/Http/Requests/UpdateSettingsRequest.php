<?php

namespace App\Http\Requests;

use App\Models\Setting;
use App\Repositories\SettingRepository;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'master_app_rules' => ['nullable', 'string'],
            'client_app_rules' => ['nullable', 'string'],
            // `sometimes` keeps the settings endpoint partial-updatable: a card that
            // only submits the app rules must not wipe the radius configuration.
            'master_search_initial_radius_km' => ['sometimes', 'required', 'integer', 'min:1', 'max:1000'],
            'master_search_max_radius_km' => ['sometimes', 'required', 'integer', 'min:1', 'max:1000'],
        ];
    }

    /**
     * The two radii constrain each other, but either may be absent from a partial
     * submit — compare the effective values (submitted, else already stored).
     *
     * @return array<int, callable>
     */
    public function after(SettingRepository $settings): array
    {
        return [
            function (Validator $validator) use ($settings): void {
                if ($validator->errors()->hasAny([
                    'master_search_initial_radius_km',
                    'master_search_max_radius_km',
                ])) {
                    return;
                }

                $stored = $settings->searchRadii();

                $initial = (int) $this->input('master_search_initial_radius_km', $stored['initial']);
                $max = (int) $this->input('master_search_max_radius_km', $stored['max']);

                if ($initial > $max) {
                    $validator->errors()->add(
                        'master_search_initial_radius_km',
                        (string) __('validation.custom.master_search_initial_radius_km.lte_max')
                    );
                }
            },
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            Setting::MASTER_SEARCH_INITIAL_RADIUS_KM => (string) __('settings.auto_search.initial_radius'),
            Setting::MASTER_SEARCH_MAX_RADIUS_KM => (string) __('settings.auto_search.max_radius'),
        ];
    }
}
