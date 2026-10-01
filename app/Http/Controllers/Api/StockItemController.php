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
        if (! $store) {
            $this->fail('You have not created a store yet.', 'STORE_NOT_FOUND', 404);
        }
        return $store;
    }

    private function rules(string $mode): array
    {
        $required = $mode === 'store' ? 'required' : 'sometimes';

        return [
            'name'     => [$required, 'string', 'max:150'],
            'category' => [$required, Rule::in(['clothes', 'shoes'])],
            'price'    => [$required, 'numeric', 'min:0'],
            'size'     => ['nullable', 'string', 'max:50'],
            'color'    => ['nullable', 'string', 'max:50'],
            'image'    => ['nullable', 'image', 'max:4096'],
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
        // used in StoreController/OrderController/AuthController.
        if ($request->hasFile('image')) {
            $validated['image_path'] = $request->file('image')->store('stock-items', 'public');
        }
        unset($validated['image']);

        $item = StockItem::create([
            ...$validated,
            'store_id' => $store->id,
            // Set explicitly — a column's DB-level default() is NOT reflected on
            // the in-memory model returned right after create(), so the immediate
            // JSON response would have status: null/undefined otherwise.
            'status' => 'unlisted',
        ]);

        return response()->json([
            'message' => 'Item added successfully.',
            'item'    => $item,
        ], 201);
    }

    // PATCH /api/stock-items/{item} — "تعديل العرض"
        public function update(Request $request, StockItem $item)
    {
        $store = $this->currentStore($request);
        if ($item->store_id !== $store->id) {
            $this->fail('This item does not belong to your store.', 'STOCK_ITEM_NOT_YOURS', 403);
        }
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
        if ($item->store_id !== $store->id) {
            $this->fail('This item does not belong to your store.', 'STOCK_ITEM_NOT_YOURS', 403);
        }
        if ($item->status !== 'unlisted') {
            $this->fail('Only an unlisted item can be offered for sale.', 'STOCK_ITEM_NOT_UNLISTED');
        }
        $item->update(['status' => 'listed']);

        return response()->json(['message' => 'Item is now listed for sale.', 'item' => $item]);
    }

    // PATCH /api/stock-items/{item}/unlist — "إلغاء العرض": listed -> unlisted
    public function unlist(Request $request, StockItem $item)
    {
        $store = $this->currentStore($request);
        if ($item->store_id !== $store->id) {
            $this->fail('This item does not belong to your store.', 'STOCK_ITEM_NOT_YOURS', 403);
        }
        if ($item->status !== 'listed') {
            $this->fail('Only a listed item can be unlisted.', 'STOCK_ITEM_NOT_LISTED');
        }
        $item->update(['status' => 'unlisted']);

        return response()->json(['message' => 'Item is no longer listed for sale.', 'item' => $item]);
    }

    // PATCH /api/stock-items/{item}/cancel-reservation — "إلغاء الحجز": reserved -> listed
    public function cancelReservation(Request $request, StockItem $item)
    {
        $store = $this->currentStore($request);
        if ($item->store_id !== $store->id) {
            $this->fail('This item does not belong to your store.', 'STOCK_ITEM_NOT_YOURS', 403);
        }
        if ($item->status !== 'reserved') {
            $this->fail('This item is not currently reserved.', 'STOCK_ITEM_NOT_RESERVED');
        }
               $buyerId = $item->customer_id;
        $item->update(['status' => 'listed', 'customer_id' => null]);

        if ($buyerId) {
            \App\Models\Notification::notify(
                $buyerId,
                'stock_item_reservation_cancelled',
                "تم إلغاء حجز: {$item->name}",
                null,
                ['item_id' => $item->id]
            );
        }

        return response()->json(['message' => 'Reservation cancelled.', 'item' => $item]);
    }

    // PATCH /api/stock-items/{item}/confirm-sale — "تأكيد البيع": reserved -> sold
    public function confirmSale(Request $request, StockItem $item)
    {
        $store = $this->currentStore($request);
        if ($item->store_id !== $store->id) {
            $this->fail('This item does not belong to your store.', 'STOCK_ITEM_NOT_YOURS', 403);
        }
        if ($item->status !== 'reserved') {
            $this->fail('Only a reserved item can be marked as sold.', 'STOCK_ITEM_NOT_RESERVED');
        }

                $buyerId = $item->customer_id;
        $item->update(['status' => 'sold']);

        if ($buyerId) {
            \App\Models\Notification::notify(
                $buyerId,
                'stock_item_sold',
                "تم تأكيد بيع: {$item->name}",
                null,
                ['item_id' => $item->id]
            );
        }

        return response()->json(['message' => 'Sale confirmed.', 'item' => $item]);
    }

    // DELETE /api/stock-items/{item}
    public function destroy(Request $request, StockItem $item)
    {
        $store = $this->currentStore($request);
        if ($item->store_id !== $store->id) {
            $this->fail('This item does not belong to your store.', 'STOCK_ITEM_NOT_YOURS', 403);
        }
        $item->delete();

        return response()->json(['message' => 'Item deleted successfully.']);
    }

    // GET /api/stores/{store}/stock-items — CUSTOMER browsing one store's items for sale
    // Only "listed" items are shown — not unlisted, reserved, or already sold.
    public function forStore(Request $request, Store $store)
    {
        if ($store->status !== 'published') {
            $this->fail('This store is not available.', 'STORE_NOT_PUBLISHED', 404);
        }
        $query = StockItem::where('store_id', $store->id)->where('status', 'listed');
        $this->applySortAndFilters($query, $request);

        return response()->json(['items' => $query->get()]);
    }

    // PATCH /api/stock-items/{item}/reserve — the CUSTOMER requesting to buy it
    public function reserve(Request $request, StockItem $item)
    {
        if ($item->status !== 'listed') {
            $this->fail('This item is not available for reservation.', 'STOCK_ITEM_NOT_LISTED');
        }

        $item->update([
            'status'      => 'reserved',
            'customer_id' => $request->user()->id,
        ]);

            \App\Models\Notification::notify(
            $item->store->user_id,
            'stock_item_reserved',
            "تم حجز قطعة: {$item->name}",
            null,
            ['item_id' => $item->id]
        );

        return response()->json(['message' => 'Item reserved successfully.', 'item' => $item]);


    }
}
