<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * past_due_at: when a renewal payment failed, which starts the payment
     * grace period (billing.past_due_grace_days). payment_grace_ended_at:
     * when the end of that period was reported. Both are cleared once the
     * subscription is no longer past due.
     */
    public function up(): void
    {
        foreach (['subscriptions' => 'stripe_status', 'paddle_subscriptions' => 'status'] as $table => $status) {
            Schema::table($table, function (Blueprint $table) {
                $table->timestamp('past_due_at')->nullable();
                $table->timestamp('payment_grace_ended_at')->nullable();
            });

            // Subscriptions already past due: the last change is the best
            // estimate of when the payment failed.
            DB::table($table)->where($status, 'past_due')->update(['past_due_at' => DB::raw('updated_at')]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['subscriptions', 'paddle_subscriptions'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropColumn(['past_due_at', 'payment_grace_ended_at']);
            });
        }
    }
};
