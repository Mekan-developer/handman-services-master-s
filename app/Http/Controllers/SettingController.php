<?php

namespace App\Http\Controllers;

use App\Actions\UpdateSettingsAction;
use App\Http\Requests\UpdateSettingsRequest;
use App\Http\Traits\WithNotification;
use App\Repositories\SettingRepository;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SettingController extends Controller
{
    use WithNotification;

    public function __construct(private readonly SettingRepository $repository) {}

    public function index(): Response
    {
        $radii = $this->repository->searchRadii();

        return Inertia::render('Settings/Index', [
            'clientAppRules' => $this->repository->clientAppRules(),
            'masterSearchInitialRadiusKm' => $radii['initial'],
            'masterSearchMaxRadiusKm' => $radii['max'],
            'orderAutoCancelHours' => $this->repository->orderAutoCancelHours(),
            'orderDeclineRestoreMinutes' => $this->repository->orderDeclineRestoreMinutes(),
        ]);
    }

    public function update(UpdateSettingsRequest $request, UpdateSettingsAction $action): RedirectResponse
    {
        $action->handle($request->validated());
        $this->notifySuccess('notifications.updated', ['resource' => __('resources.settings')]);

        return redirect()->route('settings.index');
    }
}
