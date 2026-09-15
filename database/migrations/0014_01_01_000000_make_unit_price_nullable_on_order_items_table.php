<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Raw SQL instead of Schema::change() — avoids needing the doctrine/dbal package.
        DB::statement('ALTER TABLE order_items MODIFY unit_price DECIMAL(10,2) NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE order_items MODIFY unit_price DECIMAL(10,2) NOT NULL');
    }
};
