<?php

namespace App\Qcm\Study;

use App\Qcm\Models\Question;
use Livewire\Wireable;

/**
 * Session d'étude en cours (PRD QCM UC-11), tenue dans le composant Livewire
 * de la page : rien n'est enregistré en base (plan QCM §3).
 */
final class StudySession implements Wireable
{
    /**
     * @param  list<int>  $questionIds  ordre de passage
     * @param  array<int, list<string>>  $answerOrders  clés des réponses dans l'ordre affiché, par question
     * @param  array<int, string>  $correctAnswers  clé de la bonne réponse, par question
     * @param  array<int, string>  $moduleKeys  clé de module, par question
     * @param  array<string, string>  $moduleLabels  libellé, par clé de module
     * @param  array<int, string>  $responses  clé de la réponse choisie, par question déjà validée
     */
    public function __construct(
        public array $questionIds,
        public array $answerOrders,
        public array $correctAnswers,
        public array $moduleKeys,
        public array $moduleLabels,
        public array $responses = [],
        public int $position = 0,
    ) {}

    /**
     * Prépare une session à partir des questions tirées.
     *
     * @param  list<int>  $questionIds
     * @param  array<string, string>  $moduleLabels
     */
    public static function start(array $questionIds, array $moduleLabels, StudyDraw $draw): self
    {
        $questions = Question::query()->findMany($questionIds)->keyBy('id');
        $answerOrders = $correctAnswers = $moduleKeys = [];

        foreach ($questionIds as $id) {
            /** @var Question $question */
            $question = $questions[$id];
            $keys = $question->answers->pluck('key')->all();
            $answerOrders[$id] = $question->shufflesAnswers() ? $draw->shuffleAnswers($keys) : $keys;
            $correctAnswers[$id] = (string) $question->correctAnswer?->key;
            $moduleKeys[$id] = ModuleCatalog::key($question->module);
        }

        return new self($questionIds, $answerOrders, $correctAnswers, $moduleKeys, $moduleLabels);
    }

    public function total(): int
    {
        return count($this->questionIds);
    }

    public function isFinished(): bool
    {
        return $this->position >= $this->total();
    }

    public function currentQuestionId(): ?int
    {
        return $this->questionIds[$this->position] ?? null;
    }

    public function isCurrentAnswered(): bool
    {
        $id = $this->currentQuestionId();

        return $id !== null && array_key_exists($id, $this->responses);
    }

    /**
     * Enregistre la réponse à la question courante ; renvoie vrai si elle est bonne.
     */
    public function answer(string $answerKey): bool
    {
        $id = $this->currentQuestionId();

        if ($id === null || $this->isCurrentAnswered() || ! in_array($answerKey, $this->answerOrders[$id], true)) {
            throw new \InvalidArgumentException('Réponse impossible : la question est déjà corrigée ou la réponse ne lui appartient pas.');
        }

        $this->responses[$id] = $answerKey;

        return $this->isCorrect($id);
    }

    public function next(): void
    {
        if ($this->isCurrentAnswered()) {
            $this->position++;
        }
    }

    public function isCorrect(int $questionId): bool
    {
        return isset($this->responses[$questionId]) && $this->responses[$questionId] === $this->correctAnswers[$questionId];
    }

    public function answeredCount(): int
    {
        return count($this->responses);
    }

    public function correctCount(): int
    {
        return count(array_filter(array_keys($this->responses), fn (int $id): bool => $this->isCorrect($id)));
    }

    /**
     * Pourcentage de bonnes réponses depuis le début de la session (0 tant que rien n'est répondu).
     */
    public function percentage(): int
    {
        return self::percent($this->correctCount(), $this->answeredCount());
    }

    public function remaining(): int
    {
        return $this->total() - $this->answeredCount();
    }

    /**
     * Résultat par module, dans l'ordre des libellés reçus au démarrage.
     *
     * @return list<array{label: string, answered: int, correct: int, percentage: int}>
     */
    public function byModule(): array
    {
        $stats = [];

        foreach ($this->moduleLabels as $key => $label) {
            $stats[$key] = ['label' => $label, 'answered' => 0, 'correct' => 0, 'percentage' => 0];
        }

        foreach (array_keys($this->responses) as $id) {
            $key = $this->moduleKeys[$id];
            $stats[$key] ??= ['label' => (string) $key, 'answered' => 0, 'correct' => 0, 'percentage' => 0];
            $stats[$key]['answered']++;
            $stats[$key]['correct'] += $this->isCorrect($id) ? 1 : 0;
        }

        $result = [];

        foreach ($stats as $stat) {
            if ($stat['answered'] > 0) {
                $stat['percentage'] = self::percent($stat['correct'], $stat['answered']);
                $result[] = $stat;
            }
        }

        return $result;
    }

    /**
     * @return list<int>
     */
    public function failedQuestionIds(): array
    {
        return array_values(array_filter($this->questionIds, fn (int $id): bool => isset($this->responses[$id]) && ! $this->isCorrect($id)));
    }

    private static function percent(int $part, int $whole): int
    {
        return $whole === 0 ? 0 : (int) round(100 * $part / $whole);
    }

    /**
     * @return array<string, mixed>
     */
    public function toLivewire(): array
    {
        return get_object_vars($this);
    }

    /**
     * @param  array<string, mixed>  $value
     */
    public static function fromLivewire($value): self
    {
        $int = fn (array $a): array => array_map('intval', $a);

        return new self(
            questionIds: $int(array_values($value['questionIds'])),
            answerOrders: array_map(fn (array $keys): array => array_map('strval', array_values($keys)), $value['answerOrders']),
            correctAnswers: array_map('strval', $value['correctAnswers']),
            moduleKeys: array_map('strval', $value['moduleKeys']),
            moduleLabels: array_map('strval', $value['moduleLabels']),
            responses: array_map('strval', $value['responses']),
            position: (int) $value['position'],
        );
    }
}
