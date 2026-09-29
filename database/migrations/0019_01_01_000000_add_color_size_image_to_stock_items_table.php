<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_items', function (Blueprint $table) {
            $table->string('size', 20)->nullable()->after('category');   // "المقاس (اختياري)"
            $table->string('color', 50)->nullable()->after('size');      // "اللون (اختياري)"
            $table->string('image_path')->nullable()->after('color');    // "صورة القطعة (اختياري)"
        });
    }

    public function down(): void
    {
        Schema::table('stock_items', function (Blueprint $table) {
            $table->dropColumn(['size', 'color', 'image_path']);
        });
    }
};
