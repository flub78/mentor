<?php

namespace App\Leitner\Questions;

use InvalidArgumentException;

/**
 * Réponse qui ne peut pas être corrigée (saisie non numérique, choix absent…).
 * Le message, en français, dit à l'étudiant quoi corriger ; aucun verdict
 * n'est rendu.
 */
class InvalidResponse extends InvalidArgumentException {}
