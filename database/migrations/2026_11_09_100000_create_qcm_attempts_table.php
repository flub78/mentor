<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Passages d'examen : état d'une tentative de candidat pendant l'examen
 * (PRD QCM UC-12, plan QCM §6.3).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qcm_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->nullable()->constrained('qcm_exams')->nullOnDelete();
            $table->string('status', 20)->index();
            $table->json('identity');
            $table->timestamp('started_at');
            $table->timestamp('deadline_at')->nullable()->index();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->auditColumns();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qcm_attempts');
    }
};
