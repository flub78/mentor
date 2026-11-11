<?php

namespace App\Qcm\Livewire;

use App\Qcm\Exams\AttemptException;
use App\Qcm\Exams\AttemptFinisher;
use App\Qcm\Exams\AttemptRecorder;
use App\Qcm\Exams\AttemptStarter;
use App\Qcm\Models\Answer;
use App\Qcm\Models\Attempt;
use App\Qcm\Models\AttemptItem;
use App\Qcm\Models\Exam;
use App\Qcm\Models\Question;
use App\Qcm\Models\Result;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Passage d'un examen par un candidat, hors du panel (PRD QCM UC-12, UC-13) :
 * accueil, identification, questions, confirmation, certificat et correction.
 *
 * Le passage est enregistré au fil des réponses ; son identifiant est gardé
 * dans la session pour le reprendre après un rechargement (plan QCM §6.2).
 * En prévisualisation (UC-20, étape 5), le même écran fonctionne avec une
 * identité fictive et rien n'est enregistré : l'état reste dans le composant.
 */
#[Layout('qcm.layouts.examen')]
class TakeExam extends Component
{
    public const STEP_WELCOME = 'welcome';

    public const STEP_IDENTIFY = 'identify';

    public const STEP_QUESTION = 'question';

    public const STEP_CONFIRM = 'confirm';

    public const STEP_DONE = 'done';

    /** Filtres de la correction détaillée (UC-13). */
    public const FILTERS = ['all' => 'Toutes', 'wrong' => 'Fausses', 'unanswered' => 'Non répondues', 'bookmarked' => 'Marquées'];

    public Exam $exam;

    #[Locked]
    public bool $preview = false;

    #[Locked]
    public string $step = self::STEP_WELCOME;

    /** @var array<string, string> saisie de l'identification */
    public array $identity = [];

    #[Locked]
    public ?int $attemptId = null;

    /** Question affichée (rang dans le passage). */
    #[Locked]
    public int $position = 0;

    /**
     * État du passage en prévisualisation, jamais enregistré.
     *
     * @var list<array{question_id: int, answer_order: list<string>, chosen_key: string|null, bookmarked: bool}>
     */
    #[Locked]
    public array $previewItems = [];

    /** @var array<string, string> identité validée en prévisualisation */
    #[Locked]
    public array $previewIdentity = [];

    #[Locked]
    public ?string $previewStartedAt = null;

    public string $filter = 'all';

    /** Message à afficher en tête d'écran (temps écoulé, refus…). */
    #[Locked]
    public ?string $notice = null;

    /**
     * @param  bool  $asPreview  prévisualisation demandée hors route (composant intégré, tests)
     */
    public function mount(Exam $exam, bool $asPreview = false): void
    {
        $this->exam = $exam;
        $this->preview = $asPreview || request()->routeIs('qcm.examens.apercu');

        if ($this->preview) {
            $this->identity = $this->fictitiousIdentity();

            return;
        }

        $attempt = Attempt::query()
            ->whereKey(session($this->sessionKey()))
            ->where('exam_id', $exam->id)
            ->first();

        if ($attempt !== null) {
            $this->attemptId = $attempt->id;
            $this->resume($attempt);
        }
    }

    /**
     * Tout accès à un passage échu le termine (clôture paresseuse).
     */
    public function hydrate(): void
    {
        $attempt = $this->attempt();

        if ($attempt !== null && app(AttemptFinisher::class)->closeIfOverdue($attempt)) {
            $this->showResult(AttemptException::timeUp()->getMessage());
        }
    }

    public function begin(): void
    {
        if ($this->questionCount() === 0) {
            $this->notice = AttemptException::emptyQuestionnaire($this->exam)->getMessage();

            return;
        }

        $this->notice = null;
        $this->step = self::STEP_IDENTIFY;
    }

    public function start(AttemptStarter $starter): void
    {
        $this->notice = null;

        try {
            if ($this->preview) {
                $this->previewIdentity = $starter->validateIdentity($this->exam, $this->identity);
                $this->previewItems = array_map(
                    fn (array $item): array => [...$item, 'chosen_key' => null, 'bookmarked' => false],
                    $starter->compose($this->exam),
                );
                $this->previewStartedAt = now()->toIso8601String();
            } else {
                $attempt = $starter->start($this->exam, $this->identity);
                $this->attemptId = $attempt->id;
                session()->put($this->sessionKey(), $attempt->id);
            }
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(
                collect($exception->errors())->mapWithKeys(fn (array $messages, string $key): array => ['identity.'.$key => $messages])->all(),
            );
        } catch (AttemptException $exception) {
            $this->notice = $exception->getMessage();

            return;
        }

        $this->position = 0;
        $this->step = self::STEP_QUESTION;
    }

    public function choose(string $answerKey): void
    {
        if (! $this->isAnswering()) {
            return;
        }

        if ($this->preview) {
            if (in_array($answerKey, $this->previewItems[$this->position]['answer_order'], true)) {
                $this->previewItems[$this->position]['chosen_key'] = $answerKey;
                unset($this->items);
            }

            return;
        }

        $this->record(fn (AttemptRecorder $recorder, AttemptItem $item) => $recorder->answer($item, $answerKey));
    }

    public function toggleBookmark(): void
    {
        if (! $this->isAnswering()) {
            return;
        }

        if ($this->preview) {
            $this->previewItems[$this->position]['bookmarked'] = ! $this->previewItems[$this->position]['bookmarked'];
            unset($this->items);

            return;
        }

        $this->record(fn (AttemptRecorder $recorder, AttemptItem $item) => $recorder->bookmark($item, ! $item->bookmarked));
    }

    public function goTo(int $position): void
    {
        if (in_array($this->step, [self::STEP_QUESTION, self::STEP_CONFIRM], true) && $position >= 0 && $position < $this->total()) {
            $this->position = $position;
            $this->step = self::STEP_QUESTION;
        }
    }

    public function next(): void
    {
        $this->goTo($this->position + 1);
    }

    public function previous(): void
    {
        $this->goTo($this->position - 1);
    }

    /**
     * Avant de terminer : rappel des questions non répondues et marquées
     * (UC-12, étape 8).
     */
    public function reviewBeforeFinish(): void
    {
        if ($this->step === self::STEP_QUESTION) {
            $this->step = self::STEP_CONFIRM;
        }
    }

    public function finish(AttemptFinisher $finisher): void
    {
        if (! in_array($this->step, [self::STEP_QUESTION, self::STEP_CONFIRM], true)) {
            return;
        }

        if (! $this->preview) {
            $attempt = $this->attempt();

            if ($attempt !== null) {
                $finisher->finish($attempt);
            }
        }

        $this->showResult();
    }

    /**
     * Fin du compte à rebours dans le navigateur : le serveur vérifie
     * l'échéance avec sa propre horloge (UC-12, 3a).
     */
    public function timeUp(): void
    {
        if ($this->preview) {
            if ($this->isAnswering() && $this->previewDeadlinePassed()) {
                $this->showResult(AttemptException::timeUp()->getMessage());
            }

            return;
        }

        // L'échéance atteinte a déjà été traitée par hydrate() ; sinon, le
        // rendu renvoie le temps restant exact et le compte à rebours repart.
    }

    /**
     * Libère le poste pour le candidat suivant.
     */
    public function newAttempt(): void
    {
        session()->forget($this->sessionKey());
        $this->reset(['step', 'identity', 'attemptId', 'position', 'previewItems', 'previewIdentity', 'previewStartedAt', 'filter', 'notice']);

        if ($this->preview) {
            $this->identity = $this->fictitiousIdentity();
        }
    }

    public function render(): View
    {
        return view('qcm.livewire.take-exam', [
            'fieldLabels' => AttemptStarter::fieldLabels($this->exam),
            'requirements' => $this->exam->identification_fields,
        ])->title($this->exam->title);
    }

    /**
     * Questions du passage, dans l'ordre, avec ce qu'il faut pour les afficher.
     *
     * @return list<array{position: int, question: Question|null, answers: list<Answer>, chosen: string|null, bookmarked: bool}>
     */
    #[Computed]
    public function items(): array
    {
        if ($this->preview) {
            $rows = array_map(fn (array $item, int $position): array => [...$item, 'position' => $position], $this->previewItems, array_keys($this->previewItems));
        } else {
            $attempt = $this->attempt();
            $rows = $attempt === null ? [] : $attempt->items()->get()->map(fn (AttemptItem $item): array => [
                'position' => $item->position,
                'question_id' => $item->question_id,
                'answer_order' => $item->answer_order,
                'chosen_key' => $item->chosen_key,
                'bookmarked' => $item->bookmarked,
            ])->all();
        }

        $questions = Question::query()
            ->findMany(array_filter(array_column($rows, 'question_id')))
            ->keyBy('id');

        return array_values(array_map(function (array $row) use ($questions): array {
            /** @var Question|null $question */
            $question = $row['question_id'] === null ? null : $questions->get($row['question_id']);
            $answers = $question === null ? new Collection : $question->answers->keyBy('key');

            return [
                'position' => (int) $row['position'],
                'question' => $question,
                'answers' => array_values(array_filter(array_map(fn (string $key): ?Answer => $answers->get($key), $row['answer_order']))),
                'chosen' => $row['chosen_key'],
                'bookmarked' => (bool) $row['bookmarked'],
            ];
        }, $rows));
    }

    /**
     * Résultat à présenter sur le certificat : enregistré, ou calculé sans
     * enregistrement en prévisualisation.
     */
    #[Computed]
    public function result(): ?Result
    {
        if ($this->step !== self::STEP_DONE) {
            return null;
        }

        if (! $this->preview) {
            return $this->attempt()?->result;
        }

        $items = array_map(function (array $item): AttemptItem {
            $line = new AttemptItem(['question_id' => $item['question']?->id, 'chosen_key' => $item['chosen']]);
            $line->setRelation('question', $item['question']);

            return $line;
        }, $this->items());

        $attributes = app(AttemptFinisher::class)->resultAttributes(
            $items,
            $this->exam,
            $this->previewIdentity,
            $this->previewStartedAt === null ? now() : Carbon::parse($this->previewStartedAt),
        );

        return new Result([...$attributes, 'certificate_number' => 'APERÇU']);
    }

    /**
     * Correction détaillée, filtrée (UC-13) ; vide si l'examen ne la prévoit pas.
     *
     * @return list<array{position: int, question: Question|null, answers: list<Answer>, chosen: string|null, bookmarked: bool}>
     */
    #[Computed]
    public function correction(): array
    {
        if ($this->step !== self::STEP_DONE || ! $this->exam->shows_correction) {
            return [];
        }

        return array_values(array_filter($this->items(), fn (array $item): bool => match ($this->filter) {
            'wrong' => ! self::isCorrect($item),
            'unanswered' => $item['chosen'] === null,
            'bookmarked' => $item['bookmarked'],
            default => true,
        }));
    }

    /**
     * Temps restant en secondes, selon l'horloge du serveur ; null sans durée.
     */
    public function remainingSeconds(): ?int
    {
        if ($this->preview) {
            if ($this->exam->duration_minutes === null || $this->previewStartedAt === null) {
                return null;
            }

            return max(0, (int) now()->diffInSeconds(Carbon::parse($this->previewStartedAt)->addMinutes($this->exam->duration_minutes), false));
        }

        return $this->attempt()?->remainingSeconds();
    }

    public function questionCount(): int
    {
        return $this->exam->questionnaire->questions()->count();
    }

    public function total(): int
    {
        return count($this->items());
    }

    /**
     * @param  array{chosen: string|null, answers: list<Answer>}  $item
     */
    public static function isCorrect(array $item): bool
    {
        return $item['chosen'] !== null
            && collect($item['answers'])->contains(fn (Answer $answer): bool => $answer->key === $item['chosen'] && $answer->is_correct);
    }

    private function attempt(): ?Attempt
    {
        return $this->attemptId === null ? null : Attempt::query()->with('result')->find($this->attemptId);
    }

    private function resume(Attempt $attempt): void
    {
        if (app(AttemptFinisher::class)->closeIfOverdue($attempt)) {
            $this->showResult(AttemptException::timeUp()->getMessage());

            return;
        }

        if (! $attempt->isInProgress()) {
            $this->showResult();

            return;
        }

        // Reprise sur la première question sans réponse (UC-12, 3b).
        $this->position = (int) ($attempt->items()->whereNull('chosen_key')->min('position') ?? 0);
        $this->step = self::STEP_QUESTION;
    }

    /**
     * @param  callable(AttemptRecorder, AttemptItem): void  $action
     */
    private function record(callable $action): void
    {
        $item = AttemptItem::query()->where('attempt_id', $this->attemptId)->where('position', $this->position)->first();

        if ($item === null) {
            $this->notice = 'Cette question n\'existe plus dans le passage. Rechargez la page.';

            return;
        }

        try {
            $action(app(AttemptRecorder::class), $item);
            unset($this->items);
        } catch (AttemptException $exception) {
            $this->showResult($exception->getMessage());
        }
    }

    private function showResult(?string $notice = null): void
    {
        $this->notice = $notice;
        $this->filter = 'all';
        $this->step = self::STEP_DONE;
        unset($this->items, $this->result, $this->correction);
    }

    private function isAnswering(): bool
    {
        return in_array($this->step, [self::STEP_QUESTION, self::STEP_CONFIRM], true);
    }

    private function previewDeadlinePassed(): bool
    {
        $remaining = $this->remainingSeconds();

        return $remaining !== null && $remaining === 0;
    }

    private function sessionKey(): string
    {
        return 'qcm.passages.'.$this->exam->id;
    }

    /**
     * Identité fictive de la prévisualisation : chaque champ demandé est rempli.
     *
     * @return array<string, string>
     */
    private function fictitiousIdentity(): array
    {
        $values = [
            AttemptStarter::LAST_NAME => 'Exemple',
            AttemptStarter::FIRST_NAME => 'Candidat',
            'email' => 'candidat@exemple.fr',
            'date_naissance' => '2000-01-01',
            'organisation' => 'Organisation exemple',
            'identifiant' => 'EX-0001',
        ];

        return array_intersect_key($values, AttemptStarter::fieldLabels($this->exam));
    }
}
