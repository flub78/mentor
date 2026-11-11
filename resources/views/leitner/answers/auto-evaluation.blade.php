{{-- Auto-évaluation : « Voir la réponse », puis l'étudiant dit s'il savait. --}}
@if ($answered || $page->revealed)
    <div style="padding: .75rem 1rem; border-left: 4px solid rgba(127,127,127,.4); white-space: pre-line;">{{ $question->expectedAnswer() }}</div>
@endif

@unless ($answered)
    <div style="{{ $row }}">
        @if (! $page->revealed)
            <x-filament::button wire:click="reveal" icon="heroicon-o-eye" size="lg" data-shortcut="espace">Voir la réponse</x-filament::button>
        @else
            <x-filament::button wire:click="selfAssess(true)" color="success" size="lg" data-shortcut="o">Je savais</x-filament::button>
            <x-filament::button wire:click="selfAssess(false)" color="danger" size="lg" data-shortcut="n">Je ne savais pas</x-filament::button>
        @endif
    </div>
@endunless
