<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_listings', function (Blueprint $table) {
            // Remove the old, wrong assumptions (photo upload, fixed price, category)
            $table->dropColumn(['photo_path', 'price', 'category', 'estimated_delivery', 'status']);

            // Add what the real "إضافة/تعديل خدمة" form actually has
            $table->string('icon', 30)->after('title');           // one of a fixed icon set — see model
            $table->text('description')->nullable()->after('icon');
            $table->enum('fee_type', ['free', 'fixed', 'percentage', 'variable'])
                  ->default('free')->after('description');        // مجاني / مبلغ ثابت / نسبة مئوية / حسب الحالة
            $table->decimal('fee_amount', 10, 2)->nullable()->after('fee_type'); // only used for fixed/percentage
            $table->text('notes')->nullable()->after('fee_amount');             // ملاحظات أو شروط الخدمة
            $table->boolean('is_available')->default(true)->after('notes');     // متاحة toggle
        });
    }

    public function down(): void
    {
        Schema::table('service_listings', function (Blueprint $table) {
            $table->dropColumn(['icon', 'description', 'fee_type', 'fee_amount', 'notes', 'is_available']);
            $table->string('photo_path')->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->string('category', 100)->nullable();
            $table->string('estimated_delivery', 100)->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
        });
    }
};
