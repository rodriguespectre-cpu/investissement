<?php

/*
|--------------------------------------------------------------------------
| CONFIGURATION PAIEMENT
|--------------------------------------------------------------------------
|
| Toutes les informations sensibles du prestataire de paiement
| sont centralisées ici.
|
| IMPORTANT :
| - Ne partage jamais ta clé API secrète.
| - Ne mets pas ce fichier sur GitHub.
| - Remplace les valeurs ci-dessous par celles fournies
|   par ton prestataire.
|
|--------------------------------------------------------------------------
*/


return [

    /*
    |--------------------------------------------------------------------------
    | MODE
    |--------------------------------------------------------------------------
    |
    | 'sandbox' = tests
    | 'live'    = paiements réels
    |
    */

    'mode' => 'sandbox',


    /*
    |--------------------------------------------------------------------------
    | API
    |--------------------------------------------------------------------------
    */

    'api_url' => 'https://TON-PRESTATAIRE.com/api/payment',


    /*
    |--------------------------------------------------------------------------
    | CLÉ API
    |--------------------------------------------------------------------------
    |
    | À remplacer par ta véritable clé API.
    |
    */

    'api_key' => 'REMPLACE_PAR_TA_CLE_API',


    /*
    |--------------------------------------------------------------------------
    | SECRET WEBHOOK
    |--------------------------------------------------------------------------
    |
    | Si ton prestataire fournit un secret destiné
    | à vérifier les webhooks, mets-le ici.
    |
    */

    'webhook_secret' => 'REMPLACE_PAR_TON_SECRET_WEBHOOK',


    /*
    |--------------------------------------------------------------------------
    | DEVISE PAR DÉFAUT
    |--------------------------------------------------------------------------
    */

    'default_currency' => 'XAF',


    /*
    |--------------------------------------------------------------------------
    | MONTANT MINIMUM
    |--------------------------------------------------------------------------
    */

    'minimum_amount' => 100,


    /*
    |--------------------------------------------------------------------------
    | URL DE RETOUR
    |--------------------------------------------------------------------------
    |
    | Après le paiement, le prestataire peut rediriger
    | l'utilisateur vers cette adresse.
    |
    */

    'return_url' =>
        'http://127.0.0.1:8000/pages/dashboard/deposit.php',


    /*
    |--------------------------------------------------------------------------
    | URL ANNULATION
    |--------------------------------------------------------------------------
    */

    'cancel_url' =>
        'http://127.0.0.1:8000/pages/dashboard/deposit.php',


    /*
    |--------------------------------------------------------------------------
    | URL WEBHOOK
    |--------------------------------------------------------------------------
    |
    | Cette URL doit être accessible depuis Internet
    | lorsque le site sera réellement utilisé.
    |
    */

    'webhook_url' =>
        'http://127.0.0.1:8000/api/payment/webhook.php',


    /*
    |--------------------------------------------------------------------------
    | TIMEOUT API
    |--------------------------------------------------------------------------
    */

    'timeout' => 30,


    /*
    |--------------------------------------------------------------------------
    | PAIEMENT
    |--------------------------------------------------------------------------
    */

    'payment' => [

        /*
        | Type de paiement
        */

        'type' => 'deposit',

        /*
        | Statut initial dans la base
        */

        'initial_status' => 'pending',

        /*
        | Méthode affichée dans transactions
        */

        'method_name' => 'Mobile Money',

    ],


    /*
    |--------------------------------------------------------------------------
    | JOURNALISATION
    |--------------------------------------------------------------------------
    |
    | Utile pendant les tests.
    |
    */

    'logging' => [

        'enabled' => true,

        'file' =>
            __DIR__ . '/../storage/payment.log',

    ],

];
