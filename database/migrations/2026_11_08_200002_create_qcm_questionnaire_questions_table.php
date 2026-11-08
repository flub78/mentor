<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Liste ordonnée des questions d'un questionnaire, propres ou de banque
 * (PRD QCM UC-08).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qcm_questionnaire_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('questionnaire_id')->constrained('qcm_questionnaires')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('qcm_questions')->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->auditColumns();

            $table->unique(['questionnaire_id', 'question_id']);
            $table->index(['questionnaire_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qcm_questionnaire_questions');
    }
};
