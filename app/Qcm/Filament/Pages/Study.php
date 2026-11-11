<?php

namespace App\Qcm\Filament\Pages;

use App\Qcm\Models\Answer;
use App\Qcm\Models\Bank;
use App\Qcm\Models\Question;
use App\Qcm\Questionnaires\UsableQuestions;
use App\Qcm\Study\ModuleCatalog;
use App\Qcm\Study\StudyDraw;
use App\Qcm\Study\StudySession;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Url;

/**
 * Étude libre en mode étude (PRD QCM UC-11) : choix des banques et du nombre
 * de questions par module, correction immédiate, pourcentage de bonnes
 * réponses tout au long de la session, bilan par module.
 */
class Study extends Page
{
    public const STEP_CONFIG = 'config';

    public const STEP_QUESTION = 'question';

    public const STEP_SUMMARY = 'summary';

    protected string $view = 'qcm.study';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static ?string $navigationLabel = 'Étudier';

    protected static ?string $title = 'Étudier';

    protected static ?int $navigationSort = 2;

    public string $step = self::STEP_CONFIG;

    /** Banque présélectionnée depuis la liste des banques (`?bank=`). */
    #[Url]
    public ?string $bank = null;

    /** @var list<string> identifiants des banques choisies */
    public array $selectedBanks = [];

    /** @var list<array{key: string, label: string, available: int, requested: int|string|null, all: bool}> */
    public array $modules = [];

    public ?StudySession $session = null;

    /** Réponse sélectionnée pour la question courante. */
    public ?string $choice = null;

    public function mount(): void
    {
        if ($this->bank !== null && Bank::query()->whereKey($this->bank)->exists()) {
            $this->selectedBanks = [(string) $this->bank];
        }

        $this->refreshModules();
    }

    public function updatedSelectedBanks(): void
    {
        $this->refreshModules();
    }

    /**
     * @return Collection<int, Bank>
     */
    public function getBanks(): Collection
    {
        return Bank::query()->withCount('questions')->orderBy('name')->get();
    }

    /**
     * Nombre de questions que la configuration actuelle tirerait.
     */
    public function plannedTotal(): int
    {
        $total = 0;

        foreach ($this->modules as $module) {
            $total += $module['all'] ? $module['available'] : min(max(0, (int) $module['requested']), $module['available']);
        }

        return $total;
    }

    public function start(StudyDraw $draw): void
    {
        $bankIds = array_map('intval', $this->selectedBanks);

        if ($bankIds === []) {
            Notification::make()->danger()->title('Choisissez au moins une banque.')->send();

            return;
        }

        $requested = $labels = [];

        foreach ($this->modules as $module) {
            $requested[$module['key']] = $module['all'] ? null : max(0, (int) $module['requested']);
            $labels[$module['key']] = $module['label'];
        }

        $result = $draw->draw($bankIds, $requested);

        if ($result['questionIds'] === []) {
            Notification::make()
                ->danger()
                ->title('Aucune question à étudier.')
                ->body('Indiquez un nombre de questions pour au moins un module, ou cochez « Toutes ».')
                ->send();

            return;
        }

        foreach ($result['reduced'] as $key => $count) {
            Notification::make()
                ->warning()
                ->title("Module « {$labels[$key]} » : {$count} question(s) disponible(s) seulement, toutes sont retenues.")
                ->send();
        }

        $ignored = UsableQuestions::notice($bankIds);

        if ($ignored !== null) {
            Notification::make()->warning()->title($ignored)->send();
        }

        $this->session = StudySession::start($result['questionIds'], $labels, $draw);
        $this->choice = null;
        $this->step = self::STEP_QUESTION;
    }

    public function validateAnswer(): void
    {
        if ($this->session === null || $this->session->isCurrentAnswered()) {
            return;
        }

        if ($this->choice === null || $this->choice === '') {
            Notification::make()->warning()->title('Choisissez une réponse avant de valider.')->send();

            return;
        }

        try {
            $this->session->answer($this->choice);
        } catch (\InvalidArgumentException $e) {
            Notification::make()->danger()->title($e->getMessage())->send();
        }
    }

    public function nextQuestion(): void
    {
        if ($this->session === null) {
            return;
        }

        $this->session->next();
        $this->choice = null;

        if ($this->session->isFinished()) {
            $this->step = self::STEP_SUMMARY;
        }
    }

    /**
     * Termine la session avant la fin et affiche le bilan des questions déjà répondues.
     */
    public function finish(): void
    {
        if ($this->session !== null) {
            $this->step = self::STEP_SUMMARY;
        }
    }

    public function restart(): void
    {
        $this->session = null;
        $this->choice = null;
        $this->step = self::STEP_CONFIG;
        $this->refreshModules();
    }

    public function currentQuestion(): ?Question
    {
        $id = $this->session?->currentQuestionId();

        return $id === null ? null : Question::query()->find($id);
    }

    /**
     * Réponses de la question courante, dans l'ordre mélangé de la session.
     *
     * @return list<Answer>
     */
    public function currentAnswers(Question $question): array
    {
        $answers = $question->answers->keyBy('key');
        $ordered = [];

        foreach ($this->session->answerOrders[$question->id] ?? [] as $key) {
            if (isset($answers[$key])) {
                $ordered[] = $answers[$key];
            }
        }

        return $ordered;
    }

    /**
     * @return Collection<int, Question>
     */
    public function failedQuestions(): Collection
    {
        $ids = $this->session?->failedQuestionIds() ?? [];

        return Question::query()->findMany($ids)->sortBy(fn (Question $q): int|false => array_search($q->id, $ids, true))->values();
    }

    /**
     * Recalcule la liste des modules des banques choisies, en conservant les
     * réglages des modules déjà affichés.
     */
    private function refreshModules(): void
    {
        $previous = [];

        foreach ($this->modules as $module) {
            $previous[$module['key']] = $module;
        }

        $catalog = (new ModuleCatalog)->forBanks(array_map('intval', $this->selectedBanks));

        $this->modules = array_map(fn (array $module): array => [
            'key' => $module['key'],
            'label' => $module['label'],
            'available' => $module['count'],
            'requested' => $previous[$module['key']]['requested'] ?? null,
            'all' => $previous[$module['key']]['all'] ?? true,
        ], $catalog);
    }
}
