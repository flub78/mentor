<?php

namespace App\Leitner\Filament\Resources\Suites;

use App\Banques\Filament\Schemas\QuestionFormSchema;
use App\Leitner\Filament\Resources\Suites\Pages\CreateSuite;
use App\Leitner\Filament\Resources\Suites\Pages\EditSuite;
use App\Leitner\Filament\Resources\Suites\Pages\ListSuites;
use App\Leitner\Filament\Resources\Suites\Pages\ViewSuite;
use App\Leitner\Filament\Resources\Suites\Schemas\SuiteForm;
use App\Leitner\Filament\Resources\Suites\Tables\SuitesTable;
use App\Leitner\Models\Suite;
use App\Leitner\Suites\PersonalQuestionCreator;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Radio;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Suites de révision (PRD Leitner UC-05, UC-03).
 */
class SuiteResource extends Resource
{
    /** Placement d'une question personnelle : boîte 1 ou en banque (UC-03). */
    private const PLACE_BOX_ONE = 'boite_1';

    private const PLACE_BANK = 'banque';

    protected static ?string $model = Suite::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $modelLabel = 'suite';

    protected static ?string $pluralModelLabel = 'suites';

    protected static ?string $navigationLabel = 'Suites de révision';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return SuiteForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SuitesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSuites::route('/'),
            'create' => CreateSuite::route('/create'),
            'view' => ViewSuite::route('/{record}'),
            'edit' => EditSuite::route('/{record}/edit'),
        ];
    }

    /**
     * Répartition en une ligne : boîtes, en banque, maîtrisées, suspendues.
     */
    public static function distributionSummary(Suite $suite): string
    {
        $counts = $suite->progression->counts();
        $boxes = implode(' · ', array_map(
            fn (int $box, int $count): string => "B{$box} : {$count}",
            array_keys($counts['boxes']),
            $counts['boxes'],
        ));

        $summary = "{$boxes} — en banque : {$counts['inBank']} — maîtrisées : {$counts['mastered']}";

        return $counts['suspended'] > 0 ? "{$summary} — suspendues : {$counts['suspended']}" : $summary;
    }

    /**
     * Suppression d'une suite : sa progression est perdue, les banques ne
     * changent pas.
     */
    public static function deleteAction(): DeleteAction
    {
        return DeleteAction::make()
            ->modalHeading(fn (Suite $record): string => "Supprimer la suite « {$record->name} » ?")
            ->modalDescription(function (Suite $record): string {
                $introduced = $record->progression->items()->count();
                $text = match ($introduced) {
                    0 => 'Aucune question n\'a encore été introduite.',
                    1 => 'La progression de la question introduite sera perdue.',
                    default => "La progression des {$introduced} questions introduites sera perdue.",
                }.' Les banques sources ne sont pas modifiées.';

                return $record->personalBank === null
                    ? $text
                    : $text." Votre banque « {$record->personalBank->name} » est conservée.";
            })
            ->successNotificationTitle('Suite supprimée');
    }

    /**
     * « Nouvelle question » (UC-03) : la question va dans la banque
     * personnelle de la suite, en boîte 1 ou en banque.
     */
    public static function newQuestionAction(): Action
    {
        return Action::make('newQuestion')
            ->label('Nouvelle question')
            ->icon(Heroicon::OutlinedPlus)
            ->color('gray')
            ->modalHeading('Ajouter ma propre question')
            ->modalDescription('La question est rangée dans votre banque personnelle de la suite, créée au besoin.')
            ->modalWidth(Width::ThreeExtraLarge)
            ->schema(fn (Suite $record): array => [
                ...QuestionFormSchema::components(fn (): array => $record->personalBank?->themeNames() ?? [], bankId: fn (): ?int => $record->personalBank?->id),
                Radio::make('placement')
                    ->label('Placement')
                    ->options([
                        self::PLACE_BOX_ONE => 'Directement en boîte 1 : posée dès la prochaine séance',
                        self::PLACE_BANK => 'En banque : introduite avec les autres nouvelles questions',
                    ])
                    ->default(self::PLACE_BOX_ONE)
                    ->required(),
            ])
            ->action(function (array $data, Suite $record, mixed $livewire): void {
                $placement = $data['placement'];
                unset($data['placement']);

                app(PersonalQuestionCreator::class)->create($record, $data, $placement === self::PLACE_BOX_ONE);

                if ($livewire instanceof EditSuite) {
                    $livewire->reloadForm();
                }

                Notification::make()
                    ->title('Question ajoutée')
                    ->body($placement === self::PLACE_BOX_ONE
                        ? 'Elle est en boîte 1 et sera posée à la prochaine séance.'
                        : 'Elle est en banque et sera introduite avec les autres nouvelles questions.')
                    ->success()
                    ->send();
            });
    }
}
