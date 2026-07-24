<?php

/*
|--------------------------------------------------------------------------
| Contract generation settings
|--------------------------------------------------------------------------
|
| Broker (courtier) identity printed on every generated contract.
| Edit here — no code change needed. A future admin settings page can
| override these values.
|
*/

return [

    'broker' => [
        'name' => 'SOLIVIE',
        'legal_form' => "Courtier d'assurance de catégorie B",
        'rcs' => 'RCS Bordeaux 943 794 305',
        'address' => '35 Rue de la République',
        'postal_code' => '92800',
        'city' => 'Puteaux',
        'country' => 'France',
        'phone' => '05 47 74 02 70',
        'email' => 'gestion@solivie.fr',
        'website' => 'www.solivie.fr',
        'orias_article' => "L.520-1, II, 1°, b) du Code des assurances",
    ],

    'authority' => [
        'name' => 'Autorité de Contrôle Prudentiel et de Résolution (ACPR)',
        'address' => '4 place de Budapest, 75436 Paris Cedex 9',
    ],

    'mediation' => [
        'name' => "La Médiation de l'Assurance",
        'address' => 'TSA 50110, 75441 Paris Cedex 9',
        'website' => 'www.mediation-assurance.org',
    ],

    'storage_disk' => 'local',
    'storage_path' => 'contracts',
];
