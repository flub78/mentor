<?php

namespace App\Leitner\Sessions;

use RuntimeException;

/**
 * Action refusée sur une séance (réponse en double, séance terminée…). Le
 * message, en français, dit à l'étudiant ce qui ne va pas.
 */
class SessionException extends RuntimeException {}
