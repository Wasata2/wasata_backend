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

    // GET /api/orders — the "الطلبات الواردة" table, newest first
    public function index(Request $request)
    {
        $store = $this->currentStore($request);

        $orders = Order::with(['customer', 'items'])
            ->where('store_id', $store->id)
            ->latest()
            ->get()
            ->map(function ($order) {
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
