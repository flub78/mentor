<?php

use App\Qcm\Migration\QcmToBanquesTransfer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lot B1 du plan Banques, étape B4 : bascule du module QCM sur les questions
 * du module Banques de questions.
 *
 * - les questions et banques QCM sont reprises (QcmToBanquesTransfer) ;
 * - les listes des questionnaires pointent vers les questions reprises ;
 * - le détail des passages terminés est supprimé (il ne sert qu'à la
 *   correction de fin de passage, qui n'est pas conservée) ; les réponses
 *   choisies sont désormais identifiées par une clé (`chosen_key`) ;
 * - les passages en cours ne sont pas convertis : la migration est refusée
 *   tant qu'il en existe.
 *
 * Les anciennes tables `qcm_banks`, `qcm_questions` et `qcm_answers` sont
 * supprimées à l'étape B5. Sauvegardez la base avant de lancer cette
 * migration : elle ne s'annule pas.
 */
return new class extends Migration
{
    public function up(): void
    {
        $inProgress = DB::table('qcm_attempts')->where('status', 'in_progress')->count();

        if ($inProgress > 0) {
            throw new RuntimeException("{$inProgress} passage(s) d'examen en cours : terminez-les avant de migrer, la bascule vers le module Banques ne les convertit pas.");
        }

        DB::table('qcm_attempt_items')->delete();

        Schema::table('qcm_questionnaire_questions', function (Blueprint $table) {
            $table->dropForeign(['question_id']);
        });

        Schema::table('qcm_attempt_items', function (Blueprint $table) {
            $table->dropForeign(['question_id']);
            $table->dropForeign(['chosen_answer_id']);
        });

        Schema::table('qcm_attempt_items', function (Blueprint $table) {
            $table->dropColumn('chosen_answer_id');
            $table->string('chosen_key', 40)->nullable()->after('answer_order');
        });

        $map = (new QcmToBanquesTransfer)->run();

        // Deux temps, pour qu'un nouvel identifiant ne soit jamais pris pour un ancien.
        foreach ($map as $old => $new) {
            DB::table('qcm_questionnaire_questions')->where('question_id', $old)->update(['question_id' => -$new]);
        }

        DB::table('qcm_questionnaire_questions')->where('question_id', '<', 0)->update(['question_id' => DB::raw('-question_id')]);

        Schema::table('qcm_questionnaire_questions', function (Blueprint $table) {
            $table->foreign('question_id')->references('id')->on('banques_questions')->cascadeOnDelete();
        });

        Schema::table('qcm_attempt_items', function (Blueprint $table) {
            $table->foreign('question_id')->references('id')->on('banques_questions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        throw new RuntimeException('La bascule du module QCM vers le module Banques ne s\'annule pas : restaurez la sauvegarde de la base faite avant la migration.');
    }
};
