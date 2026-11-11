@php
    // État d'une suite (PRD Leitner UC-11).
    $state = $this->state();
    $counts = $state->counts;
    $max = max(1, ...array_values($counts['boxes']));
    $stack = 'display: flex; flex-direction: column; gap: 1rem;';
    $row = 'display: flex; flex-wrap: wrap; align-items: center; gap: .75rem;';
@endphp

<x-filament-panels::page>
    @if ($state->regularity->warning)
        <x-filament::callout color="warning" icon="heroicon-o-exclamation-triangle" heading="Rythme insuffisant">
            <x-slot name="description">{{ $state->regularity->message() }}</x-slot>
        </x-filament::callout>
    @endif

    @if ($state->currentSession !== null)
        @php($position = $state->currentSession->position())
        <x-filament::callout color="info" icon="heroicon-o-play-circle" heading="Séance en cours">
            <x-slot name="description">{{ $position['done'] }} question(s) traitée(s) sur {{ $position['total'] }} : reprenez-la avec « Reprendre la séance ».</x-slot>
        </x-filament::callout>
    @endif

    <x-filament::section heading="Boîtes">
        <div style="{{ $stack }} gap: .5rem;">
            @foreach ($counts['boxes'] as $box => $count)
                <div style="{{ $row }} gap: .5rem;" wire:key="box-{{ $box }}">
                    <span style="width: 5rem; font-weight: 600;">Boîte {{ $box }}</span>
                    <div style="flex: 1; min-width: 6rem; background: rgba(127,127,127,.15); border-radius: .25rem; height: 1rem;"
                         role="img" aria-label="Boîte {{ $box }} : {{ $count }} question(s)">
                        <div style="width: {{ round(100 * $count / $max) }}%; height: 100%; border-radius: .25rem; background: rgb(var(--primary-500, 20 184 166));"></div>
                    </div>
                    <span style="width: 3rem; text-align: right;">{{ $count }}</span>
                </div>
            @endforeach
        </div>
    </x-filament::section>

    <x-filament::section heading="Répartition">
        <div style="{{ $stack }} gap: .5rem;">
            <p>
                En banque (jamais posées) : <strong>{{ $counts['inBank'] }}</strong>
                @if ($state->inBankByDifficulty !== [])
                    — {{ collect($state->inBankByDifficulty)->map(fn (int $count, string $label): string => "{$label} : {$count}")->implode(', ') }}
                @endif
            </p>
            <p>Dans les boîtes : <strong>{{ array_sum($counts['boxes']) }}</strong> · Maîtrisées : <strong>{{ $counts['mastered'] }}</strong> · Suspendues : <strong>{{ $counts['suspended'] }}</strong></p>
            <p>Total : <strong>{{ $counts['total'] }}</strong> question(s) = {{ $counts['inBank'] }} en banque + {{ array_sum($counts['boxes']) }} dans les boîtes + {{ $counts['mastered'] }} maîtrisée(s) + {{ $counts['suspended'] }} suspendue(s).</p>
        </div>
    </x-filament::section>

    <x-filament::section heading="Prochaine séance">
        <div style="{{ $stack }} gap: .5rem;">
            <p>{{ $state->next->describe() }}</p>
            <p>
                Dernière séance :
                {{ $state->lastSessionAt?->translatedFormat('l j F Y à H:i') ?? 'aucune pour l\'instant' }}.
                {{ $state->regularity->sessions }} séance(s) sur les {{ $state->regularity->windowDays }} derniers jours.
            </p>
        </div>
    </x-filament::section>
</x-filament-panels::page>
