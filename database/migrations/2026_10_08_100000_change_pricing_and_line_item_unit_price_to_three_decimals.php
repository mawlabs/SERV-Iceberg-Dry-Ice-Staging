<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-unit prices can need a third decimal (e.g. $1.955/lb). Customer
     * pricing is where they are entered; invoice line item unit_price must
     * match or the extra digit is rounded away when the draft is built.
     * Totals (total_price etc.) stay at 2 decimals.
     */
    public function up(): void
    {
        Schema::table('customer_pricing', function (Blueprint $table) {
            $table->decimal('ice_price', 10, 3)->default(0)->change();
            $table->decimal('box_price', 10, 3)->default(0)->change();
            $table->decimal('hazmat_fee', 10, 3)->default(0)->change();
            $table->decimal('delivery_fee', 10, 3)->default(0)->change();
            $table->decimal('pickup_fee', 10, 3)->default(0)->change();
            $table->decimal('other_charges', 10, 3)->default(0)->change();
        });

        Schema::table('invoice_line_items', function (Blueprint $table) {
            $table->decimal('unit_price', 10, 3)->change();
        });
    }

    public function down(): void
    {
        Schema::table('invoice_line_items', function (Blueprint $table) {
            $table->decimal('unit_price', 10, 2)->change();
        });

        Schema::table('customer_pricing', function (Blueprint $table) {
            $table->decimal('ice_price', 10, 2)->default(0)->change();
            $table->decimal('box_price', 10, 2)->default(0)->change();
            $table->decimal('hazmat_fee', 10, 2)->default(0)->change();
            $table->decimal('delivery_fee', 10, 2)->default(0)->change();
            $table->decimal('pickup_fee', 10, 2)->default(0)->change();
            $table->decimal('other_charges', 10, 2)->default(0)->change();
        });
    }
};
