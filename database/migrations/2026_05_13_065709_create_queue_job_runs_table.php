<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('queue_job_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')
                ->constrained('projects')
                ->cascadeOnDelete();
            $table->foreignUuid('trace_id')
                ->nullable()
                ->constrained('traces')
                ->nullOnDelete();

            $table->string('job_class');
            $table->string('queue', 100)->nullable();
            $table->string('connection', 100)->nullable();

            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();

            $table->unsignedInteger('duration_ms')->nullable();
            $table->unsignedInteger('attempts')->default(0);

            $table->string('status', 50);

            $table->json('payload')->nullable();
            $table->json('exception')->nullable();

            $table->string('environment', 50)->nullable();
            $table->timestamps();

            $table->index(['project_id', 'status']);
            $table->index(['project_id', 'job_class']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('queue_job_runs');
    }
};
