<?php

namespace App\Qcm\Exams;

use App\Qcm\Models\Attempt;
use App\Qcm\Models\AttemptStatus;
use App\Qcm\Models\Exam;
use App\Qcm\Models\FieldRequirement;
use App\Qcm\Models\IdentificationField;
use App\Qcm\Models\Question;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Random\Randomizer;

/**
 * Démarrage d'un passage d'examen (PRD QCM UC-12, étapes 2 et 3) :
 * identification du candidat, contrôle du nombre de passages, composition
 * et échéance.
 */
final class AttemptStarter
{
    /** Clé du nom dans l'identité, toujours demandé. */
    public const LAST_NAME = 'nom';

    /** Clé du prénom dans l'identité, toujours demandé. */
    public const FIRST_NAME = 'prenom';

    private const MESSAGES = [
        'required' => 'Le champ « :attribute » est obligatoire.',
        'string' => 'Le champ « :attribute » doit être un texte.',
        'max' => 'Le champ « :attribute » ne doit pas dépasser :max caractères.',
        'email' => 'Le champ « :attribute » doit être une adresse e-mail valide, par exemple prenom.nom@exemple.fr.',
        'date_format' => 'Le champ « :attribute » doit être une date valide au format AAAA-MM-JJ.',
        'before' => 'Le champ « :attribute » doit être une date passée.',
    ];

    public function __construct(private readonly Randomizer $randomizer = new Randomizer) {}

    /**
     * Valide l'identité et crée le passage, questions comprises.
     *
     * @param  array<string, mixed>  $identity  saisie du candidat ; les champs non demandés par l'examen sont ignorés
     *
     * @throws ValidationException champ obligatoire vide ou mal formé ; le message nomme le champ
     * @throws AttemptException questionnaire vide ou nombre maximal de passages atteint
     */
    public function start(Exam $exam, array $identity): Attempt
    {
        $identity = $this->validateIdentity($exam, $identity);
        $items = $this->compose($exam);
        $this->ensureAttemptsLeft($exam, $identity);
        $startedAt = now();

        return DB::transaction(function () use ($exam, $identity, $items, $startedAt): Attempt {
            $attempt = Attempt::create([
                'exam_id' => $exam->id,
                'status' => AttemptStatus::InProgress,
                'identity' => $identity,
                'started_at' => $startedAt,
                'deadline_at' => $exam->duration_minutes ? $startedAt->copy()->addMinutes($exam->duration_minutes) : null,
            ]);

            foreach ($items as $position => $item) {
                $attempt->items()->create(['position' => $position, ...$item]);
            }

            return $attempt;
        });
    }

    /**
     * Questions posées : toutes celles du questionnaire, dans l'ordre ou
     * mélangées selon l'examen, avec leurs réponses toujours mélangées.
     * Rien n'est enregistré (la prévisualisation s'en sert aussi).
     *
     * @return list<array{question_id: int, answer_order: list<int>}>
     *
     * @throws AttemptException questionnaire vide
     */
    public function compose(Exam $exam): array
    {
        $questions = $exam->questionnaire->questions()->with('answers')->get()->all();

        if ($questions === []) {
            throw AttemptException::emptyQuestionnaire($exam);
        }

        if ($exam->shuffle_questions) {
            $questions = $this->randomizer->shuffleArray($questions);
        }

        return array_map(fn (Question $question): array => [
            'question_id' => $question->id,
            'answer_order' => array_map(intval(...), $this->randomizer->shuffleArray($question->answers->modelKeys())),
        ], array_values($questions));
    }

    /**
     * Libellés des champs d'identification demandés par l'examen, dans
     * l'ordre de saisie, nom et prénom en tête.
     *
     * @return array<string, string> clé d'identité => libellé
     */
    public static function fieldLabels(Exam $exam): array
    {
        $labels = [self::LAST_NAME => 'Nom', self::FIRST_NAME => 'Prénom'];

        foreach (IdentificationField::cases() as $field) {
            if ($exam->requirement($field) !== FieldRequirement::NotAsked) {
                $labels[$field->value] = $field === IdentificationField::Identifier && filled($exam->identifier_label)
                    ? (string) $exam->identifier_label
                    : $field->label();
            }
        }

        return $labels;
    }

    /**
     * Identité présentée avec ses libellés, dans l'ordre de saisie. Sans
     * examen (supprimé depuis), les libellés par défaut sont utilisés.
     *
     * @param  array<string, string>  $identity
     * @return list<array{key: string, label: string, value: string}>
     */
    public static function identityLines(?Exam $exam, array $identity): array
    {
        $labels = $exam !== null ? self::fieldLabels($exam) : [self::LAST_NAME => 'Nom', self::FIRST_NAME => 'Prénom'];
        $keys = array_unique([...array_keys($labels), ...array_keys($identity)]);
        $lines = [];

        foreach ($keys as $key) {
            if (isset($identity[$key]) && $identity[$key] !== '') {
                $lines[] = [
                    'key' => $key,
                    'label' => $labels[$key] ?? IdentificationField::tryFrom($key)?->label() ?? $key,
                    'value' => $identity[$key],
                ];
            }
        }

        return $lines;
    }

    /**
     * Valide l'identité saisie selon les exigences de l'examen, sans rien
     * enregistrer.
     *
     * @param  array<string, mixed>  $identity
     * @return array<string, string> identité retenue : champs demandés et renseignés, espaces de bord retirés
     *
     * @throws ValidationException
     */
    public function validateIdentity(Exam $exam, array $identity): array
    {
        $labels = self::fieldLabels($exam);
        $values = [];

        foreach (array_keys($labels) as $key) {
            $value = $identity[$key] ?? null;
            $values[$key] = is_string($value) ? trim($value) : $value;
        }

        $rules = [
            self::LAST_NAME => ['required', 'string', 'max:255'],
            self::FIRST_NAME => ['required', 'string', 'max:255'],
        ];

        foreach (IdentificationField::cases() as $field) {
            $requirement = $exam->requirement($field);

            if ($requirement === FieldRequirement::NotAsked) {
                continue;
            }

            $rules[$field->value] = [
                $requirement === FieldRequirement::Required ? 'required' : 'nullable',
                'string',
                ...match ($field) {
                    IdentificationField::Email => ['email', 'max:255'],
                    IdentificationField::BirthDate => ['date_format:Y-m-d', 'before:today'],
                    default => ['max:255'],
                },
            ];
        }

        $validated = Validator::make($values, $rules, self::MESSAGES, $labels)->validate();

        /** @var array<string, string> */
        return array_filter($validated, fn (mixed $value): bool => is_string($value) && $value !== '');
    }

    /**
     * Le candidat est reconnu à son adresse e-mail si l'examen la demande et
     * qu'elle est saisie, sinon à ses nom et prénom, sans tenir compte des
     * majuscules (PRD QCM UC-20 étape 4, UC-18 3b).
     *
     * @param  array<string, string>  $identity
     *
     * @throws AttemptException
     */
    private function ensureAttemptsLeft(Exam $exam, array $identity): void
    {
        if ($exam->max_attempts === null) {
            return;
        }

        $signature = self::signature($identity);
        $count = $exam->attempts()
            ->get(['id', 'identity'])
            ->filter(fn (Attempt $attempt): bool => self::signature($attempt->identity) === $signature)
            ->count();

        if ($count >= $exam->max_attempts) {
            throw AttemptException::maxAttemptsReached($exam, $count);
        }
    }

    /**
     * @param  array<string, string>  $identity
     */
    private static function signature(array $identity): string
    {
        $email = $identity[IdentificationField::Email->value] ?? '';

        if ($email !== '') {
            return 'email:'.mb_strtolower($email);
        }

        return 'nom:'.mb_strtolower(($identity[self::LAST_NAME] ?? '').'|'.($identity[self::FIRST_NAME] ?? ''));
    }
}
