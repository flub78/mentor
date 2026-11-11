<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Référence facultative d'une question, unique dans sa banque (PRD Banques
 * §3, UC-05) : l'identifiant stable que l'auteur donne à la question pour la
 * retrouver d'un fichier d'échange à l'autre.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('banques_questions', function (Blueprint $table): void {
            $table->string('reference', 100)->nullable()->after('bank_id');
            $table->unique(['bank_id', 'reference']);
        });
    }

    public function down(): void
    {
        Schema::table('banques_questions', function (Blueprint $table): void {
            $table->dropUnique(['bank_id', 'reference']);
            $table->dropColumn('reference');
        });
    }
};
