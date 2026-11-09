<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Organisation et identifiant libre du candidat en colonnes, pour la
 * recherche dans la liste des résultats (PRD QCM UC-09).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('qcm_results', function (Blueprint $table) {
            $table->string('organisation')->nullable()->after('email')->index();
            $table->string('identifier')->nullable()->after('organisation')->index();
        });
    }

    public function down(): void
    {
        Schema::table('qcm_results', function (Blueprint $table) {
            $table->dropIndex(['organisation']);
            $table->dropIndex(['identifier']);
            $table->dropColumn(['organisation', 'identifier']);
        });
    }
};
