<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->enum('delivery_method', ['home_delivery', 'pickup'])
                  ->default('home_delivery')
                  ->after('customer_note');

            // Snapshot of the fee AT THE TIME the order was placed — never re-read from
            // stores.delivery_fee later, so a broker changing her fee doesn't rewrite
            // the price of past orders. Null for pickup, since pickup has no fee.
            $table->decimal('delivery_fee', 10, 2)->nullable()->after('delivery_method');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['delivery_method', 'delivery_fee']);
        });
    }
};
