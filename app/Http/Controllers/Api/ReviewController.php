<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Models\Store;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
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
