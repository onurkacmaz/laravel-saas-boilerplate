<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('webhook_id')->constrained()->cascadeOnDelete();
            $table->string('event_name');
            $table->json('payload')->comment('Event payload sent to webhook');
            $table->string('status')->default('pending')->comment('pending, success, failed');
            $table->integer('status_code')->nullable()->comment('HTTP status code from webhook endpoint');
            $table->text('response_body')->nullable()->comment('Response from webhook endpoint');
            $table->text('error_message')->nullable()->comment('Error message if delivery failed');
            $table->integer('attempt')->default(1)->comment('Delivery attempt number');
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->index(['webhook_id', 'status']);
            $table->index(['webhook_id', 'created_at']);
            $table->index('event_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
    }
};
