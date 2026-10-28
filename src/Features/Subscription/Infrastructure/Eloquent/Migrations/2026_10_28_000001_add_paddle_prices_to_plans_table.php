<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Paddle sets a trial on the price, not on the checkout: a plan with a
     * trial has a second price, with the trial, for the organizations that
     * have not used one yet.
     */
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->string('paddle_price_id')->nullable()->after('stripe_price_id')->index();
            $table->string('paddle_trial_price_id')->nullable()->after('paddle_price_id')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropIndex(['paddle_price_id']);
            $table->dropIndex(['paddle_trial_price_id']);
            $table->dropColumn(['paddle_price_id', 'paddle_trial_price_id']);
        });
    }
};
