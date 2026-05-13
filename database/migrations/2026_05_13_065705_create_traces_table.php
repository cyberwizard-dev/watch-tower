<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('traces', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')
                ->constrained('projects')
                ->cascadeOnDelete();

            $table->string('correlation_id')->unique();

            $table->string('method', 10);
            $table->text('uri');
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->string('user_identifier')->nullable();
            $table->string('user_email')->nullable();

            $table->unsignedInteger('duration_ms')->nullable();
            $table->unsignedInteger('db_queries_count')->default(0);
            $table->unsignedInteger('db_time_ms')->default(0);
            $table->unsignedInteger('memory_used_kb')->nullable();
            $table->unsignedInteger('memory_peak_kb')->nullable();

            $table->string('environment', 50)->nullable();
            $table->string('release_version')->nullable();
            $table->string('hostname')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();

            $table->json('headers')->nullable();
            $table->json('request_data')->nullable();
            $table->json('response_data')->nullable();

            $table->boolean('has_errors')->default(false);
            $table->boolean('has_slow_queries')->default(false);

            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['project_id', 'occurred_at']);
            $table->index(['project_id', 'status_code']);
            $table->index(['project_id', 'environment']);
            $table->index(['project_id', 'duration_ms']);
            $table->index(['project_id', 'user_identifier']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('traces');
    }
};
