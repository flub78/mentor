<?php

namespace App\Banques\Questions;

use App\Banques\Questions\Handlers\AutoEvaluationHandler;
use App\Banques\Questions\Handlers\QcmHandler;
use App\Banques\Questions\Handlers\ValeurNumeriqueHandler;
use App\Banques\Questions\Handlers\VraiFauxHandler;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Support\Facades\Validator;

/**
 * Types de questions (PRD Banques §6). Chaque type délègue la forme de sa
 * réponse attendue et sa correction à un QuestionTypeHandler.
 */
enum QuestionType: string implements HasLabel
{
    case Qcm = 'qcm';
    case VraiFaux = 'vrai_faux';
    case AutoEvaluation = 'auto_evaluation';
    case ValeurNumerique = 'valeur_numerique';

    public function getLabel(): string
    {
        return match ($this) {
            self::Qcm => 'QCM',
            self::VraiFaux => 'Vrai / faux',
            self::AutoEvaluation => 'Auto-évaluation',
            self::ValeurNumerique => 'Valeur numérique',
        };
    }

    public function handler(): QuestionTypeHandler
    {
        return match ($this) {
            self::Qcm => new QcmHandler,
            self::VraiFaux => new VraiFauxHandler,
            self::AutoEvaluation => new AutoEvaluationHandler,
            self::ValeurNumerique => new ValeurNumeriqueHandler,
        };
    }

    /**
     * Erreurs de cohérence de la réponse attendue, en français ; liste vide si
     * elle est valide.
     *
     * @param  array<string, mixed>  $payload
     * @return list<string>
     */
    public function payloadErrors(array $payload): array
    {
        $handler = $this->handler();

        return array_values(Validator::make($payload, $handler->rules(), $handler->messages())
            ->errors()
            ->all());
    }
}
