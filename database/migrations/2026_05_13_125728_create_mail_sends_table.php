<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_sends', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')
                ->constrained('projects')
                ->cascadeOnDelete();
            $table->string('trace_id', 36)->nullable()->index();

            $table->string('mailable_class');
            $table->string('mailer', 50)->nullable();
            $table->string('subject')->nullable();
            $table->longText('body')->nullable();
            $table->string('from_address')->nullable();
            $table->string('from_name')->nullable();

            $table->text('recipients_to')->nullable();
            $table->text('recipients_cc')->nullable();
            $table->text('recipients_bcc')->nullable();
            $table->unsignedInteger('recipients_count')->default(0);
            $table->unsignedInteger('attachments_count')->default(0);

            $table->string('queue', 100)->nullable();
            $table->string('status', 50);
            $table->unsignedInteger('duration_ms')->nullable();

            $table->string('source_type', 50)->nullable();
            $table->string('source_id')->nullable();
            $table->string('source_label')->nullable();

            $table->string('environment', 50)->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();

            $table->index(['project_id', 'mailable_class']);
            $table->index(['project_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_sends');
    }
};
