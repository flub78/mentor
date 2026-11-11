<?php

namespace App\Qcm\Exchange;

use App\Banques\Exchange\ExchangeFile;
use App\Banques\Exchange\ExchangeKind;
use App\Banques\Exchange\ImportReport;
use App\Banques\Exchange\QuestionPersister;
use App\Banques\Exchange\QuestionRow;
use App\Banques\Models\Bank;
use App\Qcm\Models\Exam;
use App\Qcm\Models\Question;
use App\Qcm\Models\Questionnaire;
use App\Qcm\Questionnaires\UsableQuestions;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Import d'un questionnaire, et de son examen si le fichier en décrit un
 * (PRD QCM UC-17). L'import crée toujours de nouveaux objets ; les questions
 * sont rangées dans une banque choisie (la banque par défaut à défaut), dans
 * l'ordre du fichier. Seules les questions QCM et vrai / faux sont reprises.
 */
final class QuestionnaireImporter
{
    /**
     * Rapport avant import, avec les paramètres lus.
     *
     * @return array{report: ImportReport, questionnaire: array{title: string, description: string|null, version: string|null}, exam: array<string, mixed>|null}
     */
    public function analyse(ExchangeFile $file): array
    {
        $report = ImportReport::analyse($file);
        $parameters = new ExchangeParameters;
        $questionnaire = $parameters->questionnaire($file);
        $exam = $parameters->exam($file, $questionnaire['title']);

        $report->addNotice(match ($file->kind()) {
            ExchangeKind::Exam => 'Fichier d\'examen : un questionnaire et un examen qui l\'utilise seront créés.',
            ExchangeKind::Questionnaire => 'Fichier de questionnaire : un questionnaire sera créé.',
            ExchangeKind::Bank => 'Fichier de banque : un questionnaire sera créé avec ses questions, sans examen.',
            null => 'Fichier sans paramètres : un questionnaire sera créé, titré d\'après le nom du fichier, sans examen.',
        });

        $ignored = UsableQuestions::message(count($file->csv()->questions) - count(self::usable($file->csv()->questions)));

        if ($ignored !== null) {
            $report->addNotice($ignored);
        }

        foreach ($parameters->warnings() as $warning) {
            $report->addNotice('Paramètre corrigé — '.$warning);
        }

        return ['report' => $report, 'questionnaire' => $questionnaire, 'exam' => $exam];
    }

    /**
     * @param  string|null  $questionnaireTitle  titre retenu ; null = titre du fichier
     * @param  string|null  $examTitle  titre retenu pour l'examen ; null = titre du fichier
     * @param  int|null  $bankId  banque où ranger les questions ; null = banque par défaut
     * @return array{questionnaire: Questionnaire, exam: Exam|null, imported: int, skipped: int, bank: Bank}
     */
    public function import(ExchangeFile $file, ?string $questionnaireTitle, ?string $examTitle, bool $skipDuplicates, ?int $bankId = null): array
    {
        $analysis = $this->analyse($file);
        $questions = self::usable($analysis['report']->questionsToImport($skipDuplicates));
        $bank = $bankId === null ? Bank::default() : Bank::query()->findOrFail($bankId);
        $persister = new QuestionPersister;

        try {
            [$questionnaire, $exam] = DB::transaction(function () use ($analysis, $questions, $persister, $file, $questionnaireTitle, $examTitle, $bank): array {
                $questionnaire = Questionnaire::create([
                    ...$analysis['questionnaire'],
                    'title' => filled($questionnaireTitle) ? $questionnaireTitle : $analysis['questionnaire']['title'],
                ]);

                foreach ($questions as $row) {
                    $question = $persister->create($row, $bank->id, $file);
                    $questionnaire->questions()->attach($question->id);
                }

                $exam = $analysis['exam'] === null ? null : Exam::create([
                    ...$analysis['exam'],
                    'title' => filled($examTitle) ? $examTitle : $analysis['exam']['title'],
                    'questionnaire_id' => $questionnaire->id,
                ]);

                return [$questionnaire, $exam];
            });
        } catch (Throwable $exception) {
            $persister->discardStoredImages();

            throw $exception;
        }

        return [
            'questionnaire' => $questionnaire,
            'exam' => $exam,
            'imported' => count($questions),
            'skipped' => count(self::usable($analysis['report']->questionsToImport(false))) - count($questions),
            'bank' => $bank,
        ];
    }

    /**
     * Lignes des types posés par le module QCM.
     *
     * @param  list<QuestionRow>  $rows
     * @return list<QuestionRow>
     */
    private static function usable(array $rows): array
    {
        return array_values(array_filter($rows, fn (QuestionRow $row): bool => in_array($row->type, Question::USABLE_TYPES, true)));
    }
}
