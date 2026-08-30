<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->enum('status', [
                'pending',            // "قيد الانتظار" — just came in, broker hasn't acted yet
                'in_progress',        // broker accepted it
                'ordered_from_shein',
                'shipped',
                'ready_for_pickup',
                'completed',
                'rejected',
                'cancelled',
            ])->default('pending');
            $table->decimal('estimated_amount', 10, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
