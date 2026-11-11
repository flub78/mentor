<?php

namespace App\Banques\Exchange;

use RuntimeException;

/**
 * Fichier d'échange inutilisable (encodage, colonnes, archive) : le message,
 * destiné à l'utilisateur, dit ce qui est attendu (PRD Banques UC-05, 2a).
 */
final class ExchangeException extends RuntimeException
{
    /** Renvoi vers la documentation du format, ajouté aux messages d'erreur. */
    public const SEE_DOCUMENTATION = 'Le format est décrit dans le guide d\'utilisation des banques (« Format des fichiers d\'échange »).';

    public static function because(string $message): self
    {
        return new self($message.' '.self::SEE_DOCUMENTATION);
    }
}
