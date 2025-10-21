<?php

/**
 * Fichier de configuration pour l'API Kelio
 *
 * Copiez ce fichier en config.php et modifiez les valeurs selon votre configuration
 */

return [
    /**
     * URL de votre instance Kelio
     * Exemple : https://entreprise.kelio.io
     */
    'kelio_url' => 'https://your-company.kelio.io',

    /**
     * Durée de la pause en minutes
     * Cette durée est ajoutée aux heures payées lorsque les conditions sont remplies
     */
    'pause_time' => 7,

    /**
     * Heure de début de journée (format : heures * 60 + minutes)
     * Les badgeages avant cette heure seront ramenés à cette limite
     * Exemple : 8h30 = 8 * 60 + 30 = 510
     */
    'start_limit_minutes' => 8 * 60 + 30, // 8h30

    /**
     * Heure de fin de journée (format : heures * 60 + minutes)
     * Les badgeages après cette heure seront ramenés à cette limite
     * Exemple : 18h30 = 18 * 60 + 30 = 1110
     */
    'end_limit_minutes' => 18 * 60 + 30, // 18h30

    /**
     * Heure à partir de laquelle la pause du matin est ajoutée (format : heures * 60)
     * Si vous travaillez après cette heure, une pause sera ajoutée
     * Exemple : 11h00 = 11 * 60 = 660
     */
    'morning_break_threshold' => 11 * 60, // 11h00

    /**
     * Heure à partir de laquelle la pause de l'après-midi est ajoutée (format : heures * 60)
     * Si vous travaillez après cette heure, une pause sera ajoutée
     * Exemple : 16h00 = 16 * 60 = 960
     */
    'afternoon_break_threshold' => 16 * 60, // 16h00

    /**
     * Activer l'accès via GET (affichage du formulaire HTML)
     * Si false, seul l'accès POST (API) sera autorisé
     * Recommandé : false pour un usage en production (API uniquement)
     */
    'enable_form_access' => true,
];
