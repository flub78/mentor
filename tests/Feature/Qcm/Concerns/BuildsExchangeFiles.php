<?php

namespace Tests\Feature\Qcm\Concerns;

use ZipArchive;

/**
 * Fichiers d'échange de test, créés sous le répertoire temporaire du système
 * et supprimés après chaque test.
 */
trait BuildsExchangeFiles
{
    /** Image PNG de 1 × 1 pixel. */
    protected const PNG = "\x89PNG\r\n\x1a\n\x00\x00\x00\rIHDR\x00\x00\x00\x01\x00\x00\x00\x01\x08\x04\x00\x00\x00\xb5\x1c\x0c\x02\x00\x00\x00\x0bIDATx\xdac\xfc\xff\x1f\x00\x03\x03\x02\x00\xef\xa2\xa7[\x00\x00\x00\x00IEND\xaeB`\x82";

    /** @var list<string> */
    private array $exchangeFiles = [];

    /**
     * @param  array<string, string>  $entries  nom dans l'archive → contenu
     */
    protected function zipFile(array $entries): string
    {
        $path = $this->exchangePath('zip');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($entries as $name => $contents) {
            $zip->addFromString($name, $contents);
        }

        $zip->close();

        return $path;
    }

    protected function csvFile(string $contents): string
    {
        $path = $this->exchangePath('csv');
        file_put_contents($path, $contents);

        return $path;
    }

    private function exchangePath(string $extension): string
    {
        $path = sys_get_temp_dir().'/'.uniqid('qcm-test-', true).'.'.$extension;
        $this->exchangeFiles[] = $path;

        return $path;
    }

    protected function tearDown(): void
    {
        foreach ($this->exchangeFiles as $path) {
            @unlink($path);
        }

        parent::tearDown();
    }
}
