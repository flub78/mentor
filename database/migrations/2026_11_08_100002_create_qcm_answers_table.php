<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Réponses d'une question : une bonne réponse et au moins une mauvaise
 * (PRD QCM §3, UC-04).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qcm_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained('qcm_questions')->cascadeOnDelete();
            $table->text('text');
            $table->boolean('is_correct')->default(false);
            $table->text('explanation')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
            $table->auditColumns();

            $table->index(['question_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qcm_answers');
    }
};
