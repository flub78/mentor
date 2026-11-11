{{-- Saisie d'une question QCM : une proposition à choisir, puis « Valider ».
     La page ne connaît que le rang de chaque proposition, jamais sa clé :
     elle ne révèle pas la bonne réponse avant la correction. --}}
@php($options = $page->qcmOptions($entry, $question))
@php($given = $entry->response)

<div style="{{ $stack }} gap: .5rem;" role="radiogroup" aria-label="Réponses proposées">
    @foreach ($options as $key => $text)
        @php($isSelected = $answered ? $given === $key : $page->response === (string) $loop->index)
        @php($isCorrect = $key === \App\Banques\Questions\Handlers\QcmHandler::CORRECT_KEY)
        <x-filament::button
            wire:key="option-{{ $entry->id }}-{{ $loop->index }}"
            wire:click="$set('response', '{{ $loop->index }}')"
            :color="$answered ? ($isCorrect ? 'success' : ($isSelected ? 'danger' : 'gray')) : ($isSelected ? 'primary' : 'gray')"
            :outlined="! $isSelected && ! ($answered && $isCorrect)"
            :disabled="$answered"
            size="lg"
            data-shortcut="{{ $loop->iteration <= 9 ? $loop->iteration : '' }}"
            role="radio"
            aria-checked="{{ $isSelected ? 'true' : 'false' }}"
            style="justify-content: flex-start; text-align: left; white-space: normal;"
        >
            <span style="opacity: .6; margin-right: .5rem;">{{ $loop->iteration }}</span>{{ $text }}
        </x-filament::button>
    @endforeach
</div>

@unless ($answered)
    <div style="{{ $row }}">
        <x-filament::button wire:click="submit" icon="heroicon-o-check" data-shortcut="enter">Valider</x-filament::button>
    </div>
@endunless
