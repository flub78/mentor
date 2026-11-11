<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Une question appartient à une banque ou, question propre, à un questionnaire
 * (PRD QCM §3) : exactement l'un des deux, contrôlé par le modèle Question.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('qcm_questions', function (Blueprint $table) {
            $table->foreignId('bank_id')->nullable()->change();
            $table->foreignId('questionnaire_id')->nullable()->after('bank_id')->constrained('qcm_questionnaires')->cascadeOnDelete();
        });
    }

    /**
     * Les questions propres disparaissent avec la colonne : sans banque, elles
     * n'auraient plus de place.
     */
    public function down(): void
    {
        DB::table('qcm_questions')->whereNull('bank_id')->delete();

        Schema::table('qcm_questions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('questionnaire_id');
            $table->foreignId('bank_id')->nullable(false)->change();
        });
    }
};
