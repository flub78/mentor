<?php

namespace App\Qcm\Filament\Actions;

use App\Banques\Models\Bank as CommonBank;
use App\Qcm\Exchange\QuestionnaireImporter;
use App\Qcm\Filament\Resources\Questionnaires\QuestionnaireResource;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;

/**
 * Import d'un questionnaire, ou d'un examen avec son questionnaire
 * (PRD QCM UC-17) : téléversement, rapport avant import, titres, confirmation.
 */
final class ImportQuestionnaireAction
{
    public static function make(): Action
    {
        $importer = new QuestionnaireImporter;

        return Action::make('importQuestionnaire')
            ->label('Importer')
            ->icon(Heroicon::OutlinedArrowUpTray)
            ->modalHeading('Importer un questionnaire ou un examen')
            ->modalSubmitActionLabel('Importer')
            ->steps([
                Step::make('Fichier')
                    ->description('CSV, ou ZIP avec les illustrations et les paramètres')
                    ->schema([ExchangeUpload::field([
                        'questionnaire (ZIP)' => 'questionnaire-exemple.zip',
                        'examen (ZIP)' => 'examen-exemple.zip',
                    ])])
                    ->afterValidation(function (Get $get, Set $set) use ($importer): void {
                        $file = ExchangeUpload::open($get('file'));

                        if ($file === null) {
                            throw new Halt;
                        }

                        $analysis = $importer->analyse($file);
                        $set('report', $analysis['report']->toArray());
                        $set('questionnaire_title', $analysis['questionnaire']['title']);
                        $set('has_exam', $analysis['exam'] !== null);
                        $set('exam_title', $analysis['exam']['title'] ?? null);
                    }),
                Step::make('Rapport')
                    ->description('Vérifier avant d\'importer')
                    ->schema([
                        Hidden::make('report')->dehydrated(false),
                        Hidden::make('has_exam')->dehydrated(false),
                        View::make('banques.exchange.import-report')
                            ->viewData(fn (Get $get): array => ['report' => $get('report')]),
                        Select::make('bank_id')
                            ->label('Banque où ranger les questions')
                            ->helperText('Toute question vit dans une banque ; à défaut, la banque par défaut, d\'où vous pourrez les déplacer.')
                            ->options(fn (): array => CommonBank::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->default(fn (): int => CommonBank::default()->id)
                            ->required(),
                        TextInput::make('questionnaire_title')
                            ->label('Titre du questionnaire')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('exam_title')
                            ->label('Titre de l\'examen')
                            ->required()
                            ->maxLength(255)
                            ->visible(fn (Get $get): bool => (bool) $get('has_exam')),
                        Radio::make('duplicates')
                            ->label('Doublons probables dans le fichier')
                            ->options([
                                'skip' => 'Ne pas importer les doublons',
                                'import' => 'Importer quand même',
                            ])
                            ->default('skip')
                            ->visible(fn (Get $get): bool => ($get('report')['duplicates'] ?? []) !== []),
                    ]),
            ])
            ->action(function (array $data, Action $action) use ($importer): void {
                $file = ExchangeUpload::open($data['file'] ?? null);

                if ($file === null) {
                    throw new Halt;
                }

                $result = $importer->import(
                    $file,
                    $data['questionnaire_title'] ?? null,
                    $data['exam_title'] ?? null,
                    ($data['duplicates'] ?? 'skip') !== 'import',
                    isset($data['bank_id']) ? (int) $data['bank_id'] : null,
                );

                $title = "Questionnaire « {$result['questionnaire']->title} » importé : {$result['imported']} question(s)";

                Notification::make()
                    ->success()
                    ->title($title)
                    ->body(collect([
                        "Questions rangées dans la banque « {$result['bank']->name} ».",
                        $result['exam'] === null ? null : "Examen « {$result['exam']->title} » créé.",
                        $result['skipped'] > 0 ? "{$result['skipped']} doublon(s) non importé(s)." : null,
                    ])->filter()->implode(' ') ?: null)
                    ->send();

                $action->redirect(QuestionnaireResource::getUrl('edit', ['record' => $result['questionnaire']]));
            });
    }
}
