<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Séances d'une progression (PRD Leitner UC-08, §7). Le numéro est attribué
 * à la première réponse ; une séance sans réponse n'est pas comptée.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leitner_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('progression_id')->constrained('leitner_progressions')->cascadeOnDelete();
            $table->string('kind', 20)->default('seance');
            $table->unsignedInteger('number')->nullable();
            $table->string('status', 20);
            $table->string('presentation_order', 40);
            $table->boolean('undo_available')->default(false);
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->auditColumns();

            $table->index(['progression_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leitner_sessions');
    }
};
