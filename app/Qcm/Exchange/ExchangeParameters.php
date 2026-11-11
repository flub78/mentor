<?php

namespace App\Qcm\Exchange;

use App\Banques\Exchange\ExchangeFile;
use App\Banques\Exchange\ExchangeKind;
use App\Qcm\Models\Exam;
use App\Qcm\Models\FieldRequirement;
use App\Qcm\Models\IdentificationField;
use App\Qcm\Models\Questionnaire;

/**
 * Paramètres d'un questionnaire et d'un examen dans le fichier de paramètres
 * (`questionnaire.json`, `examen.json`, PRD QCM §6.1) : écriture à l'export,
 * lecture tolérante à l'import (valeur invalide remplacée par la valeur par
 * défaut, avec un avertissement, PRD QCM UC-17 2b).
 *
 * Les clés sont en français : le fichier peut être écrit à la main.
 */
final class ExchangeParameters
{
    /** @var list<string> */
    private array $warnings = [];

    /**
     * @return array{titre: string, description: string|null, version: string|null}
     */
    public static function fromQuestionnaire(Questionnaire $questionnaire): array
    {
        return [
            'titre' => $questionnaire->title,
            'description' => $questionnaire->description,
            'version' => $questionnaire->version,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function fromExam(Exam $exam): array
    {
        $identification = [];

        foreach (IdentificationField::cases() as $field) {
            $identification[$field->value] = $exam->requirement($field)->value;
        }

        return [
            'titre' => $exam->title,
            'consignes' => $exam->instructions,
            'duree_minutes' => $exam->duration_minutes,
            'taux_reussite' => $exam->pass_rate,
            'melange_questions' => $exam->shuffle_questions,
            'correction_detaillee' => $exam->shows_correction,
            'passages_max' => $exam->max_attempts,
            'identification' => $identification,
            'libelle_identifiant' => $exam->identifier_label,
        ];
    }

    /**
     * Attributs du questionnaire lus dans le fichier ; titre par défaut : nom
     * de la banque, ou du fichier.
     *
     * @return array{title: string, description: string|null, version: string|null}
     */
    public function questionnaire(ExchangeFile $file): array
    {
        $parameters = match ($file->kind()) {
            ExchangeKind::Questionnaire => $file->parameters(),
            ExchangeKind::Exam => (array) ($file->parameters()['questionnaire'] ?? []),
            ExchangeKind::Bank => ['titre' => $file->parameters()['nom'] ?? null, 'description' => $file->parameters()['description'] ?? null],
            null => [],
        };

        return [
            'title' => $this->text($parameters, 'titre', 255) ?? $file->baseName(),
            'description' => $this->text($parameters, 'description'),
            'version' => $this->text($parameters, 'version', 50),
        ];
    }

    /**
     * Attributs de l'examen lus dans `examen.json`, ou null si le fichier n'en
     * décrit pas.
     *
     * @return array<string, mixed>|null
     */
    public function exam(ExchangeFile $file, string $defaultTitle): ?array
    {
        if ($file->kind() !== ExchangeKind::Exam) {
            return null;
        }

        $exam = $file->parameters()['examen'] ?? null;

        if (! is_array($exam)) {
            $this->warnings[] = 'Le fichier examen.json ne contient pas d\'objet "examen" : l\'examen est créé avec les paramètres par défaut.';
            $exam = [];
        }

        $defaults = (array) config('qcm.exam_defaults');
        $identification = $this->identification($exam['identification'] ?? []);
        $label = $this->text($exam, 'libelle_identifiant', 255);

        if ($identification[IdentificationField::Identifier->value] !== FieldRequirement::NotAsked->value && $label === null) {
            $this->warnings[] = 'L\'identifiant libre est demandé sans "libelle_identifiant" : libellé « Identifiant » retenu.';
            $label = 'Identifiant';
        }

        return [
            'title' => $this->text($exam, 'titre', 255) ?? $defaultTitle,
            'instructions' => $this->text($exam, 'consignes'),
            'duration_minutes' => $this->integer($exam, 'duree_minutes', 1, 65535, null, 'durée laissée vide (pas de chronomètre)'),
            'pass_rate' => $this->integer($exam, 'taux_reussite', 0, 100, (int) $defaults['pass_rate'], "taux par défaut de {$defaults['pass_rate']} % retenu") ?? (int) $defaults['pass_rate'],
            'shuffle_questions' => $this->boolean($exam, 'melange_questions', (bool) $defaults['shuffle_questions']),
            'shows_correction' => $this->boolean($exam, 'correction_detaillee', (bool) $defaults['shows_correction']),
            'max_attempts' => $this->integer($exam, 'passages_max', 1, 65535, null, 'passages non limités'),
            'identification_fields' => $identification,
            'identifier_label' => $identification[IdentificationField::Identifier->value] === FieldRequirement::NotAsked->value ? null : $label,
        ];
    }

    /**
     * @return list<string>
     */
    public function warnings(): array
    {
        return $this->warnings;
    }

    /**
     * @return array<string, string>
     */
    private function identification(mixed $value): array
    {
        $fields = Exam::defaultIdentificationFields();

        if (! is_array($value)) {
            $this->warnings[] = '"identification" doit être un objet : seuls le nom et le prénom seront demandés.';

            return $fields;
        }

        foreach ($value as $field => $requirement) {
            if (! array_key_exists((string) $field, $fields)) {
                $this->warnings[] = "Renseignement d'identification inconnu « {$field} » ignoré (admis : ".implode(', ', array_keys($fields)).').';

                continue;
            }

            if (FieldRequirement::tryFrom(is_string($requirement) ? $requirement : '') === null) {
                $this->warnings[] = "Exigence invalide pour « {$field} » : non demandé (valeurs admises : ".implode(', ', array_keys(FieldRequirement::options())).').';

                continue;
            }

            $fields[(string) $field] = (string) $requirement;
        }

        return $fields;
    }

    /**
     * @param  array<mixed>  $parameters
     */
    private function text(array $parameters, string $key, ?int $maxLength = null): ?string
    {
        $value = $parameters[$key] ?? null;

        if ($value === null) {
            return null;
        }

        if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
            $this->warnings[] = "\"{$key}\" doit être un texte : valeur ignorée.";

            return null;
        }

        $value = trim((string) $value);

        if ($maxLength !== null && mb_strlen($value) > $maxLength) {
            $this->warnings[] = "\"{$key}\" dépasse {$maxLength} caractères : texte tronqué.";
            $value = mb_substr($value, 0, $maxLength);
        }

        return $value === '' ? null : $value;
    }

    /**
     * @param  array<mixed>  $parameters
     */
    private function integer(array $parameters, string $key, int $min, int $max, ?int $default, string $fallback): ?int
    {
        $value = $parameters[$key] ?? null;

        if ($value === null) {
            return $default;
        }

        if (! is_int($value) || $value < $min || $value > $max) {
            $this->warnings[] = "\"{$key}\" doit être un nombre entier de {$min} à {$max} (lu : ".json_encode($value, JSON_UNESCAPED_UNICODE)."), {$fallback}.";

            return $default;
        }

        return $value;
    }

    /**
     * @param  array<mixed>  $parameters
     */
    private function boolean(array $parameters, string $key, bool $default): bool
    {
        $value = $parameters[$key] ?? null;

        if ($value === null) {
            return $default;
        }

        if (! is_bool($value)) {
            $this->warnings[] = "\"{$key}\" doit valoir true ou false : valeur par défaut retenue (".($default ? 'true' : 'false').').';

            return $default;
        }

        return $value;
    }
}
