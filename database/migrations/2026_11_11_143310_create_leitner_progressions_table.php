<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Progression d'un étudiant dans une suite (PRD Leitner §3). Sans connexion,
 * une seule progression par suite, sans utilisateur.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leitner_progressions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('suite_id')->constrained('leitner_suites')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('last_session_number')->default(0);
            $table->timestamps();
            $table->auditColumns();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leitner_progressions');
    }
};
