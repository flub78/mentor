<?php

namespace App\Qcm\Exchange;

use App\Qcm\Models\Bank;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Import de questions dans une banque existante ou nouvelle (PRD QCM UC-07).
 */
final class BankImporter
{
    public function analyse(ExchangeFile $file, ?Bank $target): ImportReport
    {
        $report = ImportReport::analyse(
            $file,
            $target === null ? [] : $target->questions()->pluck('statement')->all(),
            $target?->moduleNames(),
        );

        $kind = $file->kind();

        if ($kind === ExchangeKind::Questionnaire || $kind === ExchangeKind::Exam) {
            $report->addNotice("Le fichier contient les paramètres d'un {$kind->label()} : ils sont ignorés, seules les questions sont importées.");
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
     * @param  array{name?: string, description?: string|null}  $newBank
     * @return array{bank: Bank, imported: int, skipped: int}
     */
    public function import(ExchangeFile $file, ?Bank $target, array $newBank, bool $skipDuplicates): array
    {
        $report = $this->analyse($file, $target);
        $questions = $report->questionsToImport($skipDuplicates);
        $persister = new QuestionPersister;

        try {
            $bank = DB::transaction(function () use ($target, $newBank, $questions, $persister, $file): Bank {
                $bank = $target ?? Bank::create([
                    'name' => $newBank['name'] ?? $file->baseName(),
                    'description' => $newBank['description'] ?? null,
                ]);

                foreach ($questions as $question) {
                    $persister->create($question, ['bank_id' => $bank->id], $file);
                }

                return $bank;
            });
        } catch (Throwable $exception) {
            $persister->discardStoredImages();

            throw $exception;
        }

        return ['bank' => $bank, 'imported' => count($questions), 'skipped' => $report->validCount() - count($questions)];
    }
}
