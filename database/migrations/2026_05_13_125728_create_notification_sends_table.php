<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_sends', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')
                ->constrained('projects')
                ->cascadeOnDelete();
            $table->string('trace_id', 36)->nullable()->index();

            $table->string('notification_class');
            $table->string('channel', 50);
            $table->string('notifiable_type')->nullable();
            $table->string('notifiable_id')->nullable();

            $table->string('queue', 100)->nullable();
            $table->string('status', 50);
            $table->unsignedInteger('duration_ms')->nullable();

            $table->string('source_type', 50)->nullable();
            $table->string('source_id')->nullable();
            $table->string('source_label')->nullable();

            $table->string('environment', 50)->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();

            $table->index(['project_id', 'notification_class']);
            $table->index(['project_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_sends');
    }
};
