<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ServiceListing;
use App\Models\Store;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    // Helper: get the logged-in broker's own store, or fail clearly
    private function currentStore(Request $request): Store
    {
        $store = Store::where('user_id', $request->user()->id)->first();

        abort_if(! $store, 404, 'You have not created a store yet.');

        return $store;
    }

    // GET /api/services — list this broker's own services
    public function index(Request $request)
    {
        $store = $this->currentStore($request);

        return response()->json([
            'services' => $store->serviceListings,
        ]);
    }

    // POST /api/services — add a new service to this broker's store
    public function store(Request $request)
    {
        $store = $this->currentStore($request);

        $validated = $request->validate([
            'title'               => ['required', 'string', 'max:150'],
            'photo'                => ['nullable', 'image', 'max:4096'],
            'price'                => ['required', 'numeric', 'min:0'],
            'category'             => ['nullable', 'string', 'max:100'],
            'estimated_delivery'   => ['nullable', 'string', 'max:100'],
        ]);

        $photoPath = null;
        if ($request->hasFile('photo')) {
            $photoPath = $request->file('photo')->store('services', 'public');
        }

        $service = ServiceListing::create([
            'store_id'            => $store->id,
            'title'               => $validated['title'],
            'photo_path'          => $photoPath,
            'price'               => $validated['price'],
            'category'            => $validated['category'] ?? null,
            'estimated_delivery'  => $validated['estimated_delivery'] ?? null,
            'status'              => 'active',
        ]);

        return response()->json([
            'message' => 'Service added successfully.',
            'service' => $service,
        ], 201);
    }
}
