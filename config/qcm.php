<?php

return [
    /*
     * Taille maximale d'une image de question, en kilo-octets (PRD QCM Q8).
     */
    'image_max_kb' => (int) env('QCM_IMAGE_MAX_KB', 2048),

    /*
     * Taille maximale d'un fichier d'échange importé (CSV ou ZIP), en kilo-octets
     * (PRD QCM UC-07, UC-17). Ne pas dépasser la limite des téléversements
     * temporaires de Livewire (12 Mo par défaut).
     */
    'import_max_kb' => (int) env('QCM_IMPORT_MAX_KB', 10240),
];
