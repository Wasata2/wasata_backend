<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Set by the broker when she accepts the order (moving it to
            // ordered_from_shein) — the real, confirmed total, as opposed to
            // estimated_amount which is the customer's rough guess at checkout.
            $table->decimal('final_amount', 10, 2)->nullable()->after('estimated_amount');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('final_amount');
        });
    }
};
