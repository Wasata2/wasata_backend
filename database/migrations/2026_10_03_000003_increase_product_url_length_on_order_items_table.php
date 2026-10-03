<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE order_items MODIFY product_url VARCHAR(2048) NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE order_items MODIFY product_url VARCHAR(255) NULL');
    }
};
