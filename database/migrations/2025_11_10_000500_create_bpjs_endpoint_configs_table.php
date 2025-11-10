<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('bpjs_endpoint_configs')) {
            Schema::create('bpjs_endpoint_configs', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->string('url');
                $table->boolean('is_active')->default(true);
                $table->integer('timeout_seconds')->default(10);
                $table->decimal('warning_threshold_ms', 10, 2)->nullable();
                $table->decimal('critical_threshold_ms', 10, 2)->nullable();
                $table->integer('consecutive_error_threshold')->default(3);
                $table->json('custom_headers')->nullable();
                $table->string('method')->default('GET');
                $table->boolean('use_proxy')->default(false);
                $table->text('description')->nullable();
                $table->timestamps();
            });
        } else {
            Schema::table('bpjs_endpoint_configs', function (Blueprint $table) {
                if (!Schema::hasColumn('bpjs_endpoint_configs', 'method')) {
                    $table->string('method')->default('GET')->after('custom_headers');
                }
                if (!Schema::hasColumn('bpjs_endpoint_configs', 'use_proxy')) {
                    $table->boolean('use_proxy')->default(false)->after('method');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bpjs_endpoint_configs');
    }
};