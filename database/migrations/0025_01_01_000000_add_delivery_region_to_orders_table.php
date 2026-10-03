<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('orders', 'delivery_area') && ! Schema::hasColumn('orders', 'delivery_region')) {
            DB::statement("ALTER TABLE orders CHANGE delivery_area delivery_region VARCHAR(30) NULL");
        } elseif (! Schema::hasColumn('orders', 'delivery_region')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->string('delivery_region', 30)->nullable()->after('delivery_method');
            });
        }
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'delivery_region')) {
                $table->dropColumn('delivery_region');
            }
        });
    }
};
