<?php

namespace App\Qcm\Exams;

use App\Qcm\Models\Attempt;
use App\Qcm\Models\AttemptItem;
use App\Qcm\Models\AttemptStatus;
use App\Qcm\Models\Exam;
use App\Qcm\Models\IdentificationField;
use App\Qcm\Models\Result;
use App\Qcm\Study\ModuleCatalog;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Random\Randomizer;

/**
 * Fin d'un passage d'examen et calcul du résultat (PRD QCM UC-12 étape 9,
 * UC-20 règles).
 *
 * Une question non répondue compte comme fausse ; une question supprimée
 * pendant le passage aussi, rangée dans « Sans module » faute de module
 * connu.
 */
final class AttemptFinisher
{
    /** Alphabet base 32 de Crockford : ni I, ni L, ni O, ni U, pour éviter les confusions. */
    private const CERTIFICATE_ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    private const CERTIFICATE_SUFFIX_LENGTH = 6;

    public function __construct(private readonly Randomizer $randomizer = new Randomizer) {}

    /**
     * Termine le passage et enregistre son résultat. Sans effet sur un
     * passage déjà terminé, dont le résultat est rendu tel quel.
     *
     * @param  AttemptStatus  $status  Finished (le candidat termine) ou Expired (échéance atteinte)
     */
    public function finish(Attempt $attempt, AttemptStatus $status = AttemptStatus::Finished): Result
    {
        return DB::transaction(function () use ($attempt, $status): Result {
            $attempt->refresh();

            if (! $attempt->isInProgress()) {
                return $attempt->result()->firstOrFail();
            }

            $attempt->update(['status' => $status, 'finished_at' => now()]);

            return $this->writeResult($attempt);
        });
    }

    /**
     * Clôture paresseuse : termine le passage s'il a dépassé son échéance.
     *
     * @return bool vrai si le passage vient d'être terminé
     */
    public function closeIfOverdue(Attempt $attempt): bool
    {
        if (! $attempt->isOverdue()) {
            return false;
        }

        $this->finish($attempt, AttemptStatus::Expired);

        return true;
    }

    /**
     * Termine tous les passages en cours dont l'échéance est atteinte.
     *
     * @return int nombre de passages terminés
     */
    public function closeOverdue(): int
    {
        $closed = 0;

        Attempt::query()
            ->where('status', AttemptStatus::InProgress)
            ->whereNotNull('deadline_at')
            ->where('deadline_at', '<=', now())
            ->each(function (Attempt $attempt) use (&$closed): void {
                $closed += (int) $this->closeIfOverdue($attempt);
            });

        return $closed;
    }

    private function writeResult(Attempt $attempt): Result
    {
        $attempt->load(['items.question', 'exam.questionnaire']);

        return $attempt->result()->create([
            ...$this->resultAttributes($attempt->items, $attempt->exam, $attempt->identity, $attempt->started_at),
            'certificate_number' => $this->certificateNumber($attempt),
        ]);
    }

    /**
     * Contenu du résultat, sans numéro de certificat ni enregistrement (la
     * prévisualisation s'en sert aussi). Les lignes du passage doivent porter
     * leur relation `question`.
     *
     * @param  iterable<AttemptItem>  $items
     * @param  array<string, string>  $identity
     * @return array<string, mixed>
     */
    public function resultAttributes(iterable $items, ?Exam $exam, array $identity, CarbonInterface $takenAt): array
    {
        /** @var array<string, array{correct: int, total: int, spellings: array<string, int>}> $modules */
        $modules = [];
        $correct = 0;
        $total = 0;

        foreach ($items as $item) {
            $module = $item->question?->module;
            $key = ModuleCatalog::key($module);
            $isCorrect = $this->isCorrect($item);

            $modules[$key] ??= ['correct' => 0, 'total' => 0, 'spellings' => []];
            $modules[$key]['total']++;
            $modules[$key]['correct'] += (int) $isCorrect;
            $spelling = $module ?? ModuleCatalog::NO_MODULE_LABEL;
            $modules[$key]['spellings'][$spelling] = ($modules[$key]['spellings'][$spelling] ?? 0) + 1;
            $correct += (int) $isCorrect;
            $total++;
        }

        $moduleRates = [];

        foreach ($modules as $key => $module) {
            $moduleRates[] = [
                'key' => (string) $key,
                'label' => ModuleCatalog::preferredLabel($module['spellings']),
                'correct' => $module['correct'],
                'total' => $module['total'],
                'rate' => self::rate($module['correct'], $module['total']),
            ];
        }

        usort($moduleRates, fn (array $a, array $b): int => [$a['key'] === ModuleCatalog::NO_MODULE_KEY, mb_strtolower($a['label'])]
            <=> [$b['key'] === ModuleCatalog::NO_MODULE_KEY, mb_strtolower($b['label'])]);

        $passRate = $exam === null ? 0 : $exam->pass_rate;

        return [
            'identity' => AttemptStarter::identityLines($exam, $identity),
            'last_name' => $identity[AttemptStarter::LAST_NAME] ?? '',
            'first_name' => $identity[AttemptStarter::FIRST_NAME] ?? '',
            'email' => $identity[IdentificationField::Email->value] ?? null,
            'organisation' => $identity[IdentificationField::Organisation->value] ?? null,
            'identifier' => $identity[IdentificationField::Identifier->value] ?? null,
            'taken_at' => $takenAt,
            'exam_title' => $exam === null ? '(examen supprimé)' : $exam->title,
            'questionnaire_title' => $exam === null ? '(questionnaire supprimé)' : $exam->questionnaire->title,
            'questionnaire_version' => $exam?->questionnaire->version,
            'module_rates' => array_map(fn (array $rate): array => array_diff_key($rate, ['key' => true]), $moduleRates),
            'global_rate' => self::rate($correct, $total),
            'pass_rate' => $passRate,
            // Comparaison exacte, sans arrondi : correct / total >= taux / 100.
            'passed' => $total > 0 && $correct * 100 >= $passRate * $total,
        ];
    }

    private function isCorrect(AttemptItem $item): bool
    {
        return $item->question !== null && $item->chosenAnswer()?->is_correct === true;
    }

    private static function rate(int $correct, int $total): float
    {
        return $total === 0 ? 0.0 : round($correct * 100 / $total, 2);
    }

    /**
     * Numéro unique `AAAAMMJJ-XXXXXX` : date du passage et six caractères
     * tirés au hasard (plan QCM §6.2).
     */
    private function certificateNumber(Attempt $attempt): string
    {
        do {
            $number = $attempt->started_at->format('Ymd').'-'
                .$this->randomizer->getBytesFromString(self::CERTIFICATE_ALPHABET, self::CERTIFICATE_SUFFIX_LENGTH);
        } while (Result::query()->where('certificate_number', $number)->exists());

        return $number;
    }
}
