<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ServiceListing;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ServiceController extends Controller
{
    private function currentStore(Request $request): Store
    {
        $store = Store::where('user_id', $request->user()->id)->first();
        if (! $store) {
            $this->fail('You have not created a store yet.', 'STORE_NOT_FOUND', 404);
        }
        return $store;
    }

    // Reusable validation rules for both store() and update()
    private function rules(string $mode): array
    {
        $required = $mode === 'store' ? 'required' : 'sometimes';

        return [
            'title'        => [$required, 'string', 'max:150'],
            'icon'         => [$required, Rule::in(ServiceListing::ICONS)],
            'description'  => ['nullable', 'string'],
            'fee_type'     => [$required, Rule::in(['free', 'fixed', 'percentage', 'variable'])],
            // fee_amount is only meaningful (and required) when fee_type is fixed or percentage
            'fee_amount'   => ['required_if:fee_type,fixed,percentage', 'nullable', 'numeric', 'min:0'],
            'notes'        => ['nullable', 'string'],
            'is_available' => ['sometimes', 'boolean'],
        ];
    }

    // GET /api/services — list this broker's own services
    public function index(Request $request)
    {
        $store = $this->currentStore($request);

        return response()->json([
            'services' => $store->serviceListings,
        ]);
    }

    // POST /api/services — "+ إضافة خدمة"
    public function store(Request $request)
    {
        $store = $this->currentStore($request);

        $validated = $request->validate($this->rules('store'));

        // A free/variable service has no fixed fee_amount — force it to null for clarity
        if (in_array($validated['fee_type'], ['free', 'variable'])) {
            $validated['fee_amount'] = null;
        }

        $service = ServiceListing::create([...$validated, 'store_id' => $store->id]);

        return response()->json([
            'message' => 'Service added successfully.',
            'service' => $service,
        ], 201);
    }

    // PATCH /api/services/{service} — "تعديل"
        public function update(Request $request, ServiceListing $service)
    {
        $store = $this->currentStore($request);
        if ($service->store_id !== $store->id) {
            $this->fail('This service does not belong to your store.', 'SERVICE_NOT_YOURS', 403);
        }

        $validated = $request->validate($this->rules('update'));

        if (isset($validated['fee_type']) && in_array($validated['fee_type'], ['free', 'variable'])) {
            $validated['fee_amount'] = null;
        }

        $service->update($validated);

        return response()->json([
            'message' => 'Service updated successfully.',
            'service' => $service,
        ], 200);
    }

    // PATCH /api/services/{service}/toggle — the "تفعيل" / "تعطيل" button
    public function toggle(Request $request, ServiceListing $service)
    {
        $store = $this->currentStore($request);
        if ($service->store_id !== $store->id) {
            $this->fail('This service does not belong to your store.', 'SERVICE_NOT_YOURS', 403);
        }
        $service->update(['is_available' => ! $service->is_available]);

        return response()->json([
            'message' => $service->is_available ? 'Service enabled.' : 'Service disabled.',
            'service' => $service,
        ], 200);
    }

    // DELETE /api/services/{service} — the "حذف" button
    public function destroy(Request $request, ServiceListing $service)
    {
        $store = $this->currentStore($request);
        if ($service->store_id !== $store->id) {
            $this->fail('This service does not belong to your store.', 'SERVICE_NOT_YOURS', 403);
        }

        // The service has already been used in real orders — deleting it would
        // break those orders' history (order_items references it with restrictOnDelete).
        // Guide the broker to disable it instead of deleting.
        if ($service->orderItems()->exists()) {
            $this->fail('This service has existing orders and cannot be deleted. Disable it instead.', 'SERVICE_HAS_ORDERS');
        }

        $service->delete();

        return response()->json([
            'message' => 'Service deleted successfully.',
        ], 200);
    }
}
