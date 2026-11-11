@php
    use App\Qcm\Filament\Pages\Study;
    use App\Qcm\Models\Question;
    use Illuminate\Support\Facades\Storage;

    // Le panel utilise la feuille de style précompilée de Filament : la mise en
    // page s'appuie sur ses composants, complétés de quelques styles en ligne.
    $stack = 'display: flex; flex-direction: column; gap: 1rem;';
    $row = 'display: flex; flex-wrap: wrap; align-items: center; gap: .75rem;';
@endphp

<x-filament-panels::page>
    @if ($step === Study::STEP_CONFIG)
        @php($banks = $this->getBanks())

        <x-filament::section heading="1. Banques" description="Choisissez une ou plusieurs banques à étudier.">
            @if ($banks->isEmpty())
                <p>Aucune banque pour l'instant. Créez-en une dans « Banques de questions ».</p>
            @else
                <div style="{{ $stack }} gap: .5rem;">
                    @foreach ($banks as $bankItem)
                        @php($ignored = $bankItem->ignoredQuestionsCount())
                        <label style="{{ $row }} {{ $bankItem->questions_count === 0 ? 'opacity: .5;' : '' }}" wire:key="bank-{{ $bankItem->id }}">
                            <x-filament::input.checkbox wire:model.live="selectedBanks" value="{{ $bankItem->id }}" :disabled="$bankItem->questions_count === 0" />
                            <span>{{ $bankItem->name }}</span>
                            <x-filament::badge color="gray">{{ $bankItem->questions_count }} question(s)</x-filament::badge>
                            @if ($ignored > 0)
                                <x-filament::badge color="warning">{{ $ignored }} d'un autre type, non utilisée(s)</x-filament::badge>
                            @endif
                        </label>
                    @endforeach
                </div>
            @endif
        </x-filament::section>

        @if ($selectedBanks !== [])
            <x-filament::section heading="2. Questions par module" description="Indiquez combien de questions tirer dans chaque module, ou cochez « Toutes ».">
                @if ($modules === [])
                    <p>Les banques choisies ne contiennent aucune question.</p>
                @else
                    <div style="{{ $stack }}">
                        @foreach ($modules as $index => $module)
                            <div style="{{ $row }}" wire:key="module-{{ md5($module['key']) }}">
                                <span style="min-width: 12rem; font-weight: 600;">{{ $module['label'] }}</span>
                                <x-filament::badge color="gray">{{ $module['available'] }} disponible(s)</x-filament::badge>
                                <label style="{{ $row }} gap: .4rem;">
                                    <x-filament::input.checkbox wire:model.live="modules.{{ $index }}.all" />
                                    <span>Toutes</span>
                                </label>
                                @unless ($module['all'])
                                    <div style="width: 7rem;">
                                        <x-filament::input.wrapper>
                                            <x-filament::input
                                                type="number"
                                                min="0"
                                                max="{{ $module['available'] }}"
                                                wire:model.live.debounce.300ms="modules.{{ $index }}.requested"
                                                aria-label="Nombre de questions pour {{ $module['label'] }}"
                                            />
                                        </x-filament::input.wrapper>
                                    </div>
                                @endunless
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-filament::section>
        @endif

        @php($planned = $this->plannedTotal())
        <div style="{{ $row }}">
            <x-filament::button wire:click="start" :disabled="$planned === 0" icon="heroicon-o-play">
                Commencer
            </x-filament::button>
            <span>{{ $planned }} question(s) seront tirées au hasard.</span>
        </div>
    @elseif ($step === Study::STEP_QUESTION && $session !== null)
        @php($question = $this->currentQuestion())
        @php($answered = $session->isCurrentAnswered())

        <div style="{{ $row }}">
            <x-filament::badge color="primary">Question {{ $session->position + 1 }} / {{ $session->total() }}</x-filament::badge>
            <x-filament::badge :color="$session->answeredCount() === 0 ? 'gray' : ($session->percentage() >= 75 ? 'success' : 'warning')">
                {{ $session->percentage() }} % de bonnes réponses ({{ $session->correctCount() }} / {{ $session->answeredCount() }})
            </x-filament::badge>
            <x-filament::badge color="gray">{{ $session->remaining() }} restante(s)</x-filament::badge>
        </div>

        @if ($question === null)
            <x-filament::callout color="danger" heading="Cette question n'existe plus.">
                Elle a été supprimée pendant la session. Passez à la suivante.
            </x-filament::callout>
            <div><x-filament::button wire:click="nextQuestion">Question suivante</x-filament::button></div>
        @else
            <x-filament::section :heading="$question->module ?? 'Sans module'">
                <div style="{{ $stack }}">
                    <p style="font-size: 1.1rem; font-weight: 600; white-space: pre-line;">{{ $question->statement }}</p>

                    @if ($question->image_path)
                        <img src="{{ Storage::disk(Question::IMAGE_DISK)->url($question->image_path) }}"
                             alt="Illustration de la question"
                             style="max-width: 100%; max-height: 28rem; object-fit: contain; align-self: flex-start;">
                    @endif

                    <div style="{{ $stack }} gap: .6rem;" role="radiogroup" aria-label="Réponses">
                        @foreach ($this->currentAnswers($question) as $answer)
                            @php($isChosen = $answered && $session->responses[$question->id] === $answer->key)
                            <div wire:key="answer-{{ $answer->key }}" style="{{ $stack }} gap: .25rem;">
                                <label style="{{ $row }} gap: .5rem; cursor: {{ $answered ? 'default' : 'pointer' }};">
                                    <x-filament::input.radio
                                        name="choice"
                                        value="{{ $answer->key }}"
                                        wire:model="choice"
                                        :disabled="$answered"
                                    />
                                    <span>{{ $answer->text }}</span>
                                    @if ($answered && $answer->is_correct)
                                        <x-filament::badge color="success" icon="heroicon-m-check">Bonne réponse</x-filament::badge>
                                    @elseif ($isChosen)
                                        <x-filament::badge color="danger" icon="heroicon-m-x-mark">Votre réponse</x-filament::badge>
                                    @endif
                                </label>
                                @if ($isChosen && ! $answer->is_correct && filled($answer->explanation))
                                    <p style="margin-left: 1.75rem; white-space: pre-line;">{{ $answer->explanation }}</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            </x-filament::section>

            @if ($answered)
                @php($correct = $session->isCorrect($question->id))
                <x-filament::callout
                    :color="$correct ? 'success' : 'danger'"
                    :icon="$correct ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle'"
                    :heading="$correct ? 'Bonne réponse !' : 'Mauvaise réponse.'"
                    :description="$question->explanation"
                />
                <div style="{{ $row }}">
                    <x-filament::button wire:click="nextQuestion" icon="heroicon-o-arrow-right">
                        {{ $session->position + 1 < $session->total() ? 'Question suivante' : 'Voir le bilan' }}
                    </x-filament::button>
                </div>
            @else
                <div style="{{ $row }}">
                    <x-filament::button wire:click="validateAnswer" icon="heroicon-o-check">Valider</x-filament::button>
                    <x-filament::button wire:click="finish" color="gray">Terminer la session</x-filament::button>
                </div>
            @endif
        @endif
    @elseif ($step === Study::STEP_SUMMARY && $session !== null)
        <x-filament::section heading="Bilan de la session">
            <div style="{{ $stack }}">
                <p style="font-size: 1.25rem; font-weight: 600;">
                    {{ $session->percentage() }} % de bonnes réponses
                    ({{ $session->correctCount() }} / {{ $session->answeredCount() }})
                </p>
                @if ($session->answeredCount() < $session->total())
                    <p>Session terminée avant la fin : {{ $session->answeredCount() }} question(s) répondue(s) sur {{ $session->total() }}.</p>
                @endif

                @if ($session->byModule() !== [])
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="text-align: left; border-bottom: 1px solid rgba(127,127,127,.3);">
                                <th style="padding: .4rem;">Module</th>
                                <th style="padding: .4rem;">Bonnes réponses</th>
                                <th style="padding: .4rem;">Taux</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($session->byModule() as $stat)
                                <tr style="border-bottom: 1px solid rgba(127,127,127,.15);">
                                    <td style="padding: .4rem;">{{ $stat['label'] }}</td>
                                    <td style="padding: .4rem;">{{ $stat['correct'] }} / {{ $stat['answered'] }}</td>
                                    <td style="padding: .4rem;">{{ $stat['percentage'] }} %</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </x-filament::section>

        @php($failed = $this->failedQuestions())
        @if ($failed->isNotEmpty())
            <x-filament::section heading="Questions échouées">
                <div style="{{ $stack }}">
                    @foreach ($failed as $failedQuestion)
                        <div wire:key="failed-{{ $failedQuestion->id }}">
                            <p style="font-weight: 600; white-space: pre-line;">{{ $failedQuestion->statement }}</p>
                            <p>Bonne réponse : {{ $failedQuestion->correctAnswer?->text }}</p>
                        </div>
                    @endforeach
                </div>
            </x-filament::section>
        @endif

        <div>
            <x-filament::button wire:click="restart" icon="heroicon-o-arrow-path">Nouvelle session</x-filament::button>
        </div>
    @endif
</x-filament-panels::page>
