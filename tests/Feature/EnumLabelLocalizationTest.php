<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentModel;
use App\Enums\UserRole;
use Tests\TestCase;

/**
 * Enum labels must come from the lang files, never from hardcoded strings.
 */
class EnumLabelLocalizationTest extends TestCase
{
    /**
     * @return array<string, array{0: string}>
     */
    public static function localeProvider(): array
    {
        return [
            'ru' => ['ru'],
            'tk' => ['tk'],
        ];
    }

    /**
     * @dataProvider localeProvider
     */
    public function test_every_enum_label_has_a_translation(string $locale): void
    {
        $this->app->setLocale($locale);

        $cases = [
            ...OrderStatus::cases(),
            ...PaymentModel::cases(),
            ...UserRole::cases(),
        ];

        foreach ($cases as $case) {
            $label = $case->label();

            $this->assertNotSame(
                '',
                $label,
                sprintf('%s::%s has an empty label in %s', $case::class, $case->name, $locale),
            );

            // A missing key makes the translator echo the key back verbatim.
            $this->assertStringNotContainsString(
                '.',
                $label,
                sprintf('%s::%s falls back to the raw key in %s: %s', $case::class, $case->name, $locale, $label),
            );
        }
    }

    public function test_labels_actually_switch_with_the_locale(): void
    {
        $this->app->setLocale('ru');
        $ru = OrderStatus::Pending->label();
        $ruPayment = PaymentModel::Salary->label();

        $this->app->setLocale('tk');

        $this->assertNotSame($ru, OrderStatus::Pending->label());
        $this->assertNotSame($ruPayment, PaymentModel::Salary->label());
    }
}
