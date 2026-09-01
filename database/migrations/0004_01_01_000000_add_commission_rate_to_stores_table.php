<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            // Percentage, e.g. 12.50 = 12.5%. Each broker sets their own rate.
            $table->decimal('commission_rate', 5, 2)->default(0)->after('accepts_whatsapp_orders');
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn('commission_rate');
        });
    }
};
