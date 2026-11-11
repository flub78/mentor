<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Questionnaires : listes ordonnées de questions (PRD QCM §3, UC-08).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qcm_questionnaires', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('version', 50)->nullable();
            $table->timestamps();
            $table->auditColumns();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qcm_questionnaires');
    }
};
