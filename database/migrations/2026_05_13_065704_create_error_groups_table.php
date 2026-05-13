<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('error_groups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')
                ->constrained('projects')
                ->cascadeOnDelete();

            $table->string('fingerprint', 64);
            $table->string('exception_class');
            $table->text('first_message')->nullable();
            $table->string('first_file')->nullable();
            $table->unsignedInteger('first_line')->nullable();

            $table->unsignedBigInteger('total_count')->default(1);
            $table->timestamp('first_occurrence_at');
            $table->timestamp('last_occurrence_at');

            $table->string('status')->default('unresolved');
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution_note')->nullable();
            $table->json('tags')->nullable();

            $table->timestamps();

            $table->unique(['project_id', 'fingerprint']);
            $table->index(['project_id', 'status']);
            $table->index(['project_id', 'last_occurrence_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('error_groups');
    }
};
