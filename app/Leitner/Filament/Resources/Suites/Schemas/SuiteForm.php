<?php

namespace App\Leitner\Filament\Resources\Suites\Schemas;

use App\Leitner\Models\Bank;
use App\Leitner\Models\Suite;
use App\Leitner\Suites\MasteryEstimate;
use App\Leitner\Suites\PresentationOrder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

/**
 * Formulaire d'une suite (PRD Leitner UC-05) : nom, banques sources et
 * paramètres, pré-remplis depuis `config/leitner.php`.
 */
class SuiteForm
{
    public static function configure(Schema $schema): Schema
    {
        $defaults = (array) config('leitner.defaults');
        $boxOptions = [];

        for ($boxes = Suite::MIN_BOXES; $boxes <= Suite::MAX_BOXES; $boxes++) {
            $boxOptions[$boxes] = "{$boxes} boîtes";
        }

        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nom')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true)
                    ->validationMessages([
                        'required' => 'Le nom de la suite est obligatoire.',
                        'unique' => 'Une suite porte déjà ce nom : choisissez-en un autre.',
                    ]),
                Textarea::make('description')
                    ->label('Description')
                    ->rows(2),
                Select::make('bank_ids')
                    ->label('Banques sources')
                    ->helperText('Une suite peut réunir plusieurs banques, par exemple une par matière. Vous pourrez aussi y ajouter vos propres questions.')
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->live()
                    ->options(fn (): array => Bank::query()->orderBy('name')->pluck('name', 'id')->all()),

                Section::make('Paramètres')
                    ->columns(2)
                    ->schema([
                        Select::make('box_count')
                            ->label('Nombre de boîtes')
                            ->options($boxOptions)
                            ->default((int) $defaults['box_count'])
                            ->selectablePlaceholder(false)
                            ->required()
                            ->live()
                            ->helperText(fn (Get $get): string => MasteryEstimate::describe((int) ($get('box_count') ?: $defaults['box_count'])))
                            ->columnSpanFull(),
                        TextInput::make('min_box_one')
                            ->label('Questions minimum en boîte 1')
                            ->helperText('En dessous, de nouvelles questions sont introduites au début de chaque séance.')
                            ->integer()
                            ->minValue(1)
                            ->maxValue(500)
                            ->default((int) $defaults['min_box_one'])
                            ->required()
                            ->validationMessages([
                                'required' => 'Indiquez le nombre minimal de questions en boîte 1.',
                                'integer' => 'Le nombre minimal de questions en boîte 1 doit être un nombre entier.',
                                'min' => 'Il faut au moins 1 question en boîte 1.',
                                'max' => 'Le nombre minimal de questions en boîte 1 ne peut pas dépasser 500.',
                            ]),
                        Select::make('presentation_order')
                            ->label('Ordre de présentation')
                            ->options(PresentationOrder::class)
                            ->default((string) $defaults['presentation_order'])
                            ->selectablePlaceholder(false)
                            ->required(),
                        Toggle::make('retry_errors')
                            ->label('Reposer les erreurs en fin de séance')
                            ->helperText('Une question échouée est reposée jusqu\'à être réussie une fois ; seule la première réponse compte pour sa boîte.')
                            ->default((bool) $defaults['retry_errors']),
                    ]),
            ])
            ->columns(1);
    }
}
