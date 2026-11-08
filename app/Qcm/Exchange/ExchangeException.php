<?php

namespace App\Qcm\Exchange;

use RuntimeException;

/**
 * Fichier d'échange inutilisable (encodage, colonnes, archive) : le message,
 * destiné à l'utilisateur, dit ce qui est attendu (PRD QCM UC-07, 2a).
 */
final class ExchangeException extends RuntimeException
{
    /** Renvoi vers la documentation du format, ajouté aux messages d'erreur. */
    public const SEE_DOCUMENTATION = 'Le format est décrit dans le guide d\'utilisation du module QCM (« Format des fichiers d\'échange »).';

    public static function because(string $message): self
    {
        return new self($message.' '.self::SEE_DOCUMENTATION);
    }
}
