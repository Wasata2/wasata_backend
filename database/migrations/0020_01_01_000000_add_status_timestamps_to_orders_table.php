<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // "pending" itself has no column — it's always orders.created_at.
            // One nullable timestamp per stage the order can reach.
            $table->timestamp('ordered_from_shein_at')->nullable()->after('status');
            $table->timestamp('shipped_at')->nullable()->after('ordered_from_shein_at');
            $table->timestamp('arrived_at')->nullable()->after('shipped_at');
            $table->timestamp('inspected_at')->nullable()->after('arrived_at');
            $table->timestamp('received_at')->nullable()->after('inspected_at');
            $table->timestamp('rejected_at')->nullable()->after('received_at');
            $table->timestamp('cancelled_at')->nullable()->after('rejected_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'ordered_from_shein_at', 'shipped_at', 'arrived_at',
                'inspected_at', 'received_at', 'rejected_at', 'cancelled_at',
            ]);
        });
    }
};