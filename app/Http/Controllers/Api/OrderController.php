<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ServiceListing;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    // The 6-stage happy-path timeline, in order. "pending" and "rejected"/"cancelled"
    // are the entry point and the two "stopped" states, not part of the forward pipeline.
    public const PIPELINE = ['pending', 'ordered_from_shein', 'shipped', 'arrived', 'inspected', 'received'];

    private function currentStore(Request $request): Store
    {
        $store = Store::where('user_id', $request->user()->id)->first();
        abort_if(! $store, 404, 'You have not created a store yet.');
        return $store;
    }

    // POST /api/orders — a CUSTOMER placing an order with a specific broker's store
    public function store(Request $request)
    {
        $validated = $request->validate([
            'store_id'                    => ['required', 'exists:stores,id'],
            'delivery_method'             => ['required', Rule::in(['home_delivery', 'pickup'])],
            'address'                     => ['required_if:delivery_method,home_delivery', 'nullable', 'string', 'max:255'],
            'contact_phone'               => ['nullable', 'string', 'max:20'],
            'customer_note'               => ['nullable', 'string'],
            'estimated_amount'            => ['nullable', 'numeric', 'min:0'],
            'items'                       => ['required', 'array', 'min:1'],
            'items.*.service_listing_id'  => ['nullable', 'exists:service_listings,id'],
            'items.*.quantity'            => ['required', 'integer', 'min:1'],
            'items.*.product_name'        => ['required', 'string', 'max:150'],
            'items.*.product_url'         => ['nullable', 'url', 'max:2048'],
            'items.*.product_image'       => ['nullable', 'image', 'max:4096'],
            'items.*.color'               => ['nullable', 'string', 'max:50'],
            'items.*.size'                => ['nullable', 'string', 'max:50'],
            'items.*.item_note'           => ['nullable', 'string', 'max:255'],
        ]);

        $store = Store::findOrFail($validated['store_id']);
        if (! $store->is_accepting_orders) {
            $this->fail('This broker is not accepting orders right now.', 'STORE_NOT_ACCEPTING_ORDERS');
        }
        if ($validated['delivery_method'] === 'pickup' && ! $store->pickup_location) {
            $this->fail('This broker has not set a pickup location, so pickup is not available.', 'PICKUP_NOT_AVAILABLE');
        }

        // Only look up services for items that actually picked one — a null
        // service_listing_id is valid now and simply skips this check.
        $serviceIds = collect($validated['items'])->pluck('service_listing_id')->filter();

        $services = ServiceListing::whereIn('id', $serviceIds)
            ->where('store_id', $store->id)
            ->where('is_available', true)
            ->get()
            ->keyBy('id');

        foreach ($validated['items'] as $item) {
            if (! empty($item['service_listing_id'])) {
                if (! $services->has($item['service_listing_id'])) {
                    $this->fail('One of the selected services is not available from this store.', 'SERVICE_NOT_AVAILABLE');
                }
            }
        }

        $order = DB::transaction(function () use ($validated, $store, $request, $services) {
            $order = Order::create([
                'store_id'         => $store->id,
                'customer_id'      => $request->user()->id,
                'status'           => 'pending',
                'estimated_amount' => $validated['estimated_amount'] ?? 0,
                'customer_note'    => $validated['customer_note'] ?? null,
                'delivery_method'  => $validated['delivery_method'],
                'address'          => $validated['address'] ?? null,
                'contact_phone'    => $validated['contact_phone'] ?? null,
                'delivery_fee'     => $validated['delivery_method'] === 'home_delivery' ? $store->delivery_fee : null,
            ]);

            foreach ($validated['items'] as $index => $item) {
                $service = ! empty($item['service_listing_id'])
                    ? $services[$item['service_listing_id']]
                    : null;

                $imagePath = null;
                if ($request->hasFile("items.$index.product_image")) {
                    $imagePath = $request->file("items.$index.product_image")->store('order-items', 'public');
                }

                OrderItem::create([
                    'order_id'            => $order->id,
                    'service_listing_id'  => $service?->id,
                    'quantity'            => $item['quantity'],
                    'unit_price'          => $service && in_array($service->fee_type, ['fixed', 'percentage'])
                        ? $service->fee_amount
                        : null,
                    'product_name'        => $item['product_name'],
                    'product_url'         => $item['product_url'] ?? null,
                    'product_image_path'  => $imagePath,
                    'color'               => $item['color'] ?? null,
                    'size'                => $item['size'] ?? null,
                    'item_note'           => $item['item_note'] ?? null,
                ]);
            }

            return $order;
        });

        \App\Models\Notification::notify(
            $store->user_id,
            'order_placed',
            "طلب جديد #{$order->id}",
            "طلب جديد من {$request->user()->full_name}",
            ['order_id' => $order->id]
        );

        return response()->json([
            'message' => 'Order placed successfully.',
            'order'   => $order->load('items'),
        ], 201);
    }

    // GET /api/orders/{order} — "عرض التفاصيل" — broker OR the customer who placed it
    public function show(Request $request, Order $order)
    {
        $user = $request->user();
        $isBroker   = $order->store->user_id === $user->id;
        $isCustomer = $order->customer_id === $user->id;

        if (! $isBroker && ! $isCustomer) {
            $this->fail('You do not have access to this order.', 'ORDER_ACCESS_DENIED', 403);
        }

        $order->load(['customer', 'store', 'items.serviceListing']);

        return response()->json([
            'order'           => array_merge($order->toArray(), ['date' => $this->formatArabicDate($order)]),
            'status_times'    => $this->buildStatusTimes($order),
            'status_history'  => $this->buildStatusHistory($order),
            'totals'          => $this->buildTotals($order),
        ]);
    }

    // GET /api/orders — BROKER's incoming orders table
    public function index(Request $request)
    {
        $store = $this->currentStore($request);

        $query = Order::with(['customer', 'items'])->where('store_id', $store->id);
        $this->applyFilters($query, $request, customerNameSearch: true);

        $perPage = min((int) $request->input('per_page', 15), 100);
        $paginated = $query->latest()->paginate($perPage);

        return response()->json([
            'orders' => $this->formatList(collect($paginated->items())),
            'pagination' => [
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'per_page'     => $paginated->perPage(),
                'total'        => $paginated->total(),
            ],
        ]);
    }

    // GET /api/orders/stats — broker's 4 cards
    public function stats(Request $request)
    {
        $store = $this->currentStore($request);

        return response()->json([
            'total'       => $store->orders()->count(),
            'new'         => $store->orders()->where('status', 'pending')->count(),
            'in_progress' => $store->orders()->whereIn('status', ['ordered_from_shein', 'shipped', 'arrived', 'inspected'])->count(),
            'completed'   => $store->orders()->where('status', 'received')->count(),
        ]);
    }

    // GET /api/my-orders — CUSTOMER's own order history
    public function myOrders(Request $request)
    {
        $customerId = $request->user()->id;

        $query = Order::with(['store', 'items', 'review'])->where('customer_id', $customerId);

        if ($request->filled('status')) {
            match ($request->status) {
                'active'                 => $query->whereNotIn('status', ['received', 'rejected', 'cancelled']),
                'completed'              => $query->where('status', 'received'),
                'cancelled_or_rejected'  => $query->whereIn('status', ['rejected', 'cancelled']),
                default                  => null,
            };
        }
        $this->applyFilters($query, $request, customerNameSearch: false);

        $all = Order::where('customer_id', $customerId);

        $perPage = min((int) $request->input('per_page', 15), 100);
        $paginated = $query->latest()->paginate($perPage);

        return response()->json([
            'stats' => [
                'active'                => (clone $all)->whereNotIn('status', ['received', 'rejected', 'cancelled'])->count(),
                'completed'             => (clone $all)->where('status', 'received')->count(),
                'cancelled_or_rejected' => (clone $all)->whereIn('status', ['rejected', 'cancelled'])->count(),
            ],
            'orders' => collect($paginated->items())->map(fn ($o) => [
                'id'                => $o->id,
                'store_id'          => $o->store_id,
                'store_name'        => $o->store->name,
                'store_image_url'   => $o->store->image_url,
                'date'              => $this->formatArabicDate($o),
                'items_count'       => $o->items->count(),
                'estimated_amount'  => $o->estimated_amount,
                'status'            => $o->status,
                'reviewed'          => (bool) $o->review,
                'status_times'      => $this->buildStatusTimes($o),
            ]),
            'pagination' => [
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'per_page'     => $paginated->perPage(),
                'total'        => $paginated->total(),
            ],
        ]);
    }

    // PATCH /api/orders/{order}/accept
    public function accept(Request $request, Order $order)
    {
        $store = $this->currentStore($request);
        if ($order->store_id !== $store->id) {
            $this->fail('This order does not belong to your store.', 'ORDER_NOT_YOURS', 403);
        }
        if ($order->status !== 'pending') {
            $this->fail('Only a pending order can be accepted.', 'ORDER_NOT_PENDING');
        }

        $validated = $request->validate([
            'items'              => ['required', 'array', 'min:1'],
            'items.*.id'         => ['required', 'integer', Rule::exists('order_items', 'id')->where('order_id', $order->id)],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
        ]);

        $orderItemIds = $order->items()->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();
        $submittedIds = collect($validated['items'])->pluck('id')->map(fn ($id) => (int) $id)->unique()->sort()->values()->all();
        if ($orderItemIds !== $submittedIds) {
            $this->fail('Every item in the order must be given a price.', 'MISSING_ITEM_PRICES');
        }

        DB::transaction(function () use ($validated, $order) {
            foreach ($validated['items'] as $item) {
                OrderItem::where('id', $item['id'])
                    ->where('order_id', $order->id)
                    ->update(['unit_price' => $item['unit_price']]);
            }

            $order->update(['status' => 'ordered_from_shein', 'ordered_from_shein_at' => now()]);
        });

        \App\Models\Notification::notify(
            $order->customer_id,
            'order_accepted',
            "تم قبول طلبك #{$order->id}",
            null,
            ['order_id' => $order->id]
        );

        return response()->json([
            'message' => 'Order accepted.',
            'order'   => $order->load('items'),
        ]);
    }

    // PATCH /api/orders/{order}/reject
    public function reject(Request $request, Order $order)
    {
        $store = $this->currentStore($request);
        if ($order->store_id !== $store->id) {
            $this->fail('This order does not belong to your store.', 'ORDER_NOT_YOURS', 403);
        }
        if ($order->status !== 'pending') {
            $this->fail('Only a pending order can be rejected.', 'ORDER_NOT_PENDING');
        }

        $validated = $request->validate([
            'rejection_reason' => ['nullable', 'string', 'max:500'],
        ]);

        $order->update([
            'status'            => 'rejected',
            'rejected_at'       => now(),
            'rejection_reason'  => $validated['rejection_reason'] ?? null,
        ]);

        \App\Models\Notification::notify(
            $order->customer_id,
            'order_rejected',
            "تم رفض طلبك #{$order->id}",
            $order->rejection_reason,
            ['order_id' => $order->id]
        );

        return response()->json(['message' => 'Order rejected.', 'order' => $order]);
    }

    // PATCH /api/orders/{order}/cancel
    public function cancel(Request $request, Order $order)
    {
        if ($order->customer_id !== $request->user()->id) {
            $this->fail('This is not your order.', 'ORDER_NOT_YOURS', 403);
        }
        if ($order->status !== 'pending') {
            $this->fail('This order can no longer be cancelled.', 'ORDER_NOT_CANCELLABLE');
        }

        $order->update(['status' => 'cancelled', 'cancelled_at' => now()]);

        \App\Models\Notification::notify(
            $order->store->user_id,
            'order_cancelled',
            "ألغت الزبونة الطلب #{$order->id}",
            null,
            ['order_id' => $order->id]
        );

        return response()->json(['message' => 'Order cancelled.', 'order' => $order]);
    }

    // PATCH /api/orders/{order}/status
    public function updateStatus(Request $request, Order $order)
    {
        $store = $this->currentStore($request);
        if ($order->store_id !== $store->id) {
            $this->fail('This order does not belong to your store.', 'ORDER_NOT_YOURS', 403);
        }

        $validated = $request->validate([
            'status' => ['required', Rule::in([...self::PIPELINE, 'cancelled'])],
        ]);

        $newStatus = $validated['status'];

        if ($order->status === 'pending') {
            $this->fail('Accept or reject this order first.', 'ORDER_PENDING_DECISION');
        }

        $cancellableStages = array_slice(self::PIPELINE, 1, -1);

        if ($newStatus === 'cancelled') {
            if (! in_array($order->status, $cancellableStages)) {
                $this->fail('This order can no longer be cancelled.', 'ORDER_NOT_CANCELLABLE');
            }
        } else {
            $currentIndex = array_search($order->status, self::PIPELINE);
            $newIndex = array_search($newStatus, self::PIPELINE);
            if ($currentIndex === false || $newIndex !== $currentIndex + 1) {
                $this->fail('Orders can only move to the next stage in the pipeline, one step at a time.', 'INVALID_STATUS_TRANSITION');
            }
        }

        $order->update(['status' => $newStatus, "{$newStatus}_at" => now()]);

        $statusLabels = [
            'shipped' => 'تم الشحن', 'arrived' => 'وصلت', 'inspected' => 'تم الفحص',
            'received' => 'تم الاستلام', 'cancelled' => 'تم الإلغاء',
        ];
        \App\Models\Notification::notify(
            $order->customer_id,
            'order_status_changed',
            "طلبك #{$order->id}: " . ($statusLabels[$newStatus] ?? $newStatus),
            null,
            ['order_id' => $order->id, 'status' => $newStatus]
        );

        return response()->json(['message' => 'Order status updated.', 'order' => $order]);
    }

    private function formatArabicDate($order): string
    {
        return $order->created_at->copy()->timezone('Asia/Gaza')->locale('ar')->translatedFormat('j F Y \· h:i A');
    }

    private function buildStatusTimes(Order $order): array
    {
        $times = ['pending' => $order->created_at];

        foreach (['ordered_from_shein', 'shipped', 'arrived', 'inspected', 'received', 'rejected', 'cancelled'] as $stage) {
            $column = "{$stage}_at";
            if ($order->$column) {
                $times[$stage] = $order->$column;
            }
        }

        return collect($times)->map(fn ($t) => $t->toISOString())->toArray();
    }

    private function buildTotals(Order $order): array
    {
        $itemsTotal = $order->items->sum(fn ($item) => ($item->unit_price ?? 0) * $item->quantity);
        $deliveryFee = $order->delivery_fee ?? 0;

        return [
            'items_total'   => round($itemsTotal, 2),
            'service_fee'   => 0,
            'delivery_fee'  => round((float) $deliveryFee, 2),
            'total_amount'  => round($itemsTotal + $deliveryFee, 2),
        ];
    }

    private function buildStatusHistory(Order $order): array
    {
        $stages = ['pending', 'ordered_from_shein', 'shipped', 'arrived', 'inspected', 'received', 'rejected', 'cancelled'];

        $history = [];
        foreach ($stages as $stage) {
            $time = $stage === 'pending' ? $order->created_at : $order->{"{$stage}_at"};
            if ($time) {
                $history[] = ['status' => $stage, 'created_at' => $time->toISOString()];
            }
        }

        usort($history, fn ($a, $b) => strcmp($a['created_at'], $b['created_at']));

        return $history;
    }



    private function applyFilters($query, Request $request, bool $customerNameSearch): void
    {
        if ($request->filled('date')) {
            $query->whereDate('created_at', $request->date);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search, $customerNameSearch) {
                $q->where('id', 'like', "%{$search}%");
                if ($customerNameSearch) {
                    $q->orWhereHas('customer', fn ($c) => $c->where('full_name', 'like', "%{$search}%"));
                } else {
                    $q->orWhereHas('store', fn ($s) => $s->where('name', 'like', "%{$search}%"));
                }
            });
        }

        if ($request->filled('status') && $request->status !== 'all'
            && ! in_array($request->status, ['active', 'cancelled_or_rejected'])) {
            match ($request->status) {
                'in_progress' => $query->whereIn('status', ['ordered_from_shein', 'shipped', 'arrived', 'inspected']),
                'completed'   => $query->where('status', 'received'),
                default       => $query->where('status', $request->status),
            };
        }
    }

    private function formatList($orders)
    {
        return $orders->map(fn ($order) => [
            'id'                => $order->id,
            'customer_name'     => $order->customer->full_name,
            'date'              => $this->formatArabicDate($order),
            'items_count'       => $order->items->count(),
            'estimated_amount'  => $order->estimated_amount,
            'status'            => $order->status,
        ]);
    }
}
