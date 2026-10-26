<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bill of lading inputs: the dry ice weight (lb and kg kept in step by the
     * order form; the BOL prints kg) and an optional order-specific consignee
     * that takes priority over the delivery address.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('weight_lb', 10, 2)->nullable()->after('notes');
            $table->decimal('weight_kg', 10, 2)->nullable()->after('weight_lb');
            $table->string('consignee_name')->nullable()->after('weight_kg');
            $table->string('consignee_address')->nullable()->after('consignee_name');
            $table->string('consignee_phone', 50)->nullable()->after('consignee_address');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['weight_lb', 'weight_kg', 'consignee_name', 'consignee_address', 'consignee_phone']);
        });
    }
};
