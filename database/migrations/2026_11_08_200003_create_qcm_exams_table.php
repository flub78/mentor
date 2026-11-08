<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Examens : un questionnaire complet, une durée, un taux de réussite minimal
 * et des options de passage (PRD QCM §3, UC-20).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qcm_exams', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('instructions')->nullable();
            $table->foreignId('questionnaire_id')->constrained('qcm_questionnaires')->restrictOnDelete();
            $table->unsignedSmallInteger('duration_minutes')->nullable();
            $table->unsignedTinyInteger('pass_rate');
            $table->boolean('shuffle_questions')->default(true);
            $table->boolean('shows_correction')->default(true);
            $table->unsignedSmallInteger('max_attempts')->nullable();
            $table->json('identification_fields');
            $table->string('identifier_label')->nullable();
            $table->timestamps();
            $table->auditColumns();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qcm_exams');
    }
};
