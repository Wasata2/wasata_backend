<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_delivery_zones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->enum('area', ['gaza', 'khan_younis', 'north_gaza', 'middle', 'rafah']);
            $table->decimal('fee', 10, 2);
            $table->timestamps();
            $table->unique(['store_id', 'area']); // a store can't have two fees for the same area
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_delivery_zones');
    }
};
