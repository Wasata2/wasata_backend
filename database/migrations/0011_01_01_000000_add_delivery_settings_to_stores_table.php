<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            // "التوصيل إلى المنزل" fee — the broker sets this once; defaults to free (0)
            // until she configures it, so 0 unambiguously means "no charge", not "unset".
            $table->decimal('delivery_fee', 10, 2)->default(0)->after('delivery_time_range');

            // "استلام من نقطة معينة" — a single pickup point the broker designates.
            // Null means she hasn't set one yet, so customers can't select pickup.
            $table->string('pickup_location')->nullable()->after('delivery_fee');
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn(['delivery_fee', 'pickup_location']);
        });
    }
};
