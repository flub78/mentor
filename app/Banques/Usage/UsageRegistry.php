<?php

namespace App\Banques\Usage;

use App\Banques\Models\Bank;
use App\Banques\Models\Question;
use Illuminate\Contracts\Container\Container;

/**
 * Réunit les utilisations déclarées par les modules (`QuestionUsage`).
 */
final class UsageRegistry
{
    /** Tag de conteneur des implémentations de `QuestionUsage`. */
    public const TAG = 'banques.question-usages';

    public function __construct(private readonly Container $container) {}

    public function bankDeletionNotice(Bank $bank): string
    {
        return $this->join(fn (QuestionUsage $usage): ?string => $usage->bankDeletionNotice($bank));
    }

    public function questionDeletionNotice(Question $question): string
    {
        return $this->join(fn (QuestionUsage $usage): ?string => $usage->questionDeletionNotice($question));
    }

    public function substantiveChangeNotice(Question $question): string
    {
        return $this->join(fn (QuestionUsage $usage): ?string => $usage->substantiveChangeNotice($question));
    }

    public function hasSubstantiveChangeEffect(Question $question): bool
    {
        return $this->substantiveChangeNotice($question) !== '';
    }

    public function applySubstantiveChange(Question $question): string
    {
        return $this->join(fn (QuestionUsage $usage): ?string => $usage->applySubstantiveChange($question));
    }

    public function moveNotice(Question $question, Bank $target): string
    {
        return $this->join(fn (QuestionUsage $usage): ?string => $usage->moveNotice($question, $target));
    }

    public function applyMove(Question $question): string
    {
        return $this->join(fn (QuestionUsage $usage): ?string => $usage->applyMove($question));
    }

    /**
     * @param  callable(QuestionUsage): ?string  $notice
     */
    private function join(callable $notice): string
    {
        $parts = [];

        foreach ($this->container->tagged(self::TAG) as $usage) {
            $text = $notice($usage);

            if ($text !== null && $text !== '') {
                $parts[] = $text;
            }
        }

        return implode(' ', $parts);
    }
}
