<?php

namespace App\Http\Controllers;

use App\Enums\AnalyticsPeriod;
use App\Repositories\DashboardRepository;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardRepository $repository) {}

    public function index(Request $request): Response
    {
        $period = AnalyticsPeriod::tryFrom((string) $request->query('period')) ?? AnalyticsPeriod::Monthly;
        $year = $period === AnalyticsPeriod::Monthly ? ($request->integer('year') ?: null) : null;

        return Inertia::render('Dashboard', [
            'period' => $period->value,
            'year' => $year,
            'availableYears' => $this->repository->availableYears(),
            'series' => $this->repository->subscriptionSeries($period, $year),
        ]);
    }
}
