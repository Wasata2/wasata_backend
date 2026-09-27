<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Raw SQL instead of Schema::change() — avoids needing the doctrine/dbal
        // package, same pattern used in migration 0014.
        DB::statement('ALTER TABLE order_items MODIFY service_listing_id BIGINT UNSIGNED NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE order_items MODIFY service_listing_id BIGINT UNSIGNED NOT NULL');
    }
};
