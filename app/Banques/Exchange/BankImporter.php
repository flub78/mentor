<?php

namespace App\Banques\Exchange;

use App\Banques\Models\Bank;
use App\Banques\Usage\UsageRegistry;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Import de questions dans une banque existante ou nouvelle (PRD Banques
 * UC-05) : analyse sans écriture, puis import des lignes valides dans une
 * transaction.
 */
final class BankImporter
{
    public function __construct(private readonly UsageRegistry $usages) {}

    public function analyse(ExchangeFile $file, ?Bank $target): ImportReport
    {
        $report = ImportReport::analyse(
            $file,
            $target === null ? [] : $target->questions()->pluck('statement')->all(),
            $target?->themeNames(),
            $target === null ? [] : $target->questions()->whereNotNull('reference')->get()->keyBy('reference')->all(),
        );

        $kind = $file->kind();

        if ($kind === ExchangeKind::Questionnaire || $kind === ExchangeKind::Exam) {
            $report->addNotice("Le fichier contient les paramètres d'un {$kind->value} : ils sont ignorés, seules les questions sont importées.");
        }

        return $report;
    }

    /**
     * Nom et description proposés pour une nouvelle banque.
     *
     * @return array{name: string, description: string|null}
     */
    public function suggestedBank(ExchangeFile $file): array
    {
        $parameters = $file->kind() === ExchangeKind::Bank ? $file->parameters() : [];
        $name = $parameters['nom'] ?? null;
        $description = $parameters['description'] ?? null;

        return [
            'name' => is_string($name) && trim($name) !== '' ? trim($name) : $file->baseName(),
            'description' => is_string($description) ? $description : null,
        ];
    }

    /**
     * @param  Bank|null  $target  banque cible ; null pour créer la banque `$newBank`
     * @param  array{name?: string|null, description?: string|null}  $newBank
     * @param  bool  $updateExisting  mettre à jour les questions déjà présentes (reconnues par leur référence) ; sinon ces lignes sont ignorées
     * @return array{bank: Bank, imported: int, skipped: int, updated: int, unchanged: int, ignored: int, report: string}
     */
    public function import(ExchangeFile $file, ?Bank $target, array $newBank, bool $skipDuplicates, bool $updateExisting = true): array
    {
        $report = $this->analyse($file, $target);
        $questions = $report->questionsToImport($skipDuplicates);
        $existing = $report->existingQuestions();
        $persister = new QuestionPersister;
        $updated = 0;
        $effects = [];

        try {
            $bank = DB::transaction(function () use ($target, $newBank, $questions, $persister, $file, $existing, $updateExisting, &$updated, &$effects): Bank {
                if ($updateExisting) {
                    foreach ($existing as $entry) {
                        if ($entry['difference'] === 'unchanged') {
                            continue;
                        }

                        $persister->update($entry['question'], $entry['row'], $file);
                        $updated++;

                        if ($entry['difference'] === 'answer_changed') {
                            $effect = $this->usages->applySubstantiveChange($entry['question']);

                            if ($effect !== '') {
                                $effects[$effect] = ($effects[$effect] ?? 0) + 1;
                            }
                        }
                    }
                }

                $bank = $target ?? Bank::query()->create([
                    'name' => $newBank['name'] ?? $file->baseName(),
                    'description' => $newBank['description'] ?? null,
                ]);

                foreach ($questions as $question) {
                    $persister->create($question, $bank->id, $file);
                }

                return $bank;
            });
        } catch (Throwable $exception) {
            $persister->discardStoredImages();

            throw $exception;
        }

        $unchanged = count(array_filter($existing, fn (array $entry): bool => $entry['difference'] === 'unchanged'));
        $lines = [];

        foreach ($effects as $effect => $count) {
            $lines[] = $count === 1 ? $effect : "{$effect} (× {$count})";
        }

        return [
            'bank' => $bank,
            'imported' => count($questions),
            'skipped' => $report->validCount() - count($existing) - count($questions),
            'updated' => $updated,
            'unchanged' => $updateExisting ? $unchanged : 0,
            'ignored' => $updateExisting ? 0 : count($existing),
            'report' => implode(' ', $lines),
        ];
    }
}
