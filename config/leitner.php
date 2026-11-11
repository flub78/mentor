<?php

return [
    /*
     * Taille maximale d'une image de question, en kilo-octets (PRD Leitner
     * UC-02, Q18).
     */
    'image_max_kb' => (int) env('LEITNER_IMAGE_MAX_KB', 2048),

    /*
     * Paramètres proposés à la création d'une suite (PRD Leitner UC-05).
     * Ordre de présentation : « melange », « boite_un_d_abord » ou
     * « boites_hautes_d_abord ».
     */
    'defaults' => [
        'box_count' => (int) env('LEITNER_BOX_COUNT', 5),
        'min_box_one' => (int) env('LEITNER_MIN_BOX_ONE', 10),
        'presentation_order' => env('LEITNER_PRESENTATION_ORDER', 'melange'),
        'retry_errors' => true,
    ],

    /*
     * Avertissement de régularité (PRD Leitner §7.5) : affiché quand une suite
     * compte moins de « min_sessions » séances sur les « window_days » derniers
     * jours.
     */
    'regularity' => [
        'window_days' => (int) env('LEITNER_REGULARITY_WINDOW_DAYS', 7),
        'min_sessions' => (int) env('LEITNER_REGULARITY_MIN_SESSIONS', 3),
    ],
];
