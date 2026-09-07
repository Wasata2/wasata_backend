<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            // The top-right "استقبال الطلبات" switch — is this broker open for new orders at all?
            $table->boolean('is_accepting_orders')->default(true)->after('commission_rate');
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn('is_accepting_orders');
        });
    }
};
