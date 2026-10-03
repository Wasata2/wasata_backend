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
        $query = Store::where('status', 'published')->with('deliveryZones');

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
                'commission_rate'         => $store->commission_rate,
                'delivery_zones'          => $store->deliveryZones->map(fn ($z) => ['region' => $z->region, 'fee' => $z->fee]),
                'delivery_time_range'     => $store->delivery_time_range,
                'delivery_fee'            => $store->delivery_fee,
                'pickup_available'        => (bool) $store->pickup_location,
                'average_rating'          => round($store->reviews()->avg('rating') ?? 0, 1),
                'total_reviews'           => $store->reviews()->count(),
                'completed_orders_count'  => $store->orders()->where('status', 'received')->count(),
            ]),
        ]);
    }

    // GET /api/stores/{store} — single store's public page: profile + its available
    // services (so the customer knows what she can order) + rating summary
    public function show(Store $store)
    {
        if ($store->status !== 'published') {
            $this->fail('This store is not available.', 'STORE_NOT_PUBLISHED', 404);
        }

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
                'commission_rate'         => $store->commission_rate,
                'delivery_time_range'     => $store->delivery_time_range,
                'delivery_fee'            => $store->delivery_fee,
                'delivery_zones'          => $store->deliveryZones->map(fn ($z) => ['region' => $z->region, 'fee' => $z->fee]),
                'pickup_location'         => $store->pickup_location,
                'average_rating'          => round($store->reviews()->avg('rating') ?? 0, 1),
                'total_reviews'           => $store->reviews()->count(),
                'completed_orders_count'  => $store->orders()->where('status', 'received')->count(),
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
            // Published immediately — name/phone/city are already required above,
            // and those are the only fields that gate visibility in GET /stores.
            'status'                   => 'published',
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
            $this->fail('You have not created a store yet.', 'STORE_NOT_FOUND', 404);
        }

            return response()->json([
            'store' => array_merge($store->toArray(), [
            'delivery_zones' => $store->deliveryZones->map(fn ($z) => ['region' => $z->region, 'fee' => $z->fee]),
            ]),
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
            // Optional — if sent, replaces this store's entire delivery_zones set.
            'delivery_zones'           => ['sometimes', 'array'],
            'delivery_zones.*.region'  => ['required_with:delivery_zones', \Illuminate\Validation\Rule::in(\App\Models\StoreDeliveryZone::REGIONS)],
            'delivery_zones.*.fee'     => ['required_with:delivery_zones', 'numeric', 'min:0'],
        ]);

        if ($request->hasFile('image')) {
            $validated['image_path'] = $request->file('image')->store('stores', 'public');
            unset($validated['image']);
        }

        $zones = $validated['delivery_zones'] ?? null;
        unset($validated['delivery_zones']);

        \Illuminate\Support\Facades\DB::transaction(function () use ($store, $validated, $zones) {
            $store->update($validated);

            if ($zones !== null) {
                $store->deliveryZones()->delete();
                foreach ($zones as $zone) {
                    $store->deliveryZones()->create($zone);
                }
            }
        });

        return response()->json([
            'message' => 'Store updated successfully.',
            'store'   => array_merge($store->fresh()->toArray(), [
                'delivery_zones' => $store->deliveryZones->map(fn ($z) => ['region' => $z->region, 'fee' => $z->fee]),
            ]),
        ], 200);
    }

    // PUT /api/stores/me/delivery-zones — kept as a thin alias for backward
    // compatibility; delegates to the same logic as update().
    public function updateDeliveryZones(Request $request)
    {
        $request->merge(['delivery_zones' => $request->input('zones', [])]);
        return $this->update($request);
    }

        // PUT /api/stores/me/delivery-zones
    public function updateDeliveryZones(Request $request)
    {
        $store = Store::where('user_id', $request->user()->id)->first();
        if (! $store) {
            $this->fail('You have not created a store yet.', 'STORE_NOT_FOUND', 404);
        }

        $validated = $request->validate([
            'zones'           => ['required', 'array'],
            'zones.*.area'    => ['required', \Illuminate\Validation\Rule::in(\App\Models\StoreDeliveryZone::AREAS)],
            'zones.*.fee'     => ['required', 'numeric', 'min:0'],
        ]);

        \Illuminate\Support\Facades\DB::transaction(function () use ($store, $validated) {
            // Replace entirely — simplest way to handle add/remove/update in one call
            $store->deliveryZones()->delete();
            foreach ($validated['zones'] as $zone) {
                $store->deliveryZones()->create($zone);
            }
        });

        return response()->json([
            'message' => 'Delivery zones updated successfully.',
            'zones'   => $store->deliveryZones()->get(['area', 'fee']),
        ]);
    }
}
