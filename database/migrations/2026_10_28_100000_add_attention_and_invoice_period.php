<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * customers.attention: optional "person receiving the invoice", printed on invoices.
     * invoices.period_start/period_end: the date range the admin selected when building the
     * invoice, so the draft shows the real range instead of the dates of the orders found.
     */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('attention')->nullable();
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('attention');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['period_start', 'period_end']);
        });
    }
};
