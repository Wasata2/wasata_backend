<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Store;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    // GET /api/dashboard/stats — the 3 cards: طلبات جديدة, طلبات قيد التنفيذ, الخدمات النشطة
    public function stats(Request $request)
    {
        $store = Store::where('user_id', $request->user()->id)->first();

        abort_if(! $store, 404, 'You have not created a store yet.');

        return response()->json([
            'new_orders'       => $store->orders()->where('status', 'pending')->count(),
            'in_progress'      => $store->orders()->where('status', 'in_progress')->count(),
            'active_services'  => $store->serviceListings()->where('status', 'active')->count(),
        ]);
    }
}
