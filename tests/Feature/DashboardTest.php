<?php

namespace Tests\Feature;

use App\Enums\AnalyticsPeriod;
use App\Models\Master;
use App\Models\MasterSubscription;
use App\Models\User;
use App\Repositories\DashboardRepository;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function actingAsAdmin(): User
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        return $user;
    }

    // ── HTTP / Inertia wiring ────────────────────────────────────────────────

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_dashboard_renders_with_default_monthly_period(): void
    {
        $this->actingAsAdmin();

        $this->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard')
                ->where('period', 'monthly')
                ->where('year', null)
                ->has('series.dates', 12)
                ->has('series.new', 12)
                ->has('series.active', 12)
                ->has('series.revenue', 12)
                ->has('availableYears'));
    }

    public function test_period_and_year_query_params_are_reflected_back(): void
    {
        $this->actingAsAdmin();

        $this->get(route('dashboard', ['period' => 'yearly', 'year' => 2020]))
            ->assertInertia(fn ($page) => $page
                ->where('period', 'yearly')
                // year only applies to the monthly view — ignored for other periods.
                ->where('year', null)
                ->has('series.dates', 5));
    }

    // ── Bucketing ────────────────────────────────────────────────────────────

    public function test_daily_period_returns_seven_points_ending_today(): void
    {
        $this->travelTo(now()->setTime(12, 0));

        $series = app(DashboardRepository::class)->subscriptionSeries(AnalyticsPeriod::Daily);

        $this->assertCount(7, $series['dates']);
        $this->assertSame(now()->toDateString(), $series['dates'][6]);
        $this->assertSame(now()->subDays(6)->toDateString(), $series['dates'][0]);
    }

    public function test_monthly_year_filter_caps_at_the_current_month_for_the_current_year(): void
    {
        $this->travelTo(now()->setMonth(3)->setDay(15));

        $series = app(DashboardRepository::class)->subscriptionSeries(AnalyticsPeriod::Monthly, now()->year);

        $this->assertCount(3, $series['dates']);
    }

    public function test_monthly_year_filter_returns_all_twelve_months_for_a_past_year(): void
    {
        $series = app(DashboardRepository::class)
            ->subscriptionSeries(AnalyticsPeriod::Monthly, now()->year - 1);

        $this->assertCount(12, $series['dates']);
    }

    public function test_available_years_includes_the_current_year_even_without_data(): void
    {
        $this->assertSame([now()->year], app(DashboardRepository::class)->availableYears());
    }

    public function test_available_years_spans_back_to_the_earliest_subscription(): void
    {
        MasterSubscription::factory()->forMaster(Master::factory()->create())->create([
            'created_at' => now()->subYears(2),
        ]);

        $years = app(DashboardRepository::class)->availableYears();

        $this->assertSame(now()->year, $years[0]);
        $this->assertSame(now()->year - 2, end($years));
    }

    // ── Metric correctness ──────────────────────────────────────────────────

    public function test_new_subscriptions_are_counted_in_the_bucket_they_were_created_in(): void
    {
        $this->travelTo(now()->setTime(12, 0));
        $master = Master::factory()->create();

        MasterSubscription::factory()->forMaster($master)->create(['created_at' => now()]);
        MasterSubscription::factory()->forMaster($master)->create(['created_at' => now()->subDays(3)]);

        $series = app(DashboardRepository::class)->subscriptionSeries(AnalyticsPeriod::Daily);

        $this->assertSame(1, $series['new'][6]); // today
        $this->assertSame(1, $series['new'][3]); // 3 days ago
        $this->assertSame(0, $series['new'][0]);
    }

    public function test_cancelled_subscriptions_are_excluded_from_revenue_and_active_counts(): void
    {
        $this->travelTo(now()->setTime(12, 0));
        $master = Master::factory()->create();

        MasterSubscription::factory()->forMaster($master)->cancelled()->create([
            'created_at' => now(),
            'price_paid' => 500,
            'starts_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);

        $series = app(DashboardRepository::class)->subscriptionSeries(AnalyticsPeriod::Daily);

        $this->assertSame(0.0, $series['revenue'][6]);
        $this->assertSame(0, $series['active'][6]);
        // Still a real purchase attempt, so it counts toward "new".
        $this->assertSame(1, $series['new'][6]);
    }

    public function test_active_count_reflects_historical_coverage_even_after_the_subscription_later_expires(): void
    {
        $this->travelTo(now()->setTime(12, 0));
        $master = Master::factory()->create();

        // Ran from 5 days ago to 2 days ago — already Expired as of "today", but should
        // still count as active on the days it actually covered.
        MasterSubscription::factory()->forMaster($master)->expired()->create([
            'starts_at' => now()->subDays(5),
            'expires_at' => now()->subDays(2),
        ]);

        $series = app(DashboardRepository::class)->subscriptionSeries(AnalyticsPeriod::Daily);

        $this->assertSame(1, $series['active'][1]); // 5 days ago — the day it started
        $this->assertSame(1, $series['active'][3]); // 3 days ago — fully inside the window
        // 2 days ago it expired at noon, so by end-of-day (the snapshot moment) it had
        // already lapsed for the rest of that day.
        $this->assertSame(0, $series['active'][4]);
        $this->assertSame(0, $series['active'][6]); // today — well past its expiry
    }

    public function test_revenue_sums_price_paid_within_the_bucket(): void
    {
        $this->travelTo(now()->setTime(12, 0));
        $master = Master::factory()->create();

        MasterSubscription::factory()->forMaster($master)->create(['created_at' => now(), 'price_paid' => 150]);
        MasterSubscription::factory()->forMaster($master)->create(['created_at' => now(), 'price_paid' => 200]);

        $series = app(DashboardRepository::class)->subscriptionSeries(AnalyticsPeriod::Daily);

        $this->assertSame(350.0, $series['revenue'][6]);
    }
}
