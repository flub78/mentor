<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * État d'une question introduite dans une progression (PRD Leitner §3, §7) :
 * boîte, maîtrise, suspension, échecs consécutifs. Une question des sources
 * sans ligne ici est « en banque ».
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leitner_progress_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('progression_id')->constrained('leitner_progressions')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('leitner_questions')->cascadeOnDelete();
            $table->unsignedTinyInteger('box')->nullable();
            $table->timestamp('mastered_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->unsignedSmallInteger('consecutive_failures')->default(0);
            $table->timestamp('introduced_at');
            $table->timestamps();
            $table->auditColumns();

            $table->unique(['progression_id', 'question_id']);
            $table->index(['progression_id', 'box']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leitner_progress_items');
    }
};
