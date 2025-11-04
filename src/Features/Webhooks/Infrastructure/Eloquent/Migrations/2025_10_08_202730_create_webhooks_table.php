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
        Schema::create('webhooks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('url');
            $table->json('events')->comment('Array of event names to subscribe to');
            $table->string('secret')->nullable()->comment('Secret for HMAC signature verification');
            $table->boolean('is_active')->default(true);
            $table->json('headers')->nullable()->comment('Additional headers to send with webhook');
            $table->integer('timeout')->default(30)->comment('Request timeout in seconds');
            $table->integer('retry_limit')->default(3)->comment('Number of retry attempts');
            $table->timestamp('last_triggered_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhooks');
    }
};
