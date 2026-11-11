@php
    use App\Qcm\Livewire\TakeExam;
    use App\Qcm\Models\FieldRequirement;
    use App\Qcm\Models\Question;
    use App\Qcm\Models\Result;
    use Illuminate\Support\Facades\Storage;

    $button = 'inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2 text-sm font-semibold focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-500 disabled:opacity-50';
    $primary = $button.' bg-amber-500 text-white hover:bg-amber-600';
    $secondary = $button.' border border-zinc-300 bg-white text-zinc-800 hover:bg-zinc-50';
@endphp

<div class="space-y-6">
    @if ($preview)
        <div class="rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900 print:hidden" role="status">
            <strong>Prévisualisation</strong> : l'examen tel que le candidat le verra, avec une identité fictive. Rien n'est enregistré.
        </div>
    @endif

    <header class="print:hidden">
        <p class="text-sm text-zinc-500">Examen</p>
        <h1 class="text-2xl font-semibold">{{ $exam->title }}</h1>
    </header>

    @if ($notice)
        <div class="rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800 print:hidden" role="alert">
            {{ $notice }}
        </div>
    @endif

    {{-- Accueil (UC-12, étape 1) --}}
    @if ($step === TakeExam::STEP_WELCOME)
        <section class="space-y-4 rounded-xl bg-white p-6 shadow-sm">
            @if (filled($exam->instructions))
                <div class="whitespace-pre-line">{{ $exam->instructions }}</div>
            @endif

            <ul class="space-y-1 text-zinc-700">
                <li>Nombre de questions : <strong>{{ $this->questionCount() }}</strong></li>
                <li>Durée : <strong>{{ $exam->duration_minutes ? $exam->duration_minutes.' minutes' : 'sans limite de temps' }}</strong></li>
                <li>Taux de réussite minimal : <strong>{{ Result::formatRate((float) $exam->pass_rate) }}</strong></li>
                @if ($exam->max_attempts)
                    <li>Nombre maximal de passages par candidat : <strong>{{ $exam->max_attempts }}</strong></li>
                @endif
            </ul>

            <p class="text-sm text-zinc-600">
                Vous choisissez une réponse par question et pouvez revenir sur vos réponses jusqu'à la fin.
                Une question sans réponse compte comme fausse. La correction n'est pas visible pendant l'examen.
            </p>

            <p class="rounded-lg bg-zinc-50 p-3 text-sm text-zinc-600">
                <strong>Données personnelles</strong> : les renseignements que vous allez saisir, vos réponses et votre résultat
                sont enregistrés par l'organisateur de l'examen pour établir votre certificat de résultat et pouvoir le rééditer.
            </p>

            <button type="button" wire:click="begin" class="{{ $primary }}">Commencer</button>
        </section>
    @endif

    {{-- Identification (UC-12, étape 2) --}}
    @if ($step === TakeExam::STEP_IDENTIFY)
        <form wire:submit="start" class="space-y-4 rounded-xl bg-white p-6 shadow-sm" novalidate>
            <h2 class="text-lg font-semibold">Identification</h2>
            <p class="text-sm text-zinc-600">Les champs marqués d'un astérisque sont obligatoires.</p>

            @foreach ($fieldLabels as $key => $label)
                @php
                    $required = in_array($key, ['nom', 'prenom'], true) || ($requirements[$key] ?? null) === FieldRequirement::Required->value;
                    $type = match ($key) { 'email' => 'email', 'date_naissance' => 'date', default => 'text' };
                @endphp
                <div>
                    <label for="identity-{{ $key }}" class="block text-sm font-medium">
                        {{ $label }}@if ($required) <span class="text-red-600">*</span>@endif
                    </label>
                    <input id="identity-{{ $key }}" type="{{ $type }}" wire:model="identity.{{ $key }}"
                           @if ($key === 'email') autocomplete="email" @endif
                           class="mt-1 block w-full rounded-lg border border-zinc-300 px-3 py-2 focus:border-amber-500 focus:outline-none focus:ring-1 focus:ring-amber-500 @error('identity.'.$key) border-red-500 @enderror">
                    @error('identity.'.$key)
                        <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
                    @enderror
                </div>
            @endforeach

            <div class="flex flex-wrap gap-3">
                <button type="submit" class="{{ $primary }}">
                    Démarrer l'examen{{ $exam->duration_minutes ? ' ('.$exam->duration_minutes.' min)' : '' }}
                </button>
            </div>
        </form>
    @endif

    {{-- Questions (UC-12, étapes 3 à 7) et confirmation (étape 8) --}}
    @if (in_array($step, [TakeExam::STEP_QUESTION, TakeExam::STEP_CONFIRM], true))
        @php
            $items = $this->items;
            $total = count($items);
            $current = $items[$position] ?? null;
            $remaining = $this->remainingSeconds();
            $unanswered = array_filter($items, fn (array $item): bool => $item['chosen'] === null);
            $bookmarked = array_filter($items, fn (array $item): bool => $item['bookmarked']);
        @endphp

        <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-white px-4 py-3 shadow-sm">
            <span class="text-sm text-zinc-600">
                {{ $total - count($unanswered) }} / {{ $total }} répondue(s)
            </span>
            @if ($remaining !== null)
                <div wire:key="countdown-{{ microtime(true) }}" x-data="qcmCountdown({{ $remaining }})" class="text-right">
                    <span class="text-sm text-zinc-600">Temps restant :</span>
                    <strong class="font-mono" x-text="display">{{ intdiv($remaining, 60) }} min {{ str_pad((string) ($remaining % 60), 2, '0', STR_PAD_LEFT) }} s</strong>
                    <p x-show="warning" x-text="warning" class="mt-1 text-sm font-semibold text-red-700" role="alert"></p>
                </div>
            @endif
        </div>

        @if ($step === TakeExam::STEP_QUESTION && $current)
            <section class="space-y-4 rounded-xl bg-white p-6 shadow-sm" wire:key="question-{{ $position }}">
                <div class="flex items-start justify-between gap-4">
                    <h2 class="text-sm font-medium text-zinc-500">Question {{ $position + 1 }} sur {{ $total }}</h2>
                    <button type="button" wire:click="toggleBookmark" aria-pressed="{{ $current['bookmarked'] ? 'true' : 'false' }}"
                            class="{{ $current['bookmarked'] ? $primary : $secondary }}">
                        {{ $current['bookmarked'] ? '★ Marquée' : '☆ Marquer' }}
                    </button>
                </div>

                @if ($current['question'] === null)
                    <p class="text-zinc-600">Cette question a été supprimée depuis le début du passage ; elle compte comme fausse. Passez à la suivante.</p>
                @else
                    <p class="whitespace-pre-line text-lg">{{ $current['question']->statement }}</p>

                    @if ($current['question']->image_path)
                        <img src="{{ Storage::disk(Question::IMAGE_DISK)->url($current['question']->image_path) }}"
                             alt="Illustration de la question {{ $position + 1 }}" class="max-h-80 rounded-lg border border-zinc-200">
                    @endif

                    <div class="space-y-2" role="radiogroup" aria-label="Réponses">
                        @foreach ($current['answers'] as $answer)
                            @php($selected = $current['chosen'] === $answer->key)
                            <button type="button" role="radio" aria-checked="{{ $selected ? 'true' : 'false' }}"
                                    wire:click="choose('{{ $answer->key }}')" wire:key="answer-{{ $answer->key }}"
                                    @class([
                                        'flex w-full items-start gap-3 rounded-lg border px-4 py-3 text-left',
                                        'border-amber-500 bg-amber-50 ring-1 ring-amber-500' => $selected,
                                        'border-zinc-300 hover:bg-zinc-50' => ! $selected,
                                    ])>
                                <span class="mt-0.5 font-semibold">{{ chr(65 + $loop->index) }}.</span>
                                <span class="whitespace-pre-line">{{ $answer->text }}</span>
                            </button>
                        @endforeach
                    </div>
                @endif

                <div class="flex flex-wrap justify-between gap-3 pt-2">
                    <button type="button" wire:click="previous" class="{{ $secondary }}" @disabled($position === 0)>← Précédente</button>
                    @if ($position < $total - 1)
                        <button type="button" wire:click="next" class="{{ $secondary }}">Suivante →</button>
                    @endif
                </div>
            </section>
        @endif

        @if ($step === TakeExam::STEP_CONFIRM)
            <section class="space-y-4 rounded-xl bg-white p-6 shadow-sm">
                <h2 class="text-lg font-semibold">Terminer l'examen ?</h2>
                <ul class="space-y-1">
                    <li>Questions non répondues : <strong>{{ count($unanswered) }}</strong>
                        @if ($unanswered) (n° {{ implode(', ', array_map(fn ($item) => $item['position'] + 1, $unanswered)) }}) — elles compteront comme fausses.@endif
                    </li>
                    <li>Questions marquées : <strong>{{ count($bookmarked) }}</strong>
                        @if ($bookmarked) (n° {{ implode(', ', array_map(fn ($item) => $item['position'] + 1, $bookmarked)) }})@endif
                    </li>
                </ul>
                <p class="text-sm text-zinc-600">Une fois l'examen terminé, vous ne pourrez plus modifier vos réponses.</p>
                <div class="flex flex-wrap gap-3">
                    <button type="button" wire:click="goTo({{ $position }})" class="{{ $secondary }}">Revenir aux questions</button>
                    <button type="button" wire:click="finish" class="{{ $primary }}">Confirmer et terminer</button>
                </div>
            </section>
        @else
            <div class="flex justify-end">
                <button type="button" wire:click="reviewBeforeFinish" class="{{ $primary }}">Terminer l'examen…</button>
            </div>
        @endif

        {{-- Liste des questions (UC-12, étape 7) --}}
        <nav class="rounded-xl bg-white p-4 shadow-sm" aria-label="Liste des questions">
            <h2 class="mb-3 text-sm font-medium text-zinc-600">Liste des questions</h2>
            <div class="flex flex-wrap gap-2">
                @foreach ($items as $item)
                    <button type="button" wire:click="goTo({{ $item['position'] }})" wire:key="nav-{{ $item['position'] }}"
                            title="Question {{ $item['position'] + 1 }} : {{ $item['chosen'] === null ? 'non répondue' : 'répondue' }}{{ $item['bookmarked'] ? ', marquée' : '' }}"
                            @class([
                                'relative h-10 w-10 rounded-lg border text-sm font-semibold',
                                'border-zinc-800 bg-zinc-800 text-white' => $item['chosen'] !== null,
                                'border-zinc-300 bg-white text-zinc-700' => $item['chosen'] === null,
                                'ring-2 ring-amber-500 ring-offset-1' => $step === TakeExam::STEP_QUESTION && $item['position'] === $position,
                            ])>
                        {{ $item['position'] + 1 }}
                        @if ($item['bookmarked'])
                            <span class="absolute -right-1 -top-2 text-amber-500" aria-hidden="true">★</span>
                        @endif
                    </button>
                @endforeach
            </div>
            <p class="mt-3 text-xs text-zinc-500">Fond foncé : répondue · fond clair : non répondue · ★ : marquée</p>
        </nav>
    @endif

    {{-- Certificat (UC-12, étape 9) et correction détaillée (UC-13) --}}
    @if ($step === TakeExam::STEP_DONE)
        @if ($this->result)
            @include('qcm.certificat', ['result' => $this->result])

            <div class="flex flex-wrap gap-3 print:hidden">
                <button type="button" onclick="window.print()" class="{{ $primary }}">Imprimer ou enregistrer en PDF</button>
                <button type="button" wire:click="newAttempt" class="{{ $secondary }}">Nouveau passage (candidat suivant)</button>
            </div>
        @else
            <p class="rounded-lg bg-white p-6 shadow-sm">Le résultat de ce passage est introuvable. Le formateur peut le retrouver dans la liste des résultats.</p>
        @endif

        @if ($exam->shows_correction && $this->result)
            <section class="space-y-4 print:hidden">
                <h2 class="text-lg font-semibold">Correction détaillée</h2>

                <div class="flex flex-wrap gap-2" role="group" aria-label="Filtrer la correction">
                    @foreach (TakeExam::FILTERS as $value => $label)
                        <button type="button" wire:click="$set('filter', '{{ $value }}')" aria-pressed="{{ $filter === $value ? 'true' : 'false' }}"
                                class="{{ $filter === $value ? $primary : $secondary }}">{{ $label }}</button>
                    @endforeach
                </div>

                @forelse ($this->correction as $item)
                    @php($correct = TakeExam::isCorrect($item))
                    <article class="space-y-3 rounded-xl bg-white p-6 shadow-sm" wire:key="correction-{{ $item['position'] }}">
                        <div class="flex flex-wrap items-center gap-2 text-sm">
                            <span class="font-medium text-zinc-500">Question {{ $item['position'] + 1 }}</span>
                            <span @class(['rounded px-2 py-0.5 font-semibold', 'bg-green-100 text-green-800' => $correct, 'bg-red-100 text-red-800' => ! $correct])>
                                {{ $correct ? 'Juste' : ($item['chosen'] === null ? 'Non répondue' : 'Fausse') }}
                            </span>
                            @if ($item['bookmarked'])
                                <span class="text-amber-600">★ Marquée</span>
                            @endif
                        </div>

                        @if ($item['question'] === null)
                            <p class="text-zinc-600">Question supprimée depuis le passage : sa correction n'est plus disponible.</p>
                        @else
                            <p class="whitespace-pre-line">{{ $item['question']->statement }}</p>
                            @if ($item['question']->image_path)
                                <img src="{{ Storage::disk(Question::IMAGE_DISK)->url($item['question']->image_path) }}" alt="" class="max-h-60 rounded-lg border border-zinc-200">
                            @endif
                            <ul class="space-y-2">
                                @foreach ($item['answers'] as $answer)
                                    @php($chosen = $item['chosen'] === $answer->key)
                                    <li @class([
                                        'rounded-lg border px-4 py-2',
                                        'border-green-500 bg-green-50' => $answer->is_correct,
                                        'border-red-500 bg-red-50' => $chosen && ! $answer->is_correct,
                                        'border-zinc-200' => ! $answer->is_correct && ! $chosen,
                                    ])>
                                        <span class="font-semibold">{{ chr(65 + $loop->index) }}.</span> {{ $answer->text }}
                                        @if ($answer->is_correct) <span class="text-sm font-semibold text-green-800">— bonne réponse</span>@endif
                                        @if ($chosen) <span class="text-sm font-semibold">— votre réponse</span>@endif
                                        @if ($chosen && ! $answer->is_correct && filled($answer->explanation))
                                            <p class="mt-1 text-sm text-zinc-700">{{ $answer->explanation }}</p>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                            @if (filled($item['question']->explanation))
                                <p class="rounded-lg bg-zinc-50 p-3 text-sm"><strong>Explication</strong> : {{ $item['question']->explanation }}</p>
                            @endif
                        @endif
                    </article>
                @empty
                    <p class="text-zinc-600">Aucune question dans ce filtre.</p>
                @endforelse
            </section>
        @endif
    @endif
</div>
