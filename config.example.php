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
     * Pause minimum obligatoire le midi en minutes
     * Si la pause réelle entre 12h et 14h est inférieure à cette durée,
     * cette durée minimum sera déduite des heures payées
     * Exemple : 60 minutes = 1 heure minimum
     */
    'noon_minimum_break' => 60, // 1 heure

    /**
     * Heure de début de la plage de pause midi (format : heures * 60)
     * Exemple : 12h00 = 12 * 60 = 720
     */
    'noon_break_start' => 12 * 60, // 12h00

    /**
     * Heure de fin de la plage de pause midi (format : heures * 60)
     * Exemple : 14h00 = 14 * 60 = 840
     */
    'noon_break_end' => 14 * 60, // 14h00

    /**
     * Activer l'accès via GET (affichage du formulaire HTML)
     * Si false, seul l'accès POST (API) sera autorisé
     * Recommandé : false pour un usage en production (API uniquement)
     */
    'enable_form_access' => true,

    /**
     * Clé de chiffrement pour les tokens de session
     * IMPORTANT : Changez cette clé pour votre installation !
     * Utilisez une chaîne aléatoire de minimum 32 caractères
     */
    'encryption_key' => 'CHANGE-THIS-TO-A-RANDOM-SECRET-KEY-MIN-32-CHARS',

    /**
     * Mode debug pour le développement
     * Si true, les fichiers JSON seront formatés avec indentation (JSON_PRETTY_PRINT)
     * Si false, les fichiers seront minifiés pour optimiser les performances
     */
    'debug_mode' => false,

    /**
     * Durée maximale en secondes pour les tentatives de connexion par IP
     * Rate limiting : nombre maximum de tentatives par période
     */
    'rate_limit_max_attempts' => 5,
    'rate_limit_window' => 300, // 5 minutes

    /**
     * Identifiants administrateur pour accéder aux données sensibles
     * IMPORTANT : Changez ces valeurs pour votre installation !
     */
    'admin_username' => 'admin',
    'admin_password' => 'CHANGE-THIS-TO-A-SECURE-PASSWORD',
];
