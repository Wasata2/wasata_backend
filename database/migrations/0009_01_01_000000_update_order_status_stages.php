<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1) Remap any existing data from the old status names to the closest new one,
        //    BEFORE changing the enum — otherwise MySQL rejects/blanks old values.
        DB::table('orders')->where('status', 'in_progress')->update(['status' => 'ordered_from_shein']);
        DB::table('orders')->where('status', 'ready_for_pickup')->update(['status' => 'inspected']);
        DB::table('orders')->where('status', 'completed')->update(['status' => 'received']);

        // 2) Now the enum itself matches the real 6-stage timeline (plus the two "stopped" states)
        DB::statement("ALTER TABLE orders MODIFY status ENUM(
            'pending',
            'ordered_from_shein',
            'shipped',
            'arrived',
            'inspected',
            'received',
            'rejected',
            'cancelled'
        ) NOT NULL DEFAULT 'pending'");

        // 3) New field for store cards on the browse/discovery page — e.g. "10-14 يوم"
        Schema::table('stores', function (Blueprint $table) {
            $table->string('delivery_time_range', 50)->nullable()->after('commission_rate');
        });
    }

    public function down(): void
    {
        DB::table('orders')->where('status', 'ordered_from_shein')->update(['status' => 'in_progress']);
        DB::table('orders')->where('status', 'inspected')->update(['status' => 'ready_for_pickup']);
        DB::table('orders')->where('status', 'received')->update(['status' => 'completed']);
        DB::table('orders')->whereIn('status', ['arrived'])->update(['status' => 'shipped']);

        DB::statement("ALTER TABLE orders MODIFY status ENUM(
            'pending','in_progress','ordered_from_shein','shipped',
            'ready_for_pickup','completed','rejected','cancelled'
        ) NOT NULL DEFAULT 'pending'");

        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn('delivery_time_range');
        });
    }
};
