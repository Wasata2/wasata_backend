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
            'customer_note'               => ['nullable', 'string'],
            'estimated_amount'            => ['nullable', 'numeric', 'min:0'],
            'items'                       => ['required', 'array', 'min:1'],
            'items.*.service_listing_id'  => ['required', 'exists:service_listings,id'],
            'items.*.quantity'            => ['required', 'integer', 'min:1'],
            // Product details the customer fills in for the SHEIN item behind this line —
            // optional since not every service (e.g. a pure shipping fee) has one.
            'items.*.product_name'        => ['required', 'string', 'max:150'],
            'items.*.product_url'         => ['nullable', 'url', 'max:2048'],
            'items.*.product_image'       => ['nullable', 'image', 'max:4096'], // 4MB max, like Store::image
            'items.*.color'               => ['nullable', 'string', 'max:50'],
            'items.*.size'                => ['nullable', 'string', 'max:50'],
            'items.*.item_note'           => ['nullable', 'string', 'max:255'],
        ]);

        $store = Store::findOrFail($validated['store_id']);
        abort_if(! $store->is_accepting_orders, 422, 'This broker is not accepting orders right now.');
        abort_if(
            $validated['delivery_method'] === 'pickup' && ! $store->pickup_location,
            422,
            'This broker has not set a pickup location, so pickup is not available.'
        );

        $services = ServiceListing::whereIn('id', collect($validated['items'])->pluck('service_listing_id'))
            ->where('store_id', $store->id)
            ->where('is_available', true)
            ->get()
            ->keyBy('id');

        foreach ($validated['items'] as $item) {
            abort_if(
                ! $services->has($item['service_listing_id']),
                422,
                'One of the selected services is not available from this store.'
            );
        }

        $order = DB::transaction(function () use ($validated, $store, $request, $services) {
            $order = Order::create([
                'store_id'         => $store->id,
                'customer_id'      => $request->user()->id,
                'status'           => 'pending',
                'estimated_amount' => $validated['estimated_amount'] ?? 0,
                'customer_note'    => $validated['customer_note'] ?? null,
                'delivery_method'  => $validated['delivery_method'],
                // Snapshot now — pickup has no fee; home delivery locks in the store's
                // current fee so a later change by the broker won't alter this order.
                'delivery_fee'     => $validated['delivery_method'] === 'home_delivery' ? $store->delivery_fee : null,
            ]);

            foreach ($validated['items'] as $index => $item) {
                $service = $services[$item['service_listing_id']];

                // Files inside an array field arrive as items.{index}.product_image,
                // not inside $validated (validate() only returns non-file input).
                $imagePath = null;
                if ($request->hasFile("items.$index.product_image")) {
                    $imagePath = $request->file("items.$index.product_image")->store('order-items', 'public');
                }

                OrderItem::create([
                    'order_id'            => $order->id,
                    'service_listing_id'  => $service->id,
                    'quantity'            => $item['quantity'],
                    'unit_price'          => in_array($service->fee_type, ['fixed', 'percentage'])
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

        abort_unless($isBroker || $isCustomer, 403, 'You do not have access to this order.');

        return response()->json([
            'order' => $order->load(['customer', 'store', 'items.serviceListing']),
        ]);
    }

    // GET /api/orders — BROKER's incoming orders table
    // ?status=pending|ordered_from_shein|shipped|arrived|inspected|received|rejected|cancelled
    // ?date=2026-08-24   ?search=1042
    public function index(Request $request)
    {
        $store = $this->currentStore($request);

        $query = Order::with(['customer', 'items'])->where('store_id', $store->id);
        $this->applyFilters($query, $request, customerNameSearch: true);

        return response()->json(['orders' => $this->formatList($query->latest()->get())]);
    }

    // GET /api/orders/stats — broker's 4 cards: إجمالي, جديدة, قيد التنفيذ, مكتملة
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

    // GET /api/my-orders — CUSTOMER's own order history ("طلباتي" page)
    // ?status=active|completed|cancelled_or_rejected   ?date=...   ?search=...
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

        return response()->json([
            'stats' => [
                'active'                => (clone $all)->whereNotIn('status', ['received', 'rejected', 'cancelled'])->count(),
                'completed'             => (clone $all)->where('status', 'received')->count(),
                'cancelled_or_rejected' => (clone $all)->whereIn('status', ['rejected', 'cancelled'])->count(),
            ],
            'orders' => $query->latest()->get()->map(fn ($o) => [
                'id'                => $o->id,
                'store_name'        => $o->store->name,
                'date'              => $this->formatArabicDate($o),
                'items_count'       => $o->items->count(),
                'estimated_amount'  => $o->estimated_amount,
                'status'            => $o->status,
                'reviewed'          => (bool) $o->review,
            ]),
        ]);
    }

    // PATCH /api/orders/{order}/accept — pending -> ordered_from_shein (the first ✓ step)
    public function accept(Request $request, Order $order)
    {
        $store = $this->currentStore($request);
        abort_if($order->store_id !== $store->id, 403, 'This order does not belong to your store.');
        abort_unless($order->status === 'pending', 422, 'Only a pending order can be accepted.');

        $order->update(['status' => 'ordered_from_shein']);

        return response()->json(['message' => 'Order accepted.', 'order' => $order]);
    }

    // PATCH /api/orders/{order}/reject
    public function reject(Request $request, Order $order)
    {
        $store = $this->currentStore($request);
        abort_if($order->store_id !== $store->id, 403, 'This order does not belong to your store.');
        abort_unless($order->status === 'pending', 422, 'Only a pending order can be rejected.');

        $order->update(['status' => 'rejected']);

        return response()->json(['message' => 'Order rejected.', 'order' => $order]);
    }

    // PATCH /api/orders/{order}/status — broker moves the order forward through the
    // 6-stage timeline (shipped, arrived, inspected, received) or cancels it
    public function updateStatus(Request $request, Order $order)
    {
        $store = $this->currentStore($request);
        abort_if($order->store_id !== $store->id, 403, 'This order does not belong to your store.');

        $validated = $request->validate([
            'status' => ['required', Rule::in([...self::PIPELINE, 'cancelled'])],
        ]);

        $newStatus = $validated['status'];

        // The pending -> ordered_from_shein transition is the accept() gate, not this
        // endpoint — a broker must accept or reject before moving an order further.
        abort_if($order->status === 'pending', 422, 'Accept or reject this order first.');

        // The stages that can still be cancelled — not before acceptance (that's
        // reject()) and not after the order has already arrived (received).
        $cancellableStages = array_slice(self::PIPELINE, 1, -1);

        if ($newStatus === 'cancelled') {
            abort_unless(in_array($order->status, $cancellableStages), 422, 'This order can no longer be cancelled.');
        } else {
            $currentIndex = array_search($order->status, self::PIPELINE);
            $newIndex = array_search($newStatus, self::PIPELINE);
            abort_unless(
                $currentIndex !== false && $newIndex === $currentIndex + 1,
                422,
                'Orders can only move to the next stage in the pipeline, one step at a time.'
            );
        }

        $order->update(['status' => $newStatus]);

        return response()->json(['message' => 'Order status updated.', 'order' => $order]);
    }

    private function formatArabicDate($order): string
    {
        // e.g. "10 سبتمبر 2026 · 10:30 ص" — matches the Figma design exactly
        return $order->created_at->locale('ar')->translatedFormat('j F Y \· h:i A');
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
            // 'in_progress' and 'completed' are UI-level groupings, not real enum
            // values — map them to the pipeline stages they actually represent.
            // Everything else (pending, rejected, cancelled, or a literal pipeline
            // stage like 'shipped') is passed straight through.
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
