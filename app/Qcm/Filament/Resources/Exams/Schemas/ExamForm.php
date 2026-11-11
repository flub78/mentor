<?php

namespace App\Qcm\Filament\Resources\Exams\Schemas;

use App\Qcm\Models\Exam;
use App\Qcm\Models\FieldRequirement;
use App\Qcm\Models\IdentificationField;
use App\Qcm\Models\Questionnaire;
use App\Qcm\Study\ModuleCatalog;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class ExamForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Général')
                    ->schema([
                        TextInput::make('title')
                            ->label('Titre')
                            ->required()
                            ->maxLength(255),
                        Textarea::make('instructions')
                            ->label('Consignes')
                            ->helperText('Affichées au candidat avant qu\'il commence.')
                            ->rows(3),
                        Select::make('questionnaire_id')
                            ->label('Questionnaire')
                            ->helperText('L\'examen pose toutes les questions du questionnaire.')
                            ->relationship('questionnaire', 'title')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->default(fn (): ?int => is_numeric(request()->query('questionnaire')) ? (int) request()->query('questionnaire') : null)
                            ->live(),
                        TextEntry::make('questions_summary')
                            ->label('Questions posées')
                            ->state(fn (Get $get): string => self::questionsSummary($get('questionnaire_id'))),
                    ]),
                Section::make('Réussite et durée')
                    ->columns(2)
                    ->schema([
                        TextInput::make('pass_rate')
                            ->label('Taux de réussite minimal')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->maxValue(100)
                            ->suffix('%')
                            ->required()
                            ->default((int) config('qcm.exam_defaults.pass_rate')),
                        TextInput::make('duration_minutes')
                            ->label('Durée maximale')
                            ->helperText('Laissez vide pour un passage sans chronomètre.')
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->suffix('min'),
                    ]),
                Section::make('Passage')
                    ->columns(2)
                    ->schema([
                        Toggle::make('shows_correction')
                            ->label('Correction détaillée à la fin du passage')
                            ->default((bool) config('qcm.exam_defaults.shows_correction')),
                        Toggle::make('shuffle_questions')
                            ->label('Mélanger l\'ordre des questions')
                            ->default((bool) config('qcm.exam_defaults.shuffle_questions')),
                        TextInput::make('max_attempts')
                            ->label('Nombre maximal de passages par candidat')
                            ->helperText('Laissez vide pour ne pas limiter.')
                            ->numeric()
                            ->integer()
                            ->minValue(1),
                    ]),
                Section::make('Identification du candidat')
                    ->description('Renseignements demandés avant de commencer.')
                    ->columns(2)
                    ->schema([
                        Text::make('Nom et prénom : toujours demandés, obligatoires.')->columnSpanFull(),
                        ...array_map(
                            fn (IdentificationField $field): Select => Select::make("identification_fields.{$field->value}")
                                ->label($field->label())
                                ->options(FieldRequirement::options())
                                ->default(FieldRequirement::NotAsked->value)
                                ->selectablePlaceholder(false)
                                ->required()
                                ->live(),
                            IdentificationField::cases(),
                        ),
                        TextInput::make('identifier_label')
                            ->label('Libellé de l\'identifiant libre')
                            ->placeholder('Numéro de licence')
                            ->maxLength(255)
                            ->required(fn (Get $get): bool => self::asksIdentifier($get))
                            ->visible(fn (Get $get): bool => self::asksIdentifier($get))
                            ->validationMessages(['required' => 'Donnez un libellé à l\'identifiant libre (par exemple « Numéro de licence »).']),
                    ]),
            ])
            ->columns(1);
    }

    private static function asksIdentifier(Get $get): bool
    {
        return ($get('identification_fields.'.IdentificationField::Identifier->value) ?? FieldRequirement::NotAsked->value) !== FieldRequirement::NotAsked->value;
    }

    /**
     * Nombre de questions posées, au total et par module (PRD QCM UC-20, étape 2).
     */
    private static function questionsSummary(mixed $questionnaireId): string
    {
        $questionnaire = is_numeric($questionnaireId) ? Questionnaire::find((int) $questionnaireId) : null;

        if ($questionnaire === null) {
            return 'Choisissez un questionnaire.';
        }

        $modules = (new ModuleCatalog)->forQuestionnaire($questionnaire);
        $total = array_sum(array_column($modules, 'count'));

        if ($total === 0) {
            return 'Ce questionnaire ne contient encore aucune question.';
        }

        return "{$total} question(s) : ".implode(', ', array_map(
            fn (array $module): string => "{$module['label']} ({$module['count']})",
            $modules,
        )).'.';
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalize(array $data): array
    {
        $fields = Exam::defaultIdentificationFields();

        foreach ((array) ($data['identification_fields'] ?? []) as $field => $requirement) {
            if (array_key_exists($field, $fields) && FieldRequirement::tryFrom((string) $requirement) !== null) {
                $fields[$field] = (string) $requirement;
            }
        }

        $data['identification_fields'] = $fields;

        if ($fields[IdentificationField::Identifier->value] === FieldRequirement::NotAsked->value) {
            $data['identifier_label'] = null;
        }

        return $data;
    }
}
