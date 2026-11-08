<?php

namespace App\Models;

use Laraskel\Users\Models\User as BaseUser;

/**
 * Utilisateur de Mentor.
 *
 * Attributs, rôles, contrat Filament et cycle de vie viennent de la brique
 * laraskel-users ; les relations propres à Mentor s'ajoutent ici.
 */
class User extends BaseUser {}
