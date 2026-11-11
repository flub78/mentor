<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Lot B1 du plan Banques, étape B5 : suppression des anciennes tables du
 * module QCM, dont le contenu a été repris dans le module Banques de
 * questions par la migration de bascule (`switch_qcm_to_banques_questions`).
 * Plus aucun code ne les lit. Comme la bascule, elle ne s'annule pas :
 * restaurez la sauvegarde faite avant la migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('qcm_answers');
        Schema::dropIfExists('qcm_questions');
        Schema::dropIfExists('qcm_banks');
    }

    public function down(): void
    {
        throw new RuntimeException('La suppression des anciennes tables QCM ne s\'annule pas : restaurez la sauvegarde de la base faite avant la migration.');
    }
};
