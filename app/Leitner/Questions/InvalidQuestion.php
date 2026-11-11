<?php

namespace App\Leitner\Questions;

use LogicException;

/**
 * Question incohérente refusée à l'enregistrement (réponse attendue
 * invalide pour son type, changement de type). Message en français.
 */
class InvalidQuestion extends LogicException {}
