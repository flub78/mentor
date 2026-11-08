<?php

namespace App\Qcm\Filament\Actions;

use App\Qcm\Exchange\ExchangeException;
use App\Qcm\Exchange\ExchangeFile;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Illuminate\Support\Arr;
use Illuminate\Support\HtmlString;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Téléversement d'un fichier d'échange dans un assistant d'import : le fichier
 * reste dans le stockage temporaire de Livewire, il est analysé à la fin de la
 * première étape et analysé de nouveau à la confirmation.
 */
final class ExchangeUpload
{
    /**
     * @param  array<string, string>  $examples  libellé → nom du fichier exemple dans public/exemples/qcm
     */
    public static function field(array $examples = []): FileUpload
    {
        $maxKb = (int) config('qcm.import_max_kb');
        $links = collect($examples)
            ->map(fn (string $file, string $label): string => '<a href="'.e(asset('exemples/qcm/'.$file)).'" style="text-decoration: underline;" download>'.e($label).'</a>')
            ->implode(', ');

        return FileUpload::make('file')
            ->label('Fichier CSV ou archive ZIP')
            ->helperText(new HtmlString(
                'Format décrit dans le guide d\'utilisation (« Format des fichiers d\'échange »). Taille maximale : '.round($maxKb / 1024, 1).' Mo.'
                .($links === '' ? '' : ' Exemples : '.$links.'.'),
            ))
            ->storeFiles(false)
            ->maxSize($maxKb)
            ->required()
            ->validationMessages([
                'max' => "Le fichier dépasse la taille maximale de {$maxKb} Ko.",
                'required' => 'Choisissez le fichier à importer.',
            ]);
    }

    /**
     * Ouvre le fichier téléversé ; en cas de fichier inutilisable, affiche le
     * motif et renvoie null.
     */
    public static function open(mixed $state): ?ExchangeFile
    {
        $upload = Arr::first(Arr::wrap($state));

        if (! $upload instanceof TemporaryUploadedFile) {
            Notification::make()->danger()->title('Aucun fichier reçu')->body('Choisissez de nouveau le fichier à importer.')->send();

            return null;
        }

        try {
            return ExchangeFile::open($upload->getRealPath(), $upload->getClientOriginalName());
        } catch (ExchangeException $exception) {
            Notification::make()->danger()->title('Fichier refusé')->body($exception->getMessage())->persistent()->send();

            return null;
        }
    }
}
