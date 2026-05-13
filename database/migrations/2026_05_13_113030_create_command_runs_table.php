<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('command_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')
                ->constrained('projects')
                ->cascadeOnDelete();

            $table->string('command');
            $table->json('arguments')->nullable();
            $table->json('options')->nullable();

            $table->string('status', 50);
            $table->integer('exit_code')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();

            $table->longText('output')->nullable();

            $table->string('environment', 50)->nullable();
            $table->timestamp('occurred_at')->index();

            $table->timestamps();

            $table->index(['project_id', 'command']);
            $table->index(['project_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('command_runs');
    }
};
