<?php

namespace App\Qcm\Models;

use Carbon\CarbonInterface;
use Database\Factories\Qcm\ResultFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laraskel\Users\Models\Concerns\TracksAuthor;

/**
 * Résultat d'un passage terminé (PRD QCM §3, UC-12). Ligne autonome : elle
 * garde les titres et la version du moment et n'est jamais recalculée, même
 * si les questions, le questionnaire ou l'examen changent ou disparaissent ;
 * le certificat ne lit qu'elle (plan QCM §6.2).
 *
 * @property int $id
 * @property int|null $attempt_id
 * @property string $certificate_number
 * @property list<array{key: string, label: string, value: string}> $identity renseignements saisis, avec leur libellé du moment
 * @property string $last_name
 * @property string $first_name
 * @property string|null $email
 * @property CarbonInterface $taken_at
 * @property string $exam_title
 * @property string $questionnaire_title
 * @property string|null $questionnaire_version
 * @property list<array{label: string, correct: int, total: int, rate: float}> $module_rates
 * @property float $global_rate
 * @property int $pass_rate
 * @property bool $passed
 * @property-read Attempt|null $attempt
 */
#[UseFactory(ResultFactory::class)]
class Result extends Model
{
    /** @use HasFactory<ResultFactory> */
    use HasFactory, TracksAuthor;

    protected $table = 'qcm_results';

    protected $fillable = [
        'attempt_id', 'certificate_number', 'identity', 'last_name', 'first_name', 'email', 'taken_at',
        'exam_title', 'questionnaire_title', 'questionnaire_version', 'module_rates', 'global_rate', 'pass_rate', 'passed',
    ];

    protected function casts(): array
    {
        return [
            'identity' => 'array',
            'taken_at' => 'datetime',
            'module_rates' => 'array',
            'global_rate' => 'float',
            'pass_rate' => 'integer',
            'passed' => 'boolean',
        ];
    }

    /**
     * Taux en pourcentage, à la française : « 66,67 % », « 75 % ».
     */
    public static function formatRate(float $rate): string
    {
        $formatted = number_format($rate, 2, ',', ' ');

        return (str_ends_with($formatted, ',00') ? substr($formatted, 0, -3) : rtrim($formatted, '0')).' %';
    }

    /**
     * @return BelongsTo<Attempt, $this>
     */
    public function attempt(): BelongsTo
    {
        return $this->belongsTo(Attempt::class);
    }
}
