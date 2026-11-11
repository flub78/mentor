<?php

namespace App\Leitner\Filament\Pages;

use App\Leitner\Engine\Shuffler;
use App\Leitner\Filament\Resources\Suites\SuiteResource;
use App\Leitner\Models\Question;
use App\Leitner\Models\Session;
use App\Leitner\Models\SessionEntry;
use App\Leitner\Models\Suite;
use App\Leitner\Questions\Handlers\QcmHandler;
use App\Leitner\Questions\InvalidResponse;
use App\Leitner\Questions\QuestionType;
use App\Leitner\Questions\Verdict;
use App\Leitner\Sessions\AnswerRecorder;
use App\Leitner\Sessions\AnswerUndoer;
use App\Leitner\Sessions\EntrySkipper;
use App\Leitner\Sessions\NextSession;
use App\Leitner\Sessions\SessionException;
use App\Leitner\Sessions\SessionFinisher;
use App\Leitner\Sessions\SessionStarter;
use App\Leitner\Sessions\SessionSummary;
use App\Leitner\Suites\PresentationOrder;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;

/**
 * Séance de révision d'une suite (PRD Leitner UC-08) : lancement ou reprise,
 * une question à la fois avec correction immédiate et mouvement de boîte,
 * bilan en fin de séance.
 */
class Seance extends Page
{
    public const STEP_INTRO = 'intro';

    public const STEP_QUESTION = 'question';

    public const STEP_SUMMARY = 'summary';

    protected string $view = 'leitner.seance';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'seance';

    #[Url]
    public ?int $suite = null;

    public string $step = self::STEP_INTRO;

    public ?int $sessionId = null;

    /** Ordre de présentation choisi pour cette séance. */
    public string $order = '';

    /** Réponse en cours de saisie : clé de proposition, « vrai » / « faux », texte saisi. */
    public ?string $response = null;

    /** Auto-évaluation : la réponse de référence est affichée. */
    public bool $revealed = false;

    /** Entrée dont la correction est affichée. */
    public ?int $answeredEntryId = null;

    /** Instant d'affichage de la question, pour le temps de réponse (ms). */
    public ?float $shownAt = null;

    /** Bilan de la séance terminée (identifiant de la séance). */
    public ?int $summarySessionId = null;

    public function mount(): void
    {
        $suite = $this->suiteModel();
        $this->order = $suite->presentation_order->value;
    }

    public function getTitle(): string|Htmlable
    {
        return 'Séance — '.$this->suiteModel()->name;
    }

    public function suiteModel(): Suite
    {
        return Suite::query()->findOrFail($this->suite);
    }

    public function currentSession(): ?Session
    {
        return $this->sessionId === null ? null : Session::query()->find($this->sessionId);
    }

    public function pendingSession(): ?Session
    {
        return $this->suiteModel()->progression->currentSession();
    }

    public function nextSession(): NextSession
    {
        return NextSession::for($this->suiteModel()->progression);
    }

    public function start(): void
    {
        $result = app(SessionStarter::class)->start(
            $this->suiteModel()->progression,
            PresentationOrder::tryFrom($this->order),
        );

        if ($result->session === null) {
            Notification::make()->title('Pas de séance')->body(implode(' ', $result->notices))->warning()->send();

            return;
        }

        if ($result->notices !== []) {
            Notification::make()->title($result->resumed ? 'Séance reprise' : 'Séance commencée')->body(implode(' ', $result->notices))->info()->send();
        }

        $this->sessionId = $result->session->id;
        $this->step = self::STEP_QUESTION;
        $this->showNext();
    }

    public function resume(): void
    {
        $this->start();
    }

    public function abandon(): void
    {
        $session = $this->pendingSession();

        if ($session === null) {
            return;
        }

        $summary = app(SessionFinisher::class)->abandon($session);
        Notification::make()
            ->title('Séance abandonnée')
            ->body($summary === null ? 'Elle n\'avait aucune réponse : elle ne compte pas.' : 'Les réponses déjà données sont conservées.')
            ->success()
            ->send();
    }

    /**
     * Valide la réponse saisie pour la question en cours.
     */
    public function submit(): void
    {
        $session = $this->currentSession();
        $entry = $session?->nextEntry();

        if ($session === null || $entry === null || $this->answeredEntryId !== null) {
            return;
        }

        $this->resetErrorBag('response');

        if ($this->response === null || trim($this->response) === '') {
            $this->addError('response', $entry->question?->type === QuestionType::ValeurNumerique
                ? 'Saisissez un nombre avant de valider.'
                : 'Choisissez une réponse avant de valider.');

            return;
        }

        $this->record($session, $entry, $this->typedResponse($entry));
    }

    /**
     * Vrai / faux : la réponse est validée au clic.
     */
    public function answer(string $value): void
    {
        $this->response = $value;
        $this->submit();
    }

    /**
     * Auto-évaluation : « Je savais » ou « Je ne savais pas ».
     */
    public function selfAssess(bool $knew): void
    {
        $session = $this->currentSession();
        $entry = $session?->nextEntry();

        if ($session === null || $entry === null || ! $this->revealed || $this->answeredEntryId !== null) {
            return;
        }

        $this->record($session, $entry, $knew);
    }

    public function reveal(): void
    {
        $this->revealed = true;
    }

    public function skip(): void
    {
        $session = $this->currentSession();
        $entry = $session?->nextEntry();

        if ($session === null || $entry === null || $this->answeredEntryId !== null) {
            return;
        }

        $this->guard(fn () => app(EntrySkipper::class)->skip($session, $entry));
        $this->showNext();
    }

    public function undo(): void
    {
        $session = $this->currentSession();

        if ($session === null) {
            return;
        }

        $this->guard(function () use ($session): void {
            app(AnswerUndoer::class)->undo($session);
            $this->answeredEntryId = null;
            $this->resetAnswer();
            Notification::make()->title('Réponse annulée')->body('Répondez de nouveau à la question.')->success()->send();
        });
    }

    public function next(): void
    {
        $this->answeredEntryId = null;
        $this->showNext();
    }

    /**
     * « Terminer la séance » : fin anticipée, les questions non posées restent
     * dans leur boîte.
     */
    public function finishNow(): void
    {
        $session = $this->currentSession();

        if ($session === null) {
            return;
        }

        $this->closeSession($session);
    }

    public function answeredEntry(): ?SessionEntry
    {
        return $this->answeredEntryId === null ? null : SessionEntry::query()->with('question')->find($this->answeredEntryId);
    }

    /**
     * Entrée affichée : celle dont la correction est montrée, sinon la
     * prochaine à poser.
     */
    public function displayedEntry(): ?SessionEntry
    {
        return $this->answeredEntry() ?? $this->currentSession()?->nextEntry()?->load('question');
    }

    /**
     * Propositions d'une question QCM, mélangées de façon stable pour
     * l'entrée (sauf si le mélange est interdit), numérotées pour le clavier.
     *
     * @return array<string, string> clé → texte
     */
    public function qcmOptions(SessionEntry $entry, Question $question): array
    {
        $options = (new QcmHandler)->options($question->payload);

        if (! ($question->payload['shuffle'] ?? true)) {
            return $options;
        }

        $keys = Shuffler::shuffle(new Collection(array_keys($options)), $entry->id)->all();

        return array_combine($keys, array_map(fn (string $key): string => $options[$key], $keys));
    }

    /**
     * Position et taux de réussite des premières réponses de la séance.
     *
     * @return array{done: int, total: int, rate: int|null}
     */
    public function progress(): array
    {
        $session = $this->currentSession();

        if ($session === null) {
            return ['done' => 0, 'total' => 0, 'rate' => null];
        }

        $first = $session->entries()->where('is_retry', false)->whereNotNull('answered_at');
        $answered = (clone $first)->count();
        $correct = (clone $first)->where('verdict', Verdict::Juste)->count();

        return [
            ...$session->position(),
            'rate' => $answered === 0 ? null : (int) round(100 * $correct / $answered),
        ];
    }

    public function summary(): ?SessionSummary
    {
        $session = $this->summarySessionId === null ? null : Session::query()->find($this->summarySessionId);

        return $session === null ? null : SessionSummary::for($session);
    }

    public function suiteUrl(): string
    {
        return SuiteResource::getUrl('view', ['record' => $this->suite]);
    }

    private function record(Session $session, SessionEntry $entry, mixed $response): void
    {
        $duration = $this->shownAt === null ? null : (int) round(microtime(true) * 1000 - $this->shownAt);

        try {
            app(AnswerRecorder::class)->record($session, $entry, $response, $duration);
        } catch (InvalidResponse $exception) {
            $this->addError('response', $exception->getMessage());

            return;
        } catch (SessionException $exception) {
            $this->notifyRefusal($exception);

            return;
        }

        $this->resetErrorBag();
        $this->answeredEntryId = $entry->id;
    }

    private function showNext(): void
    {
        $session = $this->currentSession();

        if ($session === null) {
            return;
        }

        $removed = app(EntrySkipper::class)->skipDeletedQuestions($session);

        if ($removed > 0) {
            Notification::make()
                ->title('Question supprimée')
                ->body($removed === 1 ? 'Une question a été supprimée pendant la séance : elle est passée.' : "{$removed} questions ont été supprimées pendant la séance : elles sont passées.")
                ->warning()
                ->send();
        }

        $this->resetAnswer();

        if ($session->nextEntry() === null) {
            $this->closeSession($session);
        }
    }

    private function closeSession(Session $session): void
    {
        $summary = app(SessionFinisher::class)->finish($session);
        $this->sessionId = null;
        $this->answeredEntryId = null;

        if ($summary === null) {
            Notification::make()->title('Séance terminée sans réponse')->body('Elle ne compte pas dans le rythme des boîtes.')->info()->send();
            $this->step = self::STEP_INTRO;

            return;
        }

        $this->summarySessionId = $session->id;
        $this->step = self::STEP_SUMMARY;
    }

    private function resetAnswer(): void
    {
        $this->response = null;
        $this->revealed = false;
        $this->shownAt = microtime(true) * 1000;
        $this->resetErrorBag();
    }

    private function typedResponse(SessionEntry $entry): mixed
    {
        return match ($entry->question?->type) {
            QuestionType::VraiFaux => $this->response === 'vrai',
            default => $this->response,
        };
    }

    private function guard(callable $action): void
    {
        try {
            $action();
        } catch (SessionException $exception) {
            $this->notifyRefusal($exception);
        }
    }

    private function notifyRefusal(SessionException $exception): void
    {
        Notification::make()->title('Action impossible')->body($exception->getMessage())->danger()->send();
    }
}
