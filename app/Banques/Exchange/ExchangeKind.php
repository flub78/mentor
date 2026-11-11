<?php

namespace App\Banques\Exchange;

/**
 * Nature d'un fichier d'échange, donnée par son fichier de paramètres
 * (PRD Banques §8.4). Les fichiers de questionnaire et d'examen produits par
 * le module QCM s'importent aussi dans une banque : seules leurs questions
 * sont reprises.
 */
enum ExchangeKind: string
{
    case Bank = 'banque';
    case Questionnaire = 'questionnaire';
    case Exam = 'examen';

    /**
     * Nom du fichier de paramètres dans l'archive.
     */
    public function parametersFile(): string
    {
        return $this->value.'.json';
    }
}
