<?php

namespace App\Qcm\Study;

use App\Qcm\Models\Question;

/**
 * Modules disponibles dans un ensemble de banques (PRD QCM UC-11).
 *
 * Les modules de même nom sont regroupés sans tenir compte des majuscules
 * (PRD QCM UC-08) ; le libellé affiché est la forme la plus fréquente. Les
 * questions sans module forment le groupe « Sans module ».
 */
final class ModuleCatalog
{
    /** Clé du groupe des questions sans module. */
    public const NO_MODULE_KEY = '';

    public const NO_MODULE_LABEL = 'Sans module';

    /**
     * Clé de regroupement d'un nom de module.
     */
    public static function key(?string $module): string
    {
        return $module === null ? self::NO_MODULE_KEY : mb_strtolower(trim($module));
    }

    /**
     * @param  list<int>  $bankIds
     * @return list<array{key: string, label: string, count: int}> modules triés par libellé, « Sans module » en dernier
     */
    public function forBanks(array $bankIds): array
    {
        if ($bankIds === []) {
            return [];
        }

        /** @var array<string, array{count: int, spellings: array<string, int>}> $groups */
        $groups = [];

        $rows = Question::query()
            ->whereIn('bank_id', $bankIds)
            ->selectRaw('module, count(*) as total')
            ->groupBy('module')
            ->get();

        foreach ($rows as $row) {
            /** @var string|null $module */
            $module = $row->getAttribute('module');
            $total = (int) $row->getAttribute('total');
            $key = self::key($module);

            $groups[$key] ??= ['count' => 0, 'spellings' => []];
            $groups[$key]['count'] += $total;
            $spelling = $module ?? self::NO_MODULE_LABEL;
            $groups[$key]['spellings'][$spelling] = ($groups[$key]['spellings'][$spelling] ?? 0) + $total;
        }

        $modules = [];

        foreach ($groups as $key => $group) {
            $spellings = $group['spellings'];
            // Forme la plus fréquente ; à égalité, ordre alphabétique pour rester stable.
            uksort($spellings, fn (string $a, string $b): int => [$spellings[$b], $a] <=> [$spellings[$a], $b]);

            $modules[] = ['key' => (string) $key, 'label' => (string) array_key_first($spellings), 'count' => $group['count']];
        }

        usort($modules, fn (array $a, array $b): int => [$a['key'] === self::NO_MODULE_KEY, mb_strtolower($a['label'])]
            <=> [$b['key'] === self::NO_MODULE_KEY, mb_strtolower($b['label'])]);

        return $modules;
    }
}
