<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lot B1 du plan Banques, étape B1 : les banques et questions du module
 * Leitner deviennent celles du module Banques de questions, partagé avec le
 * module QCM (PRD Banques §1). Les clés étrangères des tables Leitner suivent
 * le renommage. Ajoute la banque par défaut (PRD Banques §3).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('leitner_banks', 'banques_banks');
        Schema::rename('leitner_questions', 'banques_questions');

        Schema::table('banques_banks', function (Blueprint $table) {
            $table->boolean('is_default')->default(false)->after('description');
        });

        DB::table('banques_banks')->insert([
            'name' => $this->defaultBankName(),
            'description' => 'Questions créées sans banque choisie. Rangez-les ensuite dans une banque thématique.',
            'is_default' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('banques_banks')->where('is_default', true)->whereNotExists(
            fn ($query) => $query->from('banques_questions')->whereColumn('banques_questions.bank_id', 'banques_banks.id'),
        )->delete();

        Schema::table('banques_banks', function (Blueprint $table) {
            $table->dropColumn('is_default');
        });

        Schema::rename('banques_questions', 'leitner_questions');
        Schema::rename('banques_banks', 'leitner_banks');
    }

    /**
     * « Banque par défaut », suffixé si une banque porte déjà ce nom.
     */
    private function defaultBankName(): string
    {
        $name = 'Banque par défaut';

        for ($suffix = 2; DB::table('banques_banks')->where('name', $name)->exists(); $suffix++) {
            $name = "Banque par défaut ({$suffix})";
        }

        return $name;
    }
};
