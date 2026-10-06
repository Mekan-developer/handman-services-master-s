<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\CompleteMasterOrderAction;
use App\Actions\DeclineOrderAction;
use App\Actions\RespondToOrderAction;
use App\Actions\StartMasterOrderAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CategoryOrdersRequest;
use App\Http\Resources\Api\V1\AvailableOrderResource;
use App\Http\Resources\Api\V1\CategoryOrderResource;
use App\Http\Resources\Api\V1\MasterOrderResource;
use App\Http\Resources\Api\V1\OrderMasterResponseResource;
use App\Models\Master;
use App\Repositories\MasterRepository;
use App\Repositories\OrderRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MasterOrderController extends Controller
{
    public function __construct(
        private readonly OrderRepository $repository,
        private readonly MasterRepository $masterRepository,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var Master $master */
        $master = $request->user();

        $orders = $this->repository->forMaster($master, $request->query('filter'));

        return MasterOrderResource::collection($orders);
    }

    /**
     * Unclaimed orders currently reachable from the master's last known position.
     *
     * A master who has never sent a GPS ping gets an empty list rather than an
     * error — it is a normal state right after login, not a failure.
     */
    public function available(Request $request): AnonymousResourceCollection
    {
        /** @var Master $master */
        $master = $request->user();

        $location = $this->masterRepository->latestLocation($master);

        if ($location === null) {
            return AvailableOrderResource::collection([]);
        }

        $orders = $this->repository->availableForMaster(
            $master,
            (float) $location->latitude,
            (float) $location->longitude,
        );

        return AvailableOrderResource::collection($orders);
    }

    /**
     * Every open order in the master's categories, near or far. Distance and
     * `is_within_radius` are filled only once the master has sent a GPS ping.
     */
    public function byCategory(CategoryOrdersRequest $request): AnonymousResourceCollection
    {
        /** @var Master $master */
        $master = $request->user();

        $location = $this->masterRepository->latestLocation($master);

        $orders = $this->repository->openInMasterCategories(
            $master,
            $request->categoryId(),
            $location ? (float) $location->latitude : null,
            $location ? (float) $location->longitude : null,
        );

        return CategoryOrderResource::collection($orders);
    }

    /** Respond to an offered order. The order stays open — the client decides who gets it. */
    public function respond(Request $request, int $id, RespondToOrderAction $action): JsonResponse
    {
        /** @var Master $master */
        $master = $request->user();

        $order = $this->repository->findOrFail($id);

        $response = $action->handle($master, $order);

        return (new OrderMasterResponseResource($response))
            ->response()
            ->setStatusCode(201);
    }

    /** Hide an offered order from this master's feed without affecting other masters. */
    public function decline(Request $request, int $id, DeclineOrderAction $action): JsonResponse
    {
        /** @var Master $master */
        $master = $request->user();

        $order = $this->repository->findOrFail($id);

        $action->handle($master, $order);

        return response()->json(null, 204);
    }

    public function show(Request $request, int $id): MasterOrderResource
    {
        /** @var Master $master */
        $master = $request->user();

        $order = $this->repository->findForMasterOrFail($id, $master);

        return new MasterOrderResource($order);
    }

    public function start(Request $request, int $id, StartMasterOrderAction $action): JsonResponse
    {
        /** @var Master $master */
        $master = $request->user();

        $order = $this->repository->findForMasterOrFail($id, $master);

        $updated = $action->handle($master, $order);

        return (new MasterOrderResource($updated))->response();
    }

    public function complete(Request $request, int $id, CompleteMasterOrderAction $action): JsonResponse
    {
        /** @var Master $master */
        $master = $request->user();

        $order = $this->repository->findForMasterOrFail($id, $master);

        $updated = $action->handle($master, $order);

        return (new MasterOrderResource($updated))->response();
    }
}
