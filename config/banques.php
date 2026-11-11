<?php

return [
    /*
     * Taille maximale d'une image de question, en kilo-octets (PRD Banques
     * UC-02, Q2).
     */
    'image_max_kb' => (int) env('BANQUES_IMAGE_MAX_KB', 2048),
];
