<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Store;
use Illuminate\Http\Request;

class StoreController extends Controller
{
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
}
