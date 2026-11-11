<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Banques de questions du module Leitner (PRD Leitner §3, UC-01).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leitner_banks', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->timestamps();
            $table->auditColumns();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leitner_banks');
    }
};
