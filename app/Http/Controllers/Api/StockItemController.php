<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\StockItem;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StockItemController extends Controller
{
    private function currentStore(Request $request): Store
    {
        $store = Store::where('user_id', $request->user()->id)->first();
        abort_if(! $store, 404, 'You have not created a store yet.');
        return $store;
    }

        private function rules(string $mode): array
    {
        $required = $mode === 'store' ? 'required' : 'sometimes';

        return [
            'name'     => [$required, 'string', 'max:150'],
            'category' => [$required, Rule::in(['clothes', 'shoes'])],
            'price'    => [$required, 'numeric', 'min:0'],
            'size'     => ['sometimes', 'nullable', 'string', 'max:20'],
            'color'    => ['sometimes', 'nullable', 'string', 'max:50'],
            'image'    => ['sometimes', 'nullable', 'image', 'max:4096'], // 4MB max, same limit as everywhere else
        ];
    }

    private function applySortAndFilters($query, Request $request): void
    {
        if ($request->filled('category') && $request->category !== 'all') {
            $query->where('category', $request->category);
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $query->where('name', 'like', '%'.$request->search.'%');
        }

        match ($request->query('sort', 'newest')) {
            'oldest'     => $query->oldest(),
            'price_asc'  => $query->orderBy('price', 'asc'),
            'price_desc' => $query->orderBy('price', 'desc'),
            default      => $query->latest(),
        };
    }

    // GET /api/stock-items — the broker's own "القطع الراكدة" page
    // ?category=clothes|shoes   ?status=unlisted|listed|reserved|sold
    // ?search=...   ?sort=newest|oldest|price_asc|price_desc
    public function index(Request $request)
    {
        $store = $this->currentStore($request);

        $query = StockItem::where('store_id', $store->id);
        $this->applySortAndFilters($query, $request);

        return response()->json(['items' => $query->get()]);
    }

    // POST /api/stock-items — "+ إضافة قطعة جديدة"
    public function store(Request $request)
    {
        $store = $this->currentStore($request);

        $validated = $request->validate($this->rules('store'));

        // Files arrive separately from validate()'s return value — same pattern
        // used in StoreController::store() and OrderController::store().
        if ($request->hasFile('image')) {
            $validated['image_path'] = $request->file('image')->store('stock-items', 'public');
        }
        unset($validated['image']);

        $item = StockItem::create([...$validated, 'store_id' => $store->id]);

        return response()->json([
            'message' => 'Item added successfully.',
            'item'    => $item,
        ], 201);
    }
    // PATCH /api/stock-items/{item} — "تعديل العرض"
    public function update(Request $request, StockItem $item)
    {
        $store = $this->currentStore($request);
        abort_if($item->store_id !== $store->id, 403, 'This item does not belong to your store.');

        $validated = $request->validate($this->rules('update'));

        if ($request->hasFile('image')) {
            $validated['image_path'] = $request->file('image')->store('stock-items', 'public');
        }
        unset($validated['image']);

        $item->update($validated);

        return response()->json([
            'message' => 'Item updated successfully.',
            'item'    => $item,
        ]);
    }

    // PATCH /api/stock-items/{item}/list — "عرض للبيع": unlisted -> listed
    public function list(Request $request, StockItem $item)
    {
        $store = $this->currentStore($request);
        abort_if($item->store_id !== $store->id, 403, 'This item does not belong to your store.');
        abort_unless($item->status === 'unlisted', 422, 'Only an unlisted item can be offered for sale.');

        $item->update(['status' => 'listed']);

        return response()->json(['message' => 'Item is now listed for sale.', 'item' => $item]);
    }

    // PATCH /api/stock-items/{item}/unlist — "إلغاء العرض": listed -> unlisted
    public function unlist(Request $request, StockItem $item)
    {
        $store = $this->currentStore($request);
        abort_if($item->store_id !== $store->id, 403, 'This item does not belong to your store.');
        abort_unless($item->status === 'listed', 422, 'Only a listed item can be unlisted.');

        $item->update(['status' => 'unlisted']);

        return response()->json(['message' => 'Item is no longer listed for sale.', 'item' => $item]);
    }

    // PATCH /api/stock-items/{item}/cancel-reservation — "إلغاء الحجز": reserved -> listed
    public function cancelReservation(Request $request, StockItem $item)
    {
        $store = $this->currentStore($request);
        abort_if($item->store_id !== $store->id, 403, 'This item does not belong to your store.');
        abort_unless($item->status === 'reserved', 422, 'This item is not currently reserved.');

        $item->update(['status' => 'listed', 'customer_id' => null]);

        return response()->json(['message' => 'Reservation cancelled.', 'item' => $item]);
    }

    // PATCH /api/stock-items/{item}/confirm-sale — "تأكيد البيع": reserved -> sold
    public function confirmSale(Request $request, StockItem $item)
    {
        $store = $this->currentStore($request);
        abort_if($item->store_id !== $store->id, 403, 'This item does not belong to your store.');
        abort_unless($item->status === 'reserved', 422, 'Only a reserved item can be marked as sold.');

        $item->update(['status' => 'sold']);

        return response()->json(['message' => 'Sale confirmed.', 'item' => $item]);
    }

    // DELETE /api/stock-items/{item}
    public function destroy(Request $request, StockItem $item)
    {
        $store = $this->currentStore($request);
        abort_if($item->store_id !== $store->id, 403, 'This item does not belong to your store.');

        $item->delete();

        return response()->json(['message' => 'Item deleted successfully.']);
    }

    // GET /api/stores/{store}/stock-items — CUSTOMER browsing one store's items for sale
    // Only "listed" items are shown — not unlisted, reserved, or already sold.
    public function forStore(Request $request, Store $store)
    {
        abort_unless($store->status === 'published', 404, 'This store is not available.');

        $query = StockItem::where('store_id', $store->id)->where('status', 'listed');
        $this->applySortAndFilters($query, $request);

        return response()->json(['items' => $query->get()]);
    }

    // PATCH /api/stock-items/{item}/reserve — the CUSTOMER requesting to buy it
    public function reserve(Request $request, StockItem $item)
    {
        abort_unless($item->status === 'listed', 422, 'This item is not available for reservation.');

        $item->update([
            'status'      => 'reserved',
            'customer_id' => $request->user()->id,
        ]);

        return response()->json(['message' => 'Item reserved successfully.', 'item' => $item]);
    }
}
