<?php

namespace App\Banques\Filament\Schemas;

use App\Banques\Models\Question;
use App\Banques\Questions\Difficulty;
use App\Banques\Questions\QuestionType;
use Closure;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;

/**
 * Formulaire d'une question (PRD Banques UC-02) : champs communs,
 * puis champs de la réponse attendue propres au type choisi.
 */
final class QuestionFormSchema
{
    /**
     * @param  Closure(): list<string>  $themeNames  thèmes proposés à la saisie
     * @param  list<QuestionType>|null  $types  types proposés ; null = tous (un module qui n'en pose que certains les restreint)
     * @return list<Component|Field>
     */
    public static function components(Closure $themeNames, ?array $types = null): array
    {
        $types ??= QuestionType::cases();
        $typeSections = array_map(
            fn (QuestionType $type): Group => Group::make($type->handler()->formSchema())
                ->visible(fn (Get $get): bool => self::selectedType($get) === $type),
            $types,
        );

        return [
            Select::make('type')
                ->label('Type de question')
                ->options(collect($types)->mapWithKeys(fn (QuestionType $type): array => [$type->value => $type->getLabel()])->all())
                ->default(QuestionType::Qcm->value)
                ->selectablePlaceholder(false)
                ->required()
                ->live()
                ->disabledOn('edit')
                ->helperText(fn (string $operation): ?string => $operation === 'edit'
                    ? 'Le type ne peut plus être changé : créez une nouvelle question pour un autre type.'
                    : null)
                ->validationMessages(['required' => 'Choisissez le type de la question.']),

            Textarea::make('statement')
                ->label('Énoncé')
                ->required()
                ->rows(3)
                ->validationMessages(['required' => 'L\'énoncé est obligatoire.']),

            FileUpload::make('image_path')
                ->label('Image (facultative)')
                ->helperText('Photo, carte ou schéma sur lequel porte la question (« Quel est ce monument ? »).')
                ->image()
                ->disk(Question::IMAGE_DISK)
                ->directory(Question::IMAGE_DIRECTORY)
                ->maxSize((int) config('banques.image_max_kb'))
                ->live()
                ->validationMessages([
                    'image' => 'Le fichier doit être une image (JPEG, PNG, GIF, WebP…).',
                    'max' => 'L\'image dépasse la taille maximale de :max Ko.',
                ]),

            TextInput::make('image_alt')
                ->label('Description de l\'image')
                ->helperText('Lue par les lecteurs d\'écran ; obligatoire quand une image est fournie.')
                ->maxLength(255)
                ->visible(fn (Get $get): bool => filled($get('image_path')))
                ->required()
                ->validationMessages(['required' => 'Décrivez l\'image en quelques mots (texte alternatif, pour l\'accessibilité).']),

            ...$typeSections,

            Section::make('Compléments')
                ->schema([
                    Textarea::make('explanation')
                        ->label('Explication (facultative)')
                        ->helperText('Affichée après la réponse.')
                        ->rows(2),
                    Select::make('difficulty')
                        ->label('Difficulté')
                        ->options(Difficulty::class)
                        ->placeholder('Non précisée (comptée comme moyenne)')
                        ->helperText('Les nouvelles questions sont introduites des plus faciles aux plus difficiles.'),
                    TextInput::make('theme')
                        ->label('Thème (facultatif)')
                        ->helperText('Reprenez un thème déjà utilisé dans la banque pour regrouper les questions.')
                        ->maxLength(255)
                        ->datalist($themeNames),
                ]),
        ];
    }

    private static function selectedType(Get $get): ?QuestionType
    {
        $type = $get('type');

        return $type instanceof QuestionType ? $type : QuestionType::tryFrom((string) $type);
    }
}
