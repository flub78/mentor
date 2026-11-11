{{-- Valeur numérique : nombre avec virgule ou point, unité facultative. --}}
@php($unit = $question->payload['unit'] ?? null)

<div style="{{ $row }}">
    <div style="width: 14rem;">
        <x-filament::input.wrapper :suffix="$unit" :valid="! $errors->has('response')">
            <x-filament::input
                type="text"
                inputmode="decimal"
                autocomplete="off"
                wire:model="response"
                wire:keydown.enter="submit"
                :disabled="$answered"
                aria-label="Votre réponse"
                autofocus
            />
        </x-filament::input.wrapper>
    </div>
    @unless ($answered)
        <x-filament::button wire:click="submit" icon="heroicon-o-check">Valider</x-filament::button>
    @endunless
</div>
