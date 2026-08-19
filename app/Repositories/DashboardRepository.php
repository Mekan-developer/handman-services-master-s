<?php

namespace App\Repositories;

use App\Enums\AnalyticsPeriod;
use App\Enums\SubscriptionStatus;
use App\Models\MasterSubscription;
use Illuminate\Support\Carbon;

class DashboardRepository
{
    private const DAILY_POINTS = 7;

    private const WEEKLY_POINTS = 8;

    private const MONTHLY_POINTS = 12;

    private const YEARLY_POINTS = 5;

    /** Every status except Cancelled — mirrors MasterSubscriptionRepository::stats(). */
    private const NOT_CANCELLED = [
        SubscriptionStatus::Active->value,
        SubscriptionStatus::Pending->value,
        SubscriptionStatus::Expired->value,
    ];

    /**
     * Subscription trend for the dashboard KPI cards and chart, bucketed by period.
     *
     * @return array{dates: array<int, string>, new: array<int, int>, active: array<int, int>, revenue: array<int, float>}
     */
    public function subscriptionSeries(AnalyticsPeriod $period, ?int $year = null): array
    {
        $dates = [];
        $new = [];
        $active = [];
        $revenue = [];

        foreach ($this->buckets($period, $year) as [$start, $end, $refDate]) {
            $dates[] = $refDate->toDateString();
            $new[] = MasterSubscription::whereBetween('created_at', [$start, $end])->count();
            $revenue[] = (float) MasterSubscription::whereIn('status', self::NOT_CANCELLED)
                ->whereBetween('created_at', [$start, $end])
                ->sum('price_paid');
            // Snapshot of coverage as of the bucket's end date, not the current `status`
            // column — a subscription later marked Expired still counted as active on
            // any date within its original starts_at..expires_at window.
            $active[] = MasterSubscription::whereIn('status', self::NOT_CANCELLED)
                ->where('starts_at', '<=', $end)
                ->where('expires_at', '>=', $end)
                ->count();
        }

        return compact('dates', 'new', 'active', 'revenue');
    }

    /**
     * Years with at least one subscription, newest first — always includes the current year.
     *
     * @return array<int, int>
     */
    public function availableYears(): array
    {
        $earliest = MasterSubscription::min('created_at');
        $startYear = $earliest ? Carbon::parse($earliest)->year : now()->year;

        return range(now()->year, $startYear);
    }

    /**
     * @return array<int, array{0: Carbon, 1: Carbon, 2: Carbon}>
     */
    private function buckets(AnalyticsPeriod $period, ?int $year): array
    {
        return match ($period) {
            AnalyticsPeriod::Daily => $this->dailyBuckets(),
            AnalyticsPeriod::Weekly => $this->weeklyBuckets(),
            AnalyticsPeriod::Monthly => $this->monthlyBuckets($year),
            AnalyticsPeriod::Yearly => $this->yearlyBuckets(),
        };
    }

    /** @return array<int, array{0: Carbon, 1: Carbon, 2: Carbon}> */
    private function dailyBuckets(): array
    {
        $buckets = [];

        for ($i = self::DAILY_POINTS - 1; $i >= 0; $i--) {
            $day = now()->subDays($i)->startOfDay();
            $buckets[] = [$day->copy(), $day->copy()->endOfDay(), $day->copy()];
        }

        return $buckets;
    }

    /** @return array<int, array{0: Carbon, 1: Carbon, 2: Carbon}> */
    private function weeklyBuckets(): array
    {
        $buckets = [];
        $currentWeekStart = now()->startOfWeek(Carbon::MONDAY);

        for ($i = self::WEEKLY_POINTS - 1; $i >= 0; $i--) {
            $weekStart = $currentWeekStart->copy()->subWeeks($i);
            $buckets[] = [$weekStart->copy(), $weekStart->copy()->endOfWeek(Carbon::SUNDAY), $weekStart->copy()];
        }

        return $buckets;
    }

    /** @return array<int, array{0: Carbon, 1: Carbon, 2: Carbon}> */
    private function monthlyBuckets(?int $year): array
    {
        if ($year === null) {
            $buckets = [];
            $currentMonthStart = now()->startOfMonth();

            for ($i = self::MONTHLY_POINTS - 1; $i >= 0; $i--) {
                $monthStart = $currentMonthStart->copy()->subMonths($i);
                $buckets[] = [$monthStart->copy(), $monthStart->copy()->endOfMonth(), $monthStart->copy()];
            }

            return $buckets;
        }

        // Don't project months that haven't happened yet for the current year.
        $monthCount = $year === now()->year ? now()->month : 12;
        $buckets = [];

        for ($month = 1; $month <= $monthCount; $month++) {
            $monthStart = Carbon::create($year, $month, 1)->startOfDay();
            $buckets[] = [$monthStart->copy(), $monthStart->copy()->endOfMonth(), $monthStart->copy()];
        }

        return $buckets;
    }

    /** @return array<int, array{0: Carbon, 1: Carbon, 2: Carbon}> */
    private function yearlyBuckets(): array
    {
        $buckets = [];
        $currentYear = now()->year;

        for ($i = self::YEARLY_POINTS - 1; $i >= 0; $i--) {
            $yearStart = Carbon::create($currentYear - $i, 1, 1)->startOfDay();
            $buckets[] = [$yearStart->copy(), $yearStart->copy()->endOfYear(), $yearStart->copy()];
        }

        return $buckets;
    }
}
