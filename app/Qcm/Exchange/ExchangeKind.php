<?php

namespace App\Qcm\Exchange;

/**
 * Nature d'un fichier d'échange, donnée par son fichier de paramètres (PRD QCM §6.1).
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

    public function label(): string
    {
        return match ($this) {
            self::Bank => 'banque',
            self::Questionnaire => 'questionnaire',
            self::Exam => 'examen',
        };
    }
}
