<?php

namespace App\Qcm\Models;

/**
 * Renseignement facultatif demandé au candidat avant un examen, en plus du
 * nom et du prénom, toujours demandés (PRD QCM UC-20, étape 4).
 *
 * Les valeurs sont celles du fichier d'échange (`examen.json`).
 */
enum IdentificationField: string
{
    case Email = 'email';
    case BirthDate = 'date_naissance';
    case Organisation = 'organisation';
    case Identifier = 'identifiant';

    public function label(): string
    {
        return match ($this) {
            self::Email => 'Adresse e-mail',
            self::BirthDate => 'Date de naissance',
            self::Organisation => 'Organisation (club, entreprise)',
            self::Identifier => 'Identifiant libre',
        };
    }
}
