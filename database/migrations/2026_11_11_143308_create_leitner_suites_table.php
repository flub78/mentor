<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Suites de révision Leitner et leurs paramètres (PRD Leitner §3, UC-05).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leitner_suites', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('box_count');
            $table->unsignedSmallInteger('min_box_one');
            $table->string('presentation_order', 40);
            $table->boolean('retry_errors');
            $table->foreignId('personal_bank_id')->nullable()->constrained('leitner_banks')->nullOnDelete();
            $table->timestamps();
            $table->auditColumns();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leitner_suites');
    }
};
