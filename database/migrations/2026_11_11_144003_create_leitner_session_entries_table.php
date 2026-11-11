<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Questions d'une séance, dans l'ordre de présentation (PRD Leitner UC-08).
 * Les entrées sont créées au lancement ; chaque réponse est écrite dès
 * qu'elle est donnée, avec la boîte avant et après.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leitner_session_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('leitner_sessions')->cascadeOnDelete();
            $table->foreignId('question_id')->nullable()->constrained('leitner_questions')->nullOnDelete();
            $table->foreignId('retry_of_id')->nullable()->constrained('leitner_session_entries')->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->boolean('is_retry')->default(false);
            $table->boolean('is_new')->default(false);
            $table->unsignedTinyInteger('box_before')->nullable();
            $table->unsignedSmallInteger('failures_before')->nullable();
            $table->json('response')->nullable();
            $table->string('verdict', 20)->nullable();
            $table->text('detail')->nullable();
            $table->unsignedTinyInteger('box_after')->nullable();
            $table->boolean('mastered')->default(false);
            $table->timestamp('answered_at')->nullable();
            $table->timestamp('skipped_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamps();
            $table->auditColumns();

            $table->unique(['session_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leitner_session_entries');
    }
};
