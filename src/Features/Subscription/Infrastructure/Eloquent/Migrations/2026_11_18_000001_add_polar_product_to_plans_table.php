<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A Polar product holds a single recurring price: each paid plan is sold
     * as one product. Trials are set on the checkout, so one product serves
     * with and without a trial.
     */
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->string('polar_product_id')->nullable()->after('paddle_trial_price_id')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropIndex(['polar_product_id']);
            $table->dropColumn('polar_product_id');
        });
    }
};
