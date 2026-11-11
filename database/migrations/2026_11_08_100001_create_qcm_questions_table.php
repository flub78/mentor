<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Questions d'une banque (PRD QCM §3, UC-04).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qcm_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_id')->constrained('qcm_banks')->cascadeOnDelete();
            $table->text('statement');
            $table->string('module')->nullable();
            $table->text('explanation')->nullable();
            $table->string('image_path')->nullable();
            $table->timestamps();
            $table->auditColumns();

            $table->index(['bank_id', 'module']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qcm_questions');
    }
};
