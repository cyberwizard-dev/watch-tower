<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('project_id')
                ->constrained('projects')
                ->cascadeOnDelete();

            $table->timestamp('period_start');
            $table->timestamp('period_end');
            $table->string('aggregation_level', 20);
            $table->string('environment', 50)->nullable();

            $table->unsignedBigInteger('requests_count')->default(0);
            $table->unsignedBigInteger('errors_count')->default(0);
            $table->unsignedBigInteger('slow_requests_count')->default(0);

            $table->decimal('avg_response_time_ms', 12, 2)->nullable();
            $table->unsignedInteger('p50_response_time_ms')->nullable();
            $table->unsignedInteger('p95_response_time_ms')->nullable();
            $table->unsignedInteger('p99_response_time_ms')->nullable();

            $table->unsignedBigInteger('total_queries')->default(0);
            $table->decimal('avg_query_time_ms', 12, 3)->nullable();

            $table->timestamps();

            $table->unique(['project_id', 'period_start', 'aggregation_level', 'environment'], 'metrics_project_period_unique');
            $table->index(['project_id', 'period_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('metrics');
    }
};
