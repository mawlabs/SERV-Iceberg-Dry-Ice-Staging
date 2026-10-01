<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_pricing', function (Blueprint $table) {
            // novex = quote from the Novex API, fixed = customer's delivery_fee,
            // none = never charge delivery (e.g. customers who always pick up).
            $table->string('delivery_mode', 20)->default('novex')->after('delivery_fee');
        });

        // Customers that already have a delivery fee keep charging exactly that.
        DB::table('customer_pricing')
            ->where('delivery_fee', '>', 0)
            ->update(['delivery_mode' => 'fixed']);
    }

    public function down(): void
    {
        Schema::table('customer_pricing', function (Blueprint $table) {
            $table->dropColumn('delivery_mode');
        });
    }
};
