<?php

namespace App\Qcm\Filament\Schemas;

use App\Qcm\Models\Question;
use Closure;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;

/**
 * Formulaire d'une question (PRD QCM UC-04), partagé par les banques et les
 * questionnaires (questions propres) : une bonne réponse, au moins une
 * mauvaise réponse (trois proposées), explications, image et module facultatifs.
 */
final class QuestionFormSchema
{
    /**
     * @param  Closure(): list<string>  $moduleNames  noms de module proposés à la saisie
     * @param  string  $moduleHelp  aide du champ module
     * @return list<Component|Field>
     */
    public static function components(Closure $moduleNames, string $moduleHelp): array
    {
        return [
            Textarea::make('statement')
                ->label('Énoncé')
                ->required()
                ->rows(3),

            FileUpload::make('image_path')
                ->label('Image (facultative)')
                ->helperText('Schéma, carte ou photo sur laquelle porte la question.')
                ->image()
                ->disk(Question::IMAGE_DISK)
                ->directory(Question::IMAGE_DIRECTORY)
                ->maxSize((int) config('qcm.image_max_kb'))
                ->validationMessages([
                    'image' => 'Le fichier doit être une image (JPEG, PNG, GIF, WebP…).',
                    'max' => 'L\'image dépasse la taille maximale de :max Ko.',
                ]),

            Group::make()
                ->relationship('correctAnswer')
                ->statePath('correctAnswer')
                ->mutateRelationshipDataBeforeCreateUsing(fn (array $data): array => [
                    ...$data,
                    'is_correct' => true,
                    'position' => 0,
                ])
                ->schema([
                    TextInput::make('text')
                        ->label('Bonne réponse')
                        ->required(),
                ]),

            Repeater::make('wrongAnswers')
                ->label('Mauvaises réponses')
                ->relationship()
                ->orderColumn('position')
                ->mutateRelationshipDataBeforeCreateUsing(fn (array $data): array => [
                    ...$data,
                    'is_correct' => false,
                ])
                ->schema([
                    TextInput::make('text')
                        ->label('Réponse')
                        ->required(),
                    Textarea::make('explanation')
                        ->label('Pourquoi cette réponse est fausse (facultatif)')
                        ->rows(2),
                ])
                ->defaultItems(3)
                ->minItems(1)
                ->addActionLabel('Ajouter une mauvaise réponse')
                ->validationMessages([
                    'min' => 'Une question doit avoir au moins une mauvaise réponse.',
                ]),

            Section::make('Compléments')
                ->schema([
                    Textarea::make('explanation')
                        ->label('Explication de la bonne réponse (facultative)')
                        ->rows(2),
                    TextInput::make('module')
                        ->label('Module (facultatif)')
                        ->helperText($moduleHelp)
                        ->maxLength(255)
                        ->datalist($moduleNames),
                ]),
        ];
    }
}
