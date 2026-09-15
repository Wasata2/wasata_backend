<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Store;
use Illuminate\Http\Request;

class StoreController extends Controller
{
    // GET /api/stores — public browse/discovery list, for the CUSTOMER choosing a broker
    // ?city=غزة   ?search=store+name
    public function browse(Request $request)
    {
        $query = Store::where('status', 'published');

        if ($request->filled('city')) {
            $query->where('city', $request->city);
        }

        if ($request->filled('search')) {
            $query->where('name', 'like', '%'.$request->search.'%');
        }

        $stores = $query->latest()->get();

        return response()->json([
            'stores' => $stores->map(fn ($store) => [
                'id'                      => $store->id,
                'name'                    => $store->name,
                'bio'                     => $store->bio,
                'image_url'               => $store->image_url,
                'city'                    => $store->city,
                'is_accepting_orders'     => $store->is_accepting_orders,
                'accepts_whatsapp_orders' => $store->accepts_whatsapp_orders,
                'delivery_time_range'     => $store->delivery_time_range,
                'delivery_fee'            => $store->delivery_fee,
                'pickup_available'        => (bool) $store->pickup_location,
                'average_rating'          => round($store->reviews()->avg('rating') ?? 0, 1),
                'total_reviews'           => $store->reviews()->count(),
            ]),
        ]);
    }

    // GET /api/stores/{store} — single store's public page: profile + its available
    // services (so the customer knows what she can order) + rating summary
    public function show(Store $store)
    {
        abort_unless($store->status === 'published', 404, 'This store is not available.');

        return response()->json([
            'store' => [
                'id'                      => $store->id,
                'name'                    => $store->name,
                'bio'                     => $store->bio,
                'image_url'               => $store->image_url,
                'phone'                   => $store->phone,
                'city'                    => $store->city,
                'is_accepting_orders'     => $store->is_accepting_orders,
                'accepts_whatsapp_orders' => $store->accepts_whatsapp_orders,
                'delivery_time_range'     => $store->delivery_time_range,
                'delivery_fee'            => $store->delivery_fee,
                'pickup_location'         => $store->pickup_location,
                'average_rating'          => round($store->reviews()->avg('rating') ?? 0, 1),
                'total_reviews'           => $store->reviews()->count(),
            ],
            // is_available filter: a store might have disabled services it doesn't
            // want new customers to order right now
            'services' => $store->serviceListings()->where('is_available', true)->get(),
        ]);
    }

    // POST /api/stores
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'                     => ['required', 'string', 'max:150'],
            'bio'                      => ['nullable', 'string', 'max:150'],
            'image'                    => ['nullable', 'image', 'max:4096'], // 4MB max
            'phone'                    => ['required', 'string', 'max:20'],
            'city'                     => ['required', 'string', 'in:غزة,شمال غزة,الوسطى,خانيونس,رفح'],
            'accepts_whatsapp_orders'  => ['boolean'],
        ]);

        // Handle the image upload, if one was sent
        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('stores', 'public');
        }

        $store = Store::create([
            'user_id'                  => $request->user()->id,
            'name'                     => $validated['name'],
            'bio'                      => $validated['bio'] ?? null,
            'image_path'               => $imagePath,
            'phone'                    => $validated['phone'],
            'city'                     => $validated['city'],
            'accepts_whatsapp_orders'  => $validated['accepts_whatsapp_orders'] ?? false,
            'status'                   => 'draft', // starts as draft — step 2 ("ready to publish") flips this later
        ]);

        return response()->json([
            'message' => 'Store created successfully.',
            'store'   => $store,
        ], 201);
    }

    // GET /api/stores/me — returns ONLY the store belonging to the logged-in user
    public function myStore(Request $request)
    {
        $store = Store::where('user_id', $request->user()->id)->first();

        if (! $store) {
            return response()->json([
                'message' => 'You have not created a store yet.',
            ], 404);
        }

        return response()->json([
            'store' => $store,
        ], 200);
    }

    // PATCH /api/stores/me — edit profile fields after creation (e.g. from MediatorProfile.js)
    public function update(Request $request)
    {
        $store = Store::where('user_id', $request->user()->id)->first();

        if (! $store) {
            return response()->json([
                'message' => 'You have not created a store yet.',
            ], 404);
        }

        // 'sometimes' = only validate/update fields that were actually sent
        $validated = $request->validate([
            'name'                     => ['sometimes', 'string', 'max:150'],
            'bio'                      => ['sometimes', 'nullable', 'string', 'max:150'],
            'image'                    => ['sometimes', 'nullable', 'image', 'max:4096'],
            'phone'                    => ['sometimes', 'string', 'max:20'],
            'city'                     => ['sometimes', 'string', 'in:غزة,شمال غزة,الوسطى,خانيونس,رفح'],
            'accepts_whatsapp_orders'  => ['sometimes', 'boolean'],
            'is_accepting_orders'      => ['sometimes', 'boolean'],
            'commission_rate'          => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'delivery_time_range'      => ['sometimes', 'nullable', 'string', 'max:50'],
            'delivery_fee'             => ['sometimes', 'numeric', 'min:0'],
            'pickup_location'          => ['sometimes', 'nullable', 'string', 'max:150'],
        ]);

        if ($request->hasFile('image')) {
            $validated['image_path'] = $request->file('image')->store('stores', 'public');
            unset($validated['image']);
        }

        $store->update($validated);

        return response()->json([
            'message' => 'Store updated successfully.',
            'store'   => $store,
        ], 200);
    }
}
