<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Questions posées lors d'un passage, dans l'ordre du passage, avec l'ordre
 * des réponses affiché et la réponse choisie (PRD QCM UC-12, plan QCM §6.3).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qcm_attempt_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attempt_id')->constrained('qcm_attempts')->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->foreignId('question_id')->nullable()->constrained('qcm_questions')->nullOnDelete();
            $table->json('answer_order');
            $table->foreignId('chosen_answer_id')->nullable()->constrained('qcm_answers')->nullOnDelete();
            $table->boolean('bookmarked')->default(false);
            $table->timestamp('answered_at')->nullable();
            $table->timestamps();
            $table->auditColumns();

            $table->unique(['attempt_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qcm_attempt_items');
    }
};
