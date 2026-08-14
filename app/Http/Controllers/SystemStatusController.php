<?php

namespace App\Http\Controllers;

use App\Services\SystemStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SystemStatusController extends Controller
{
    public function __construct(private readonly SystemStatusService $systemStatus) {}

    public function __invoke(Request $request): JsonResponse
    {
        return response()->json(
            $this->systemStatus->snapshot(fresh: $request->boolean('fresh'))
        );
    }
}
