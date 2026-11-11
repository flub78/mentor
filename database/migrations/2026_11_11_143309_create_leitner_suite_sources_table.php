<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Banques sources d'une suite (PRD Leitner UC-05).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leitner_suite_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('suite_id')->constrained('leitner_suites')->cascadeOnDelete();
            $table->foreignId('bank_id')->constrained('leitner_banks')->cascadeOnDelete();
            $table->timestamps();
            $table->auditColumns();

            $table->unique(['suite_id', 'bank_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leitner_suite_sources');
    }
};
