<?php

namespace App\Qcm\Questionnaires;

use App\Qcm\Models\Questionnaire;
use RuntimeException;

/**
 * Suppression refusée d'un questionnaire utilisé par des examens
 * (PRD QCM UC-08, 3b).
 */
final class QuestionnaireInUseException extends RuntimeException
{
    /**
     * @param  list<string>  $examTitles
     */
    public static function usedBy(Questionnaire $questionnaire, array $examTitles): self
    {
        return new self(sprintf(
            'Le questionnaire « %s » est utilisé par %s : %s. Supprimez ces examens ou faites-leur utiliser un autre questionnaire, puis recommencez.',
            $questionnaire->title,
            count($examTitles) > 1 ? 'les examens' : 'l\'examen',
            '« '.implode(' », « ', $examTitles).' »',
        ));
    }
}
