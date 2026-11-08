<?php

namespace App\Qcm\Exchange;

use App\Qcm\Models\Exam;
use App\Qcm\Models\Questionnaire;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Import d'un questionnaire, et de son examen si le fichier en décrit un
 * (PRD QCM UC-17). L'import crée toujours de nouveaux objets ; toutes les
 * questions deviennent des questions propres, dans l'ordre du fichier.
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

        foreach ($parameters->warnings() as $warning) {
            $report->addNotice('Paramètre corrigé — '.$warning);
        }

        return ['report' => $report, 'questionnaire' => $questionnaire, 'exam' => $exam];
    }

    /**
     * @param  string|null  $questionnaireTitle  titre retenu ; null = titre du fichier
     * @param  string|null  $examTitle  titre retenu pour l'examen ; null = titre du fichier
     * @return array{questionnaire: Questionnaire, exam: Exam|null, imported: int, skipped: int}
     */
    public function import(ExchangeFile $file, ?string $questionnaireTitle, ?string $examTitle, bool $skipDuplicates): array
    {
        $analysis = $this->analyse($file);
        $questions = $analysis['report']->questionsToImport($skipDuplicates);
        $persister = new QuestionPersister;

        try {
            [$questionnaire, $exam] = DB::transaction(function () use ($analysis, $questions, $persister, $file, $questionnaireTitle, $examTitle): array {
                $questionnaire = Questionnaire::create([
                    ...$analysis['questionnaire'],
                    'title' => filled($questionnaireTitle) ? $questionnaireTitle : $analysis['questionnaire']['title'],
                ]);

                foreach ($questions as $data) {
                    $question = $persister->create($data, ['questionnaire_id' => $questionnaire->id], $file);
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
            'skipped' => $analysis['report']->validCount() - count($questions),
        ];
    }
}
