<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Cashier 16 writes the meter of each item when a subscription changes
     * price (swap); its own migrations add these columns.
     */
    public function up(): void
    {
        Schema::table('subscription_items', function (Blueprint $table) {
            if (! Schema::hasColumn('subscription_items', 'meter_id')) {
                $table->string('meter_id')->nullable()->after('stripe_price');
            }

            if (! Schema::hasColumn('subscription_items', 'meter_event_name')) {
                $table->string('meter_event_name')->nullable()->after('quantity');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscription_items', function (Blueprint $table) {
            $table->dropColumn(['meter_id', 'meter_event_name']);
        });
    }
};
