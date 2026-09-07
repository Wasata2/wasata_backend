<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Store;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    private function currentStore(Request $request): Store
    {
        $store = Store::where('user_id', $request->user()->id)->first();

        abort_if(! $store, 404, 'You have not created a store yet.');

        return $store;
    }

    // GET /api/orders — the "الطلبات" table, with filters matching the page:
    // ?status=pending|in_progress|ordered_from_shein|shipped|ready_for_pickup|completed|rejected|cancelled
    // ?date=2026-08-24
    // ?search=1042  (matches order id or customer name)
    public function index(Request $request)
    {
        $store = $this->currentStore($request);

        $query = Order::with(['customer', 'items'])->where('store_id', $store->id);

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('date')) {
            $query->whereDate('created_at', $request->date);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('id', 'like', "%{$search}%")
                  ->orWhereHas('customer', fn ($c) => $c->where('full_name', 'like', "%{$search}%"));
            });
        }

        $orders = $query->latest()->get()->map(function ($order) {
            return [
                'id'                => $order->id,
                'customer_name'     => $order->customer->full_name,
                'date'              => $order->created_at->format('d M Y'),
                'items_count'       => $order->items->count(),
                'estimated_amount'  => $order->estimated_amount,
                'status'            => $order->status,
            ];
        });

        return response()->json(['orders' => $orders]);
    }

    // GET /api/orders/stats — the 4 cards: إجمالي الطلبات, طلبات جديدة, قيد التنفيذ, مكتملة
    public function stats(Request $request)
    {
        $store = $this->currentStore($request);

        return response()->json([
            'total'       => $store->orders()->count(),
            'new'         => $store->orders()->where('status', 'pending')->count(),
            'in_progress' => $store->orders()->where('status', 'in_progress')->count(),
            'completed'   => $store->orders()->where('status', 'completed')->count(),
        ]);
    }

    // PATCH /api/orders/{order}/accept — the ✓ button
    public function accept(Request $request, Order $order)
    {
        $store = $this->currentStore($request);
        abort_if($order->store_id !== $store->id, 403, 'This order does not belong to your store.');

        $order->update(['status' => 'in_progress']);

        return response()->json(['message' => 'Order accepted.', 'order' => $order]);
    }

    // PATCH /api/orders/{order}/reject — the ✗ button
    public function reject(Request $request, Order $order)
    {
        $store = $this->currentStore($request);
        abort_if($order->store_id !== $store->id, 403, 'This order does not belong to your store.');

        $order->update(['status' => 'rejected']);

        return response()->json(['message' => 'Order rejected.', 'order' => $order]);
    }
}
