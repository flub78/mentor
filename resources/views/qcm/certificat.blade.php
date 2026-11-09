{{--
    Certificat de résultat (PRD QCM UC-12, UC-19) : lu uniquement dans le
    résultat enregistré, sans aucune question. Une page A4, noir et blanc.

    @param \App\Qcm\Models\Result $result
    @param \Carbon\CarbonInterface|null $reissuedAt  date de réédition (UC-19)
--}}
@php
    use App\Qcm\Models\Result;
    $reissuedAt ??= null;
@endphp

<article class="certificate rounded-xl border border-zinc-300 bg-white p-6 shadow-sm sm:p-10 print:rounded-none print:border-2 print:border-black print:p-8 print:text-black print:shadow-none">
    <header class="border-b border-zinc-300 pb-4 text-center print:border-black">
        <p class="text-sm uppercase tracking-widest text-zinc-500 print:text-black">Certificat de résultat</p>
        <h2 class="mt-2 text-2xl font-semibold">{{ $result->exam_title }}</h2>
        <p class="mt-1 text-zinc-600 print:text-black">
            Questionnaire « {{ $result->questionnaire_title }} »@if ($result->questionnaire_version), version {{ $result->questionnaire_version }}@endif
        </p>
    </header>

    <dl class="mt-6 grid grid-cols-1 gap-x-6 gap-y-2 sm:grid-cols-[auto_1fr] print:grid-cols-[auto_1fr]">
        @foreach ($result->identity as $line)
            <dt class="font-medium text-zinc-600 print:text-black">{{ $line['label'] }}</dt>
            <dd>{{ $line['value'] }}</dd>
        @endforeach
        <dt class="font-medium text-zinc-600 print:text-black">Date du passage</dt>
        <dd>{{ $result->taken_at->format('d/m/Y à H:i') }}</dd>
    </dl>

    <table class="mt-6 w-full border-collapse text-left">
        <thead>
            <tr class="border-b border-zinc-300 print:border-black">
                <th class="py-2 font-medium">Module</th>
                <th class="py-2 text-right font-medium">Bonnes réponses</th>
                <th class="py-2 text-right font-medium">Taux</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($result->module_rates as $module)
                <tr class="border-b border-zinc-200 print:border-zinc-500">
                    <td class="py-1.5">{{ $module['label'] }}</td>
                    <td class="py-1.5 text-right">{{ $module['correct'] }} / {{ $module['total'] }}</td>
                    <td class="py-1.5 text-right">{{ Result::formatRate((float) $module['rate']) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="font-semibold">
                <td class="pt-3" colspan="2">Taux global</td>
                <td class="pt-3 text-right">{{ Result::formatRate($result->global_rate) }}</td>
            </tr>
            <tr>
                <td class="py-1" colspan="2">Taux de réussite minimal</td>
                <td class="py-1 text-right">{{ Result::formatRate((float) $result->pass_rate) }}</td>
            </tr>
        </tfoot>
    </table>

    <p @class([
        'mt-6 rounded-lg border-2 py-3 text-center text-xl font-bold uppercase tracking-wide print:border-black print:text-black',
        'border-green-600 text-green-700' => $result->passed,
        'border-red-600 text-red-700' => ! $result->passed,
    ])>
        {{ $result->passed ? 'Réussi' : 'Non réussi' }}
    </p>

    <footer class="mt-6 space-y-1 border-t border-zinc-300 pt-4 text-sm text-zinc-600 print:border-black print:text-black">
        <p>Numéro de certificat : <strong class="font-mono text-base text-zinc-900 print:text-black">{{ $result->certificate_number }}</strong></p>
        @if ($reissuedAt)
            <p>Réédition du {{ $reissuedAt->format('d/m/Y') }}.</p>
        @endif
        <p>Ce certificat atteste d'un passage dans l'application {{ config('app.name') }} ; il n'a pas de valeur réglementaire.</p>
    </footer>
</article>
