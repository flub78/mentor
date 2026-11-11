@php
    /** @var array{file: string, valid: int, byType: array<string, int>, existing?: list<array{line: int, statement: string, reference: string, difference: string, label: string}>, errors: list<array{line: int, message: string}>, warnings: list<array{line: int, message: string}>, unknownColumns: list<string>, duplicates: list<array{line: int, statement: string, reason: string}>, imageProblems: list<array{line: int, image: string, problem: string}>, newThemes: list<string>, notices: list<string>}|null $report */
    $listStyle = 'margin: .25rem 0 0 1.25rem; list-style: disc;';
@endphp

@if (is_array($report))
    <div style="display: flex; flex-direction: column; gap: .75rem; font-size: .875rem;">
        <p>
            Fichier <strong>{{ $report['file'] }}</strong> :
            <strong>{{ $report['valid'] }}</strong> question(s) valide(s),
            <strong>{{ count($report['errors']) }}</strong> ligne(s) en erreur.
            @if ($report['byType'] !== [])
                Par type : {{ collect($report['byType'])->map(fn (int $count, string $type): string => "{$type} : {$count}")->implode(', ') }}.
            @endif
        </p>

        @if ($report['valid'] === 0)
            <x-filament::callout color="danger" icon="heroicon-o-x-circle" heading="Aucune question à importer">
                <x-slot name="description">Corrigez le fichier d'après les erreurs ci-dessous, puis recommencez.</x-slot>
            </x-filament::callout>
        @endif

        @foreach ($report['notices'] as $notice)
            <x-filament::callout color="info" icon="heroicon-o-information-circle" :heading="$notice" />
        @endforeach

        @if ($report['errors'] !== [])
            <div>
                <strong>Lignes en erreur (non importées)</strong>
                <ul style="{{ $listStyle }}">
                    @foreach ($report['errors'] as $error)
                        <li>Ligne {{ $error['line'] }} : {{ $error['message'] }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($report['warnings'] !== [])
            <div>
                <strong>Remarques (lignes importées)</strong>
                <ul style="{{ $listStyle }}">
                    @foreach ($report['warnings'] as $warning)
                        <li>Ligne {{ $warning['line'] }} : {{ $warning['message'] }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if (($report['existing'] ?? []) !== [])
            <div>
                <strong>Questions déjà présentes, reconnues par leur référence</strong>
                <ul style="{{ $listStyle }}">
                    @foreach ($report['existing'] as $existing)
                        <li>Ligne {{ $existing['line'] }} — {{ $existing['reference'] }} « {{ \Illuminate\Support\Str::limit($existing['statement'], 80) }} » : {{ $existing['label'] }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($report['duplicates'] !== [])
            <div>
                <strong>Doublons probables</strong>
                <ul style="{{ $listStyle }}">
                    @foreach ($report['duplicates'] as $duplicate)
                        <li>Ligne {{ $duplicate['line'] }} — « {{ \Illuminate\Support\Str::limit($duplicate['statement'], 80) }} » : {{ $duplicate['reason'] }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($report['imageProblems'] !== [])
            <div>
                <strong>Illustrations inutilisables (questions importées sans illustration)</strong>
                <ul style="{{ $listStyle }}">
                    @foreach ($report['imageProblems'] as $problem)
                        <li>Ligne {{ $problem['line'] }} — {{ $problem['image'] }} : {{ $problem['problem'] }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($report['newThemes'] !== [])
            <div>
                <strong>Thèmes nouveaux pour cette banque</strong> (vérifiez les fautes de frappe) :
                {{ implode(', ', $report['newThemes']) }}
            </div>
        @endif

        @if ($report['unknownColumns'] !== [])
            <div>
                <strong>Colonnes inconnues, ignorées</strong> : {{ implode(', ', $report['unknownColumns']) }}
            </div>
        @endif
    </div>
@endif
