<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            // The specific SHEIN product the customer wants ordered under this service —
            // entered by the customer at checkout, not by the broker.
            // The actual product name the customer typed (e.g. "فستان صيفي أزرق") —
            // distinct from service_listing.title, which is the broker's general
            // service (e.g. "شحن من شي إن"), not a specific product.
            $table->string('product_name')->nullable()->after('service_listing_id');
            $table->string('product_url')->nullable()->after('product_name');
            $table->string('product_image_path')->nullable()->after('product_url');
            $table->string('color', 50)->nullable()->after('product_image_path');
            $table->string('size', 50)->nullable()->after('color');
            // Per-item note (e.g. "بدون العلبة"), distinct from orders.customer_note
            // which is a note on the whole order.
            $table->text('item_note')->nullable()->after('size');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['product_name', 'product_url', 'product_image_path', 'color', 'size', 'item_note']);
        });
    }
};
