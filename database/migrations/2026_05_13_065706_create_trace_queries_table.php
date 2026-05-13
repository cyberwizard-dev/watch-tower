<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trace_queries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')
                ->constrained('projects')
                ->cascadeOnDelete();
            $table->foreignUuid('trace_id')
                ->constrained('traces')
                ->cascadeOnDelete();

            $table->string('query_type', 20)->nullable();
            $table->text('sql');
            $table->json('bindings')->nullable();
            $table->string('connection_name', 100)->nullable();

            $table->decimal('duration_ms', 12, 3);
            $table->unsignedBigInteger('row_count')->nullable();

            $table->boolean('is_n_plus_one')->default(false);
            $table->string('n_plus_one_group')->nullable();
            $table->boolean('is_slow')->default(false);

            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index('trace_id');
            $table->index(['project_id', 'is_slow', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trace_queries');
    }
};
