@php
    use App\Leitner\Filament\Pages\Seance;
    use App\Banques\Models\Question;
    use App\Banques\Questions\Verdict;
    use App\Leitner\Suites\PresentationOrder;
    use Illuminate\Support\Facades\Storage;

    // Le panel utilise la feuille de style précompilée de Filament : mise en
    // page avec ses composants, complétés de quelques styles en ligne.
    $stack = 'display: flex; flex-direction: column; gap: 1rem;';
    $row = 'display: flex; flex-wrap: wrap; align-items: center; gap: .75rem;';
@endphp

<x-filament-panels::page>
    {{-- Raccourcis clavier : un élément porteur de data-shortcut est cliqué quand on presse sa touche. --}}
    <div
        x-data="{
            press(event) {
                if (['INPUT', 'TEXTAREA', 'SELECT'].includes(event.target.tagName) || event.ctrlKey || event.metaKey || event.altKey) {
                    return;
                }
                const key = event.key === ' ' ? 'espace' : event.key.toLowerCase();
                const element = $root.querySelector(`[data-shortcut='${key}']`);
                if (element && ! element.disabled) {
                    event.preventDefault();
                    element.click();
                }
            },
        }"
        x-on:keydown.window="press($event)"
        style="{{ $stack }}"
    >
        @if ($step === Seance::STEP_INTRO)
            @php($pending = $this->pendingSession())

            @if ($pending !== null)
                @php($position = $pending->position())
                <x-filament::section heading="Une séance est en cours">
                    <div style="{{ $stack }}">
                        <p>{{ $position['done'] }} question(s) traitée(s) sur {{ $position['total'] }}. Reprenez-la là où elle s'est arrêtée, ou abandonnez-la : les réponses déjà données sont conservées.</p>
                        <div style="{{ $row }}">
                            <x-filament::button wire:click="resume" icon="heroicon-o-play" data-shortcut="enter">Reprendre la séance</x-filament::button>
                            <x-filament::button wire:click="abandon" color="gray">Abandonner la séance</x-filament::button>
                        </div>
                    </div>
                </x-filament::section>
            @else
                @php($next = $this->nextSession())
                <x-filament::section :heading="'Séance n° '.$next->number">
                    <div style="{{ $stack }}">
                        <p>{{ $next->describe() }}</p>
                        <div style="max-width: 20rem;">
                            <label style="{{ $stack }} gap: .4rem;">
                                <span style="font-weight: 600;">Ordre de présentation</span>
                                <x-filament::input.wrapper>
                                    <x-filament::input.select wire:model="order">
                                        @foreach (PresentationOrder::cases() as $case)
                                            <option value="{{ $case->value }}">{{ $case->getLabel() }}</option>
                                        @endforeach
                                    </x-filament::input.select>
                                </x-filament::input.wrapper>
                            </label>
                        </div>
                        <div style="{{ $row }}">
                            <x-filament::button wire:click="start" icon="heroicon-o-play" data-shortcut="enter" :disabled="$next->total() === 0">Commencer la séance</x-filament::button>
                            <x-filament::button tag="a" :href="$this->suiteUrl()" color="gray">Retour à la suite</x-filament::button>
                        </div>
                        @if ($next->total() === 0)
                            <p>Rien à poser à cette séance : ajoutez des questions ou une banque à la suite.</p>
                        @endif
                    </div>
                </x-filament::section>
            @endif
        @elseif ($step === Seance::STEP_QUESTION)
            @php($entry = $this->displayedEntry())
            @php($progress = $this->progress())

            <div style="{{ $row }}">
                <x-filament::badge color="primary">Question {{ min($progress['done'] + ($entry?->isAnswered() ? 0 : 1), $progress['total']) }} / {{ $progress['total'] }}</x-filament::badge>
                <x-filament::badge :color="$progress['rate'] === null ? 'gray' : ($progress['rate'] >= 75 ? 'success' : 'warning')">
                    {{ $progress['rate'] === null ? 'Aucune réponse' : $progress['rate'].' % de bonnes réponses' }}
                </x-filament::badge>
                <span style="flex: 1;"></span>
                <x-filament::button wire:click="finishNow" color="gray" size="sm">Terminer la séance</x-filament::button>
            </div>

            @if ($entry !== null && $entry->question !== null)
                @php($question = $entry->question)
                @php($answered = $entry->isAnswered())

                <x-filament::section>
                    <x-slot name="heading">
                        <span style="{{ $row }} gap: .5rem;">
                            <span>{{ $question->theme ?? $question->type->getLabel() }}</span>
                            @if ($entry->is_retry)
                                <x-filament::badge color="warning" size="sm">Reprise d'une erreur</x-filament::badge>
                            @elseif ($entry->is_new)
                                <x-filament::badge color="info" size="sm">Nouvelle question</x-filament::badge>
                            @elseif ($entry->box_before !== null)
                                <x-filament::badge color="gray" size="sm">Boîte {{ $entry->box_before }}</x-filament::badge>
                            @endif
                        </span>
                    </x-slot>

                    <div style="{{ $stack }}">
                        <p style="font-size: 1.15rem; font-weight: 600; white-space: pre-line;">{{ $question->statement }}</p>

                        @if ($question->image_path)
                            @php($imageUrl = Storage::disk(Question::IMAGE_DISK)->url($question->image_path))
                            <a href="{{ $imageUrl }}" target="_blank" rel="noopener" title="Agrandir l'image" style="align-self: flex-start;">
                                <img src="{{ $imageUrl }}" alt="{{ $question->image_alt }}"
                                     style="max-width: 100%; max-height: 24rem; object-fit: contain;">
                            </a>
                        @endif

                        @include($question->handler()->answerView(), ['page' => $this, 'entry' => $entry, 'question' => $question, 'answered' => $answered, 'stack' => $stack, 'row' => $row])

                        @error('response')
                            <p role="alert" style="color: rgb(220 38 38); font-weight: 600;">{{ $message }}</p>
                        @enderror
                    </div>
                </x-filament::section>

                @if ($answered)
                    @php($correct = $entry->verdict === Verdict::Juste)
                    <x-filament::callout
                        :color="$correct ? 'success' : 'danger'"
                        :icon="$correct ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle'"
                        :heading="$correct ? 'Bonne réponse' : 'Mauvaise réponse'"
                    >
                        <x-slot name="description">
                            <div style="{{ $stack }} gap: .4rem;">
                                <p><strong>Réponse attendue :</strong> {{ $question->expectedAnswer() }}</p>
                                @if (filled($entry->detail))
                                    <p style="white-space: pre-line;">{{ $entry->detail }}</p>
                                @endif
                                @if (filled($question->explanation))
                                    <p style="white-space: pre-line;">{{ $question->explanation }}</p>
                                @endif
                                <p>
                                    @if ($entry->is_retry)
                                        Reprise : la boîte de la question ne change pas.
                                    @elseif ($entry->mastered)
                                        Boîte {{ $entry->box_before }} → question maîtrisée.
                                    @elseif ($entry->box_before !== null)
                                        Boîte {{ $entry->box_before }} → boîte {{ $entry->box_after }}.
                                    @endif
                                    @if (! $correct && $this->currentSession()?->progression->suite->retry_errors && ! $entry->is_retry)
                                        Elle sera reposée en fin de séance.
                                    @endif
                                </p>
                            </div>
                        </x-slot>
                    </x-filament::callout>
                    <div style="{{ $row }}">
                        <x-filament::button wire:click="next" icon="heroicon-o-arrow-right" data-shortcut="enter">Question suivante</x-filament::button>
                        @if ($this->currentSession()?->undo_available)
                            <x-filament::button wire:click="undo" color="gray" icon="heroicon-o-arrow-uturn-left" data-shortcut="a">Annuler ma réponse</x-filament::button>
                        @endif
                    </div>
                @else
                    <div style="{{ $row }}">
                        <x-filament::button wire:click="skip" color="gray" size="sm" data-shortcut="p">Passer</x-filament::button>
                    </div>
                @endif
            @endif

            <details>
                <summary style="cursor: pointer;">Raccourcis clavier</summary>
                <p>1 à 9 : choisir une proposition · V / F : vrai ou faux · Espace : voir la réponse ·
                    O / N : je savais / je ne savais pas · Entrée : valider ou passer à la suite ·
                    P : passer · A : annuler ma réponse.</p>
            </details>
        @elseif ($step === Seance::STEP_SUMMARY)
            @php($summary = $this->summary())

            @if ($summary !== null)
                <x-filament::section heading="Bilan de la séance">
                    <div style="{{ $stack }}">
                        <p style="font-size: 1.25rem; font-weight: 600;">
                            {{ $summary->successRate() ?? 0 }} % de bonnes réponses ({{ $summary->correct }} / {{ $summary->asked }})
                        </p>
                        <ul style="list-style: disc; padding-left: 1.5rem;">
                            <li>{{ $summary->introduced }} nouvelle(s) question(s) introduite(s)</li>
                            <li>{{ $summary->mastered }} question(s) maîtrisée(s)</li>
                            @if ($summary->skipped > 0)
                                <li>{{ $summary->skipped }} question(s) passée(s)</li>
                            @endif
                        </ul>

                        @if ($summary->moves !== [])
                            <div>
                                <p style="font-weight: 600;">Mouvements entre boîtes</p>
                                <ul style="list-style: disc; padding-left: 1.5rem;">
                                    @foreach ($summary->moves as $move => $count)
                                        <li>Boîte {{ $move }} : {{ $count }} question(s)</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <p>Prochaine séance : {{ $summary->next->describe() }}</p>
                    </div>
                </x-filament::section>

                @if ($summary->failed->isNotEmpty())
                    <x-filament::section heading="Questions échouées">
                        <div style="{{ $stack }}">
                            @foreach ($summary->failed as $failedEntry)
                                @if ($failedEntry->question !== null)
                                    <div wire:key="failed-{{ $failedEntry->id }}">
                                        <p style="font-weight: 600; white-space: pre-line;">{{ $failedEntry->question->statement }}</p>
                                        <p>Réponse attendue : {{ $failedEntry->question->expectedAnswer() }}</p>
                                        @if (filled($failedEntry->question->explanation))
                                            <p style="white-space: pre-line;">{{ $failedEntry->question->explanation }}</p>
                                        @endif
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </x-filament::section>
                @endif
            @endif

            <div style="{{ $row }}">
                <x-filament::button tag="a" :href="$this->suiteUrl()" data-shortcut="enter">Retour à la suite</x-filament::button>
            </div>
        @endif
    </div>
</x-filament-panels::page>
