{{-- Saisie d'une question vrai / faux : la réponse est validée au clic. --}}
@php($given = $entry->response)

<div style="{{ $row }}">
    @foreach (['vrai' => ['Vrai', 'v', true], 'faux' => ['Faux', 'f', false]] as $value => [$label, $shortcut, $boolean])
        <x-filament::button
            wire:key="tf-{{ $entry->id }}-{{ $value }}"
            wire:click="answer('{{ $value }}')"
            :color="$answered ? ($given === $boolean ? ($entry->verdict === \App\Banques\Questions\Verdict::Juste ? 'success' : 'danger') : 'gray') : 'primary'"
            :outlined="$answered && $given !== $boolean"
            :disabled="$answered"
            size="xl"
            data-shortcut="{{ $shortcut }}"
            style="min-width: 8rem;"
        >
            {{ $label }}
        </x-filament::button>
    @endforeach
</div>
