<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('error_occurrences', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')
                ->constrained('projects')
                ->cascadeOnDelete();
            $table->foreignUuid('trace_id')
                ->nullable()
                ->constrained('traces')
                ->nullOnDelete();
            $table->foreignUuid('error_group_id')
                ->nullable()
                ->constrained('error_groups')
                ->nullOnDelete();

            $table->string('exception_class');
            $table->text('message')->nullable();
            $table->json('stacktrace');

            $table->string('fingerprint', 64);

            $table->string('user_identifier')->nullable();
            $table->string('user_email')->nullable();
            $table->string('file')->nullable();
            $table->unsignedInteger('line')->nullable();
            $table->string('environment', 50)->nullable();
            $table->string('release_version')->nullable();

            $table->json('context')->nullable();

            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['project_id', 'occurred_at']);
            $table->index(['project_id', 'fingerprint']);
            $table->index('error_group_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('error_occurrences');
    }
};
