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
        Schema::create('bpjs_monitoring_logs', function (Blueprint $table) {
            $table->id();
            $table->string('endpoint_name')->index();
            $table->text('endpoint_url');
            $table->decimal('response_time', 8, 2); // milliseconds
            $table->string('status_code', 10);
            $table->text('status_message')->nullable();
            $table->enum('status', ['success', 'error', 'timeout'])->index();
            $table->json('response_headers')->nullable();
            $table->text('error_details')->nullable();
            $table->timestamp('checked_at')->index();
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['endpoint_name', 'checked_at']);
            $table->index(['status', 'checked_at']);
        });

        // Create alerts table for notifications
        Schema::create('bpjs_monitoring_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('endpoint_name')->index();
            $table->enum('alert_type', ['consecutive_errors', 'response_time', 'downtime'])->index();
            $table->text('alert_message');
            $table->json('alert_data')->nullable();
            $table->boolean('is_resolved')->default(false)->index();
            $table->timestamp('triggered_at')->index();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        // Create endpoint configurations table
        Schema::create('bpjs_endpoint_configs', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('url');
            $table->boolean('is_active')->default(true)->index();
            $table->integer('timeout_seconds')->default(10);
            $table->decimal('warning_threshold_ms', 8, 2)->default(1000.00);
            $table->decimal('critical_threshold_ms', 8, 2)->default(2000.00);
            $table->integer('consecutive_error_threshold')->default(3);
            $table->json('custom_headers')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bpjs_monitoring_logs');
        Schema::dropIfExists('bpjs_monitoring_alerts');
        Schema::dropIfExists('bpjs_endpoint_configs');
    }
};
