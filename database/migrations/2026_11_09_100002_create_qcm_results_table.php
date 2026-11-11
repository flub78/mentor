<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Résultats d'examen : ligne autonome écrite à la fin du passage, seule
 * source du certificat (PRD QCM UC-12, UC-19, plan QCM §6.3).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qcm_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attempt_id')->nullable()->unique()->constrained('qcm_attempts')->nullOnDelete();
            $table->string('certificate_number', 20)->unique();
            $table->json('identity');
            $table->string('last_name');
            $table->string('first_name');
            $table->string('email')->nullable()->index();
            $table->timestamp('taken_at');
            $table->string('exam_title');
            $table->string('questionnaire_title');
            $table->string('questionnaire_version')->nullable();
            $table->json('module_rates');
            $table->decimal('global_rate', 5, 2);
            $table->unsignedTinyInteger('pass_rate');
            $table->boolean('passed');
            $table->timestamps();
            $table->auditColumns();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qcm_results');
    }
};
