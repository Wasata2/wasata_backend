<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('store_delivery_zones', 'area') && ! Schema::hasColumn('store_delivery_zones', 'region')) {
            DB::statement("ALTER TABLE store_delivery_zones CHANGE area region ENUM('gaza','khan_younis','north_gaza','middle','rafah') NOT NULL");
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('store_delivery_zones', 'region') && ! Schema::hasColumn('store_delivery_zones', 'area')) {
            DB::statement("ALTER TABLE store_delivery_zones CHANGE region area ENUM('gaza','khan_younis','north_gaza','middle','rafah') NOT NULL");
        }
    }
};