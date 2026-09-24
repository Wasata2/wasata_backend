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

        return response()->json([
            'message' => 'Review submitted successfully.',
            'review'  => $review,
        ], 201);
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

    // GET /api/stores/{store}/reviews — public reviews for a specific store,
    // for a CUSTOMER viewing that broker's profile before ordering
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
}
