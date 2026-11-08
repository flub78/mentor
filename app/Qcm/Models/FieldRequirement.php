<?php

namespace App\Qcm\Models;

/**
 * Exigence sur un renseignement d'identification (PRD QCM UC-20, étape 4).
 *
 * Les valeurs sont celles du fichier d'échange (`examen.json`).
 */
enum FieldRequirement: string
{
    case NotAsked = 'non_demande';
    case Optional = 'facultatif';
    case Required = 'obligatoire';

    public function label(): string
    {
        return match ($this) {
            self::NotAsked => 'Non demandé',
            self::Optional => 'Facultatif',
            self::Required => 'Obligatoire',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_combine(
            array_map(fn (self $case): string => $case->value, self::cases()),
            array_map(fn (self $case): string => $case->label(), self::cases()),
        );
    }
}
