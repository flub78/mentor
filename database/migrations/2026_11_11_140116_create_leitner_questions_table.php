<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Questions d'une banque Leitner (PRD Leitner §3, §6, UC-02). La réponse
 * attendue, propre au type, est portée par la colonne JSON `payload`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leitner_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_id')->constrained('leitner_banks')->cascadeOnDelete();
            $table->string('type', 40);
            $table->text('statement');
            $table->json('payload');
            $table->text('explanation')->nullable();
            $table->unsignedTinyInteger('difficulty')->nullable();
            $table->string('theme')->nullable();
            $table->string('image_path')->nullable();
            $table->string('image_alt')->nullable();
            $table->timestamps();
            $table->auditColumns();

            $table->index(['bank_id', 'type']);
            $table->index(['bank_id', 'difficulty']);
            $table->index(['bank_id', 'theme']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leitner_questions');
    }
};
