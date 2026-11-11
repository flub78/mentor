<?php

namespace App\Banques\Usage;

use App\Banques\Models\Bank;
use App\Banques\Models\Question;

/**
 * Utilisation des questions par un module (QCM, Leitner), déclarée au module
 * Banques sans qu'il dépende de ce module (PRD Banques §3, UC-01, UC-02) :
 * phrases des confirmations et réaction à une modification de fond.
 * Une implémentation s'enregistre avec le tag `UsageRegistry::TAG`.
 */
interface QuestionUsage
{
    /**
     * Phrase ajoutée à la confirmation de suppression d'une banque, ou null
     * si le module n'utilise pas la banque.
     */
    public function bankDeletionNotice(Bank $bank): ?string;

    /**
     * Phrase ajoutée à la confirmation de suppression d'une question, ou null.
     */
    public function questionDeletionNotice(Question $question): ?string;

    /**
     * Phrase qui dit où une modification de fond aurait un effet, ou null si
     * elle n'en aurait aucun dans ce module.
     */
    public function substantiveChangeNotice(Question $question): ?string;

    /**
     * Applique une modification de fond ; renvoie la phrase qui en rend
     * compte, ou null si rien n'a changé.
     */
    public function applySubstantiveChange(Question $question): ?string;
}
