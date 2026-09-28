<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            // The customer who reserved/bought it — null until someone does.
            $table->foreignId('customer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 150);
            $table->enum('category', ['clothes', 'shoes']);
            $table->decimal('price', 10, 2);
            // unlisted: just added, not visible to customers yet ("غير معروضة")
            // listed: visible and biddable ("معروضة للبيع")
            // reserved: a customer asked to buy it ("محجوزة")
            // sold: the broker confirmed the sale ("تم البيع")
            $table->enum('status', ['unlisted', 'listed', 'reserved', 'sold'])->default('unlisted');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_items');
    }
};
