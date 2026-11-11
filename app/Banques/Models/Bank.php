<?php

namespace App\Banques\Models;

use Database\Factories\Banques\BankFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;
use Laraskel\Users\Models\Concerns\TracksAuthor;

/**
 * Banque de questions (PRD Banques §3, UC-01), partagée par les modules QCM
 * et Leitner, qui ne la modifient jamais en l'utilisant. La banque par défaut
 * recueille les questions créées sans banque choisie ; elle ne peut pas être
 * supprimée.
 *
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property bool $is_default
 */
#[UseFactory(BankFactory::class)]
class Bank extends Model
{
    /** @use HasFactory<BankFactory> */
    use HasFactory, TracksAuthor;

    /** Nom donné à la banque par défaut quand elle est créée. */
    public const DEFAULT_NAME = 'Banque par défaut';

    protected $table = 'banques_banks';

    protected $fillable = ['name', 'description', 'is_default'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saving(function (Bank $bank): void {
            if ($bank->is_default && self::query()->where('is_default', true)->whereKeyNot($bank->getKey())->exists()) {
                throw new InvalidArgumentException('Il ne peut y avoir qu\'une seule banque par défaut.');
            }
        });

        // Suppression question par question pour que chacune efface son image.
        static::deleting(function (Bank $bank): void {
            if ($bank->is_default) {
                throw new InvalidArgumentException("La banque par défaut « {$bank->name} » ne peut pas être supprimée : déplacez ses questions vers d'autres banques si vous voulez la vider.");
            }
            $bank->questions()->each(fn (Question $question) => $question->delete());
        });
    }

    /**
     * @return HasMany<covariant Question, $this>
     */
    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }

    /**
     * Thèmes déjà utilisés dans la banque, sans doublon et triés, proposés à
     * la saisie d'une question (PRD Banques §3).
     *
     * @return list<string>
     */
    public function themeNames(): array
    {
        return $this->questions()
            ->whereNotNull('theme')
            ->distinct()
            ->orderBy('theme')
            ->pluck('theme')
            ->values()
            ->all();
    }

    /**
     * Banque par défaut (PRD Banques §3), créée par la migration du lot B1
     * et recréée au besoin (une banque qui porte déjà son nom le devient).
     */
    public static function default(): self
    {
        $default = self::query()->where('is_default', true)->first();

        if ($default !== null) {
            return $default;
        }

        $bank = self::query()->firstOrNew(['name' => self::DEFAULT_NAME]);
        $bank->is_default = true;
        $bank->save();

        return $bank;
    }
}
