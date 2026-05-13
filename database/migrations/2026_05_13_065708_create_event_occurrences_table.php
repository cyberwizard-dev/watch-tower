<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_occurrences', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')
                ->constrained('projects')
                ->cascadeOnDelete();
            $table->foreignUuid('trace_id')
                ->nullable()
                ->constrained('traces')
                ->cascadeOnDelete();

            $table->string('event_class');
            $table->string('fired_by')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->unsignedInteger('listeners_count')->default(0);

            $table->json('payload')->nullable();

            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index('trace_id');
            $table->index(['project_id', 'event_class']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_occurrences');
    }
};
