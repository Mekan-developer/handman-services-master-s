<?php

namespace App\Http\Controllers;

use App\Actions\CreateOrderForClientAction;
use App\Actions\DeleteOrderAction;
use App\Actions\RestartOrderSearchAction;
use App\Actions\UpdateOrderAction;
use App\Actions\UpdateOrderStatusAction;
use App\Enums\OrderStatus;
use App\Exceptions\OrderException;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdateOrderRequest;
use App\Http\Requests\UpdateOrderStatusRequest;
use App\Http\Resources\MasterTrajectoryResource;
use App\Http\Resources\OrderResource;
use App\Http\Traits\WithNotification;
use App\Repositories\CategoryRepository;
use App\Repositories\ClientRepository;
use App\Repositories\MasterLocationRepository;
use App\Repositories\OblastRepository;
use App\Repositories\OrderRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    use WithNotification;

    public function __construct(
        private readonly OrderRepository $repository,
    ) {}

    public function index(Request $request): Response
    {
        $filters = $request->only(['status', 'city_id', 'search', 'date_from', 'date_to']);

        return Inertia::render('Orders/Index', [
            'orders' => OrderResource::collection($this->repository->paginate($filters)),
            'oblasts' => app(OblastRepository::class)->allWithCities(),
            'categories' => app(CategoryRepository::class)->treeForSelect(),
            'clients' => app(ClientRepository::class)->allForSelect(),
            'statuses' => collect(OrderStatus::cases())->map(fn ($s) => [
                'value' => $s->value,
                'label' => $s->label(),
                'color' => $s->color(),
            ]),
            'filters' => $filters,
        ]);
    }

    public function show(int $id): Response
    {
        $order = $this->repository->findOrFail($id);

        $isPending = $order->status === OrderStatus::Pending;

        return Inertia::render('Orders/Show', [
            'order' => (new OrderResource($order))->resolve(),
            'oblasts' => $isPending ? app(OblastRepository::class)->allWithCities() : collect(),
            'categories' => $isPending ? app(CategoryRepository::class)->treeForSelect() : [],
            'statuses' => collect(OrderStatus::cases())->map(fn ($s) => [
                'value' => $s->value,
                'label' => $s->label(),
                'color' => $s->color(),
            ]),
        ]);
    }

    public function store(StoreOrderRequest $request, CreateOrderForClientAction $action): RedirectResponse
    {
        $data = $request->validated();
        $photos = $request->file('photos', []);
        unset($data['photos']);

        $action->handle($data, $photos);
        $this->notifySuccess('notifications.created', ['resource' => __('resources.order')]);

        return redirect()->route('orders.index');
    }

    public function update(UpdateOrderRequest $request, int $id, UpdateOrderAction $action): RedirectResponse
    {
        $order = $this->repository->findOrFail($id);

        try {
            $action->handle($order, $request->validated());
            $this->notifySuccess('notifications.updated', ['resource' => __('resources.order')]);
        } catch (OrderException $e) {
            $this->notifyError($e->getMessage());
        }

        return redirect()->route('orders.show', $id);
    }

    public function destroy(int $id, DeleteOrderAction $action): RedirectResponse
    {
        $order = $this->repository->findOrFail($id);
        $action->handle($order);
        $this->notifySuccess('notifications.deleted', ['resource' => __('resources.order')]);

        return redirect()->route('orders.index');
    }

    public function restartSearch(int $id, RestartOrderSearchAction $action): RedirectResponse
    {
        $order = $this->repository->findOrFail($id);

        try {
            $action->handle($order);
            $this->notifySuccess('orders.notifications.search_restarted');
        } catch (OrderException $e) {
            $this->notifyError($e->getMessage());
        }

        return redirect()->route('orders.show', $order->id);
    }

    public function updateStatus(UpdateOrderStatusRequest $request, int $id, UpdateOrderStatusAction $action): RedirectResponse
    {
        $order = $this->repository->findOrFail($id);
        $data = $request->validated();
        $newStatus = OrderStatus::from($data['status']);

        try {
            $updated = $action->handle($order, $newStatus, $data['cancel_reason'] ?? null);
            $updated->loadMissing('master');

            if ($newStatus === OrderStatus::Completed && $updated->final_price === null) {
                $this->notifyWarning('orders.notifications.completed_without_price');
            } else {
                $this->notifySuccess('orders.notifications.status_updated');
            }
        } catch (OrderException $e) {
            $this->notifyError($e->getMessage());
        }

        return redirect()->route('orders.show', $order->id);
    }

    /**
     * The route the master drove for this order — the polyline on the order map.
     *
     * Scoped by the ping's own `order_id`, which is the only thing that says a
     * position belongs to this job. Selecting by master and a time window would
     * fold in every other trip they made in between and draw them as one line.
     */
    public function masterTrajectoryForOrder(int $id, MasterLocationRepository $locations): MasterTrajectoryResource
    {
        $order = $this->repository->findOrFail($id);

        return new MasterTrajectoryResource($locations->trackForOrder($order));
    }
}
