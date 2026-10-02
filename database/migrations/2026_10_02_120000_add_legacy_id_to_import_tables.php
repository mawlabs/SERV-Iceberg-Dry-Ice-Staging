<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Link columns back to the legacy (live PHP site) database so `legacy:import`
// is idempotent and every imported row can be reconciled against its source.
return new class extends Migration
{
    private array $tables = ['customers', 'orders', 'recurring_orders'];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $t) use ($table) {
                // customers.legacy_id = login.customerID; orders / recurring_orders.legacy_id = orders.orderID
                $t->unsignedBigInteger('legacy_id')->nullable()->unique("{$table}_legacy_id_unique");
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $t) use ($table) {
                $t->dropUnique("{$table}_legacy_id_unique");
                $t->dropColumn('legacy_id');
            });
        }
    }
};
