<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The subscriptions Polar bills, as its webhooks describe them. The
     * organization is Polar's customer: its id is the customer's external ID,
     * so no customers table is needed. plan_id links a subscription to the
     * application's plan catalogue.
     */
    public function up(): void
    {
        Schema::create('polar_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('organization_id')
                ->constrained('organizations')
                ->cascadeOnDelete();
            $table->foreignUuid('plan_id')->nullable()->constrained('plans')->nullOnDelete();
            $table->string('polar_id')->unique();
            $table->string('polar_customer_id')->nullable()->index();
            $table->string('polar_product_id')->nullable();
            $table->string('status');
            $table->unsignedInteger('seats')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            // Trials are offered once per organization.
            $table->boolean('had_trial')->default(false);
            $table->timestamp('current_period_end')->nullable();
            // When the subscription ends (canceled at the end of the period)
            // or ended (revoked).
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('past_due_at')->nullable();
            $table->timestamp('payment_grace_ended_at')->nullable();
            // Polar's modified_at (ISO 8601, to the microsecond) of the state
            // stored here: webhooks can arrive out of order, an older state
            // never overwrites a newer one.
            $table->string('polar_modified_at', 40)->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('polar_subscriptions');
    }
};
