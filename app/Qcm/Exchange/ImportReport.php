<?php

namespace App\Qcm\Exchange;

use App\Qcm\Study\ModuleCatalog;

/**
 * Rapport d'analyse d'un fichier avant import (PRD QCM UC-07, UC-17) :
 * rien n'est écrit tant que l'utilisateur n'a pas confirmé.
 */
final class ImportReport
{
    /** @var list<array{line: int, statement: string, reason: string}> */
    private array $duplicates = [];

    /** @var array<int, true> lignes des doublons */
    private array $duplicateLines = [];

    /** @var list<array{line: int, image: string, problem: string}> */
    private array $imageProblems = [];

    /** @var list<string> */
    private array $newModules = [];

    /** @var list<string> */
    private array $notices = [];

    private function __construct(private readonly ExchangeFile $file) {}

    /**
     * @param  list<string>  $existingStatements  énoncés déjà présents dans la cible
     * @param  list<string>|null  $existingModules  modules de la cible ; null = cible nouvelle, pas de liste
     */
    public static function analyse(ExchangeFile $file, array $existingStatements = [], ?array $existingModules = null): self
    {
        $report = new self($file);
        $existing = array_fill_keys(array_map(QuestionData::normalizeStatement(...), $existingStatements), true);
        $seen = [];
        $knownModules = array_fill_keys(array_map(ModuleCatalog::key(...), $existingModules ?? []), true);
        $newModules = [];

        foreach ($file->csv()->questions as $question) {
            $key = QuestionData::normalizeStatement($question->statement);

            if (isset($seen[$key])) {
                $report->addDuplicate($question, "même énoncé que la ligne {$seen[$key]} du fichier");
            } elseif (isset($existing[$key])) {
                $report->addDuplicate($question, 'même énoncé qu\'une question déjà présente');
            }

            $seen[$key] ??= $question->line;

            if ($question->image !== null) {
                $problem = $file->image($question->image)['problem'];

                if ($problem !== null) {
                    $report->imageProblems[] = ['line' => $question->line, 'image' => $question->image, 'problem' => $problem];
                }
            }

            if ($existingModules !== null && $question->module !== null && ! isset($knownModules[ModuleCatalog::key($question->module)])) {
                $newModules[ModuleCatalog::key($question->module)] ??= $question->module;
            }
        }

        $report->newModules = array_values($newModules);

        return $report;
    }

    public function file(): ExchangeFile
    {
        return $this->file;
    }

    public function addNotice(string $notice): void
    {
        $this->notices[] = $notice;
    }

    /**
     * Questions à importer, doublons écartés ou non.
     *
     * @return list<QuestionData>
     */
    public function questionsToImport(bool $skipDuplicates): array
    {
        return array_values(array_filter(
            $this->file->csv()->questions,
            fn (QuestionData $question): bool => ! ($skipDuplicates && isset($this->duplicateLines[$question->line])),
        ));
    }

    public function validCount(): int
    {
        return count($this->file->csv()->questions);
    }

    public function hasDuplicates(): bool
    {
        return $this->duplicates !== [];
    }

    /**
     * Contenu du rapport pour l'affichage.
     *
     * @return array{file: string, valid: int, errors: list<array{line: int, message: string}>, unknownColumns: list<string>, duplicates: list<array{line: int, statement: string, reason: string}>, imageProblems: list<array{line: int, image: string, problem: string}>, newModules: list<string>, notices: list<string>}
     */
    public function toArray(): array
    {
        return [
            'file' => $this->file->name(),
            'valid' => $this->validCount(),
            'errors' => $this->file->csv()->errors,
            'unknownColumns' => $this->file->csv()->unknownColumns,
            'duplicates' => $this->duplicates,
            'imageProblems' => $this->imageProblems,
            'newModules' => $this->newModules,
            'notices' => $this->notices,
        ];
    }

    private function addDuplicate(QuestionData $question, string $reason): void
    {
        $this->duplicates[] = ['line' => $question->line, 'statement' => $question->statement, 'reason' => $reason];
        $this->duplicateLines[$question->line] = true;
    }
}
