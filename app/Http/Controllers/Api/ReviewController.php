<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Review;
use App\Models\Store;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    // POST /api/reviews — a CUSTOMER reviewing one of her own completed orders
    public function store(Request $request)
    {
        $validated = $request->validate([
            'order_id' => ['required', 'exists:orders,id'],
            'rating'   => ['required', 'integer', 'min:1', 'max:5'],
            'comment'  => ['nullable', 'string'],
        ]);

        $order = Order::findOrFail($validated['order_id']);

        abort_unless($order->customer_id === $request->user()->id, 403, 'This is not your order.');
        abort_unless($order->status === 'received', 422, 'You can only review a completed order.');

        if (Review::where('order_id', $order->id)->exists()) {
            return response()->json(['message' => 'You have already reviewed this order.'], 422);
        }

        $review = Review::create([
            'order_id'    => $order->id,
            'store_id'    => $order->store_id,
            'customer_id' => $request->user()->id,
            'rating'      => $validated['rating'],
            'comment'     => $validated['comment'] ?? null,
        ]);

        \App\Models\Notification::notify(
            $order->store->user_id,
            'review_received',
            "تقييم جديد ({$validated['rating']} نجوم) على طلب #{$order->id}",
            $validated['comment'] ?? null,
            ['order_id' => $order->id, 'review_id' => $review->id]
        );

        return response()->json([
            'message' => 'Review submitted successfully.',
            'review'  => $review,
        ], 201);
    }
    // POST /api/orders/{order}/review — the CUSTOMER reviewing a completed order,
    // matching the frontend's exact spec: nested under the order, order model
    // resolved from the route (not the body), and customer_name in the response.
    public function storeForOrder(Request $request, Order $order)
    {
        $validated = $request->validate([
            'rating'  => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:500'],
        ]);

        abort_unless((int) $order->customer_id === (int) $request->user()->id, 403, 'This is not your order.');
        abort_unless($order->status === 'received', 422, 'You can only review a completed order.');
        abort_if(Review::where('order_id', $order->id)->exists(), 422, 'You have already reviewed this order.');

        $review = Review::create([
            'order_id'    => $order->id,
            'store_id'    => $order->store_id,
            'customer_id' => $request->user()->id,
            'rating'      => $validated['rating'],
            'comment'     => $validated['comment'] ?? null,
        ]);

        return response()->json([
            'message' => 'Review submitted successfully.',
            'review'  => [
                'id'            => $review->id,
                'rating'        => $review->rating,
                'comment'       => $review->comment,
                'order_id'      => $review->order_id,
                'customer_name' => $request->user()->full_name,
                'created_at'    => $review->created_at,
            ],
        ], 201);
    }

    // GET /api/stores/{store}/reviews — public: same shape as index() (average,
    // distribution, list) but for any published store, for the CUSTOMER's view
    // of a broker's profile page.
    public function forStore(Store $store)
    {
        abort_unless($store->status === 'published', 404, 'This store is not available.');

        $reviews = Review::with('customer')
            ->where('store_id', $store->id)
            ->latest()
            ->get();

        $total = $reviews->count();

        $distribution = [];
        for ($star = 5; $star >= 1; $star--) {
            $count = $reviews->where('rating', $star)->count();
            $distribution[] = [
                'stars'      => $star,
                'count'      => $count,
                'percentage' => $total > 0 ? round(($count / $total) * 100) : 0,
            ];
        }

        return response()->json([
            'average_rating' => $total > 0 ? round($reviews->avg('rating'), 1) : 0,
            'total_reviews'  => $total,
            'distribution'   => $distribution,
            'reviews'        => $reviews->map(fn ($r) => [
                'id'            => $r->id,
                'customer_name' => $r->customer->full_name,
                'rating'        => $r->rating,
                'comment'       => $r->comment,
                'order_id'      => $r->order_id,
                'date'          => $r->created_at->format('d F Y'),
            ]),
        ]);
    }

    // GET /api/reviews — everything the "التقييمات والمراجعات" page needs in one call
    public function index(Request $request)
    {
        $store = Store::where('user_id', $request->user()->id)->first();
        abort_if(! $store, 404, 'You have not created a store yet.');

        $reviews = Review::with('customer')
            ->where('store_id', $store->id)
            ->latest()
            ->get();

        $total = $reviews->count();

        // "توزيع التقييمات" — count + percentage per star, 5 down to 1
        $distribution = [];
        for ($star = 5; $star >= 1; $star--) {
            $count = $reviews->where('rating', $star)->count();
            $distribution[] = [
                'stars'      => $star,
                'count'      => $count,
                'percentage' => $total > 0 ? round(($count / $total) * 100) : 0,
            ];
        }

        return response()->json([
            'average_rating' => $total > 0 ? round($reviews->avg('rating'), 1) : 0,
            'total_reviews'  => $total,
            'distribution'   => $distribution,
            'reviews'        => $reviews->map(fn ($r) => [
                'id'            => $r->id,
                'customer_name' => $r->customer->full_name,
                'rating'        => $r->rating,
                'comment'       => $r->comment,
                'order_id'      => $r->order_id,
                'date'          => $r->created_at->format('d F Y'),
            ]),
        ]);
    }
}
