<?php

namespace App\Qcm\Exchange;

use App\Banques\Exchange\ExchangeKind;
use App\Banques\Exchange\ExchangeWriter;
use App\Qcm\Models\Exam;
use App\Qcm\Models\Question;
use App\Qcm\Models\Questionnaire;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Export d'un questionnaire, ou d'un examen avec son questionnaire
 * (PRD QCM UC-16), au format du module Banques (PRD Banques §8). Le fichier
 * est autonome : les questions y sont écrites en entier.
 */
final class QuestionnaireExporter
{
    public function __construct(private readonly ExchangeWriter $writer = new ExchangeWriter) {}

    public function csv(Questionnaire $questionnaire): string
    {
        return $this->writer->csv($this->questions($questionnaire));
    }

    /**
     * @return string chemin du fichier temporaire
     */
    public function zip(Questionnaire $questionnaire): string
    {
        return $this->writer->zip(
            $this->questions($questionnaire),
            ExchangeKind::Questionnaire,
            ExchangeParameters::fromQuestionnaire($questionnaire),
        );
    }

    /**
     * @return string chemin du fichier temporaire
     */
    public function examZip(Exam $exam): string
    {
        return $this->writer->zip($this->questions($exam->questionnaire), ExchangeKind::Exam, [
            'questionnaire' => ExchangeParameters::fromQuestionnaire($exam->questionnaire),
            'examen' => ExchangeParameters::fromExam($exam),
        ]);
    }

    public function fileName(Questionnaire|Exam $subject, string $extension): string
    {
        [$prefix, $title] = $subject instanceof Exam ? ['examen', $subject->title] : ['questionnaire', $subject->title];

        return $prefix.'-'.(Str::slug($title) ?: $subject->id).'-'.now()->format('Y-m-d').'.'.$extension;
    }

    /**
     * @return Collection<int, Question>
     */
    private function questions(Questionnaire $questionnaire): Collection
    {
        return $questionnaire->questions()->get();
    }
}
