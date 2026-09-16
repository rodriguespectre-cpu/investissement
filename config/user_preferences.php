<?php
// config/user_preferences.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/*
|--------------------------------------------------------------------------
| Langues disponibles
|--------------------------------------------------------------------------
*/

$AVAILABLE_LANGUAGES = [
    'fr' => 'Français',
    'en' => 'English',
    'es' => 'Español'
];

/*
|--------------------------------------------------------------------------
| Devises disponibles
|--------------------------------------------------------------------------
*/

$AVAILABLE_CURRENCIES = [
    'XAF' => [
        'name' => 'Franc CFA',
        'symbol' => 'FCFA',
        'rate' => 1
    ],
    'EUR' => [
        'name' => 'Euro',
        'symbol' => '€',
        'rate' => 0.001524
    ],
    'USD' => [
        'name' => 'Dollar américain',
        'symbol' => '$',
        'rate' => 0.00167
    ],
    'GBP' => [
        'name' => 'Livre sterling',
        'symbol' => '£',
        'rate' => 0.00130
    ],
    'CAD' => [
        'name' => 'Dollar canadien',
        'symbol' => 'CA$',
        'rate' => 0.00228
    ]
];

/*
|--------------------------------------------------------------------------
| Récupération des préférences utilisateur
|--------------------------------------------------------------------------
*/

function getUserPreferences(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare("
        SELECT
            id,
            username,
            email,
            phone,
            country_code,
            currency_code,
            language_code,
            balance,
            bonus_balance
        FROM users
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([$userId]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        return [];
    }

    $user['language_code'] =
        in_array($user['language_code'], ['fr', 'en', 'es'], true)
            ? $user['language_code']
            : 'fr';

    $user['currency_code'] =
        array_key_exists($user['currency_code'], $GLOBALS['AVAILABLE_CURRENCIES'])
            ? $user['currency_code']
            : 'XAF';

    return $user;
}


/*
|--------------------------------------------------------------------------
| Traductions
|--------------------------------------------------------------------------
*/

$TRANSLATIONS = [

    'fr' => [

        'settings' => 'Paramètres',
        'appearance' => 'Apparence',
        'dark_mode' => 'Mode sombre',
        'language' => 'Langue',
        'currency' => 'Devise',
        'personal_information' => 'Informations personnelles',

        'username' => "Nom d'utilisateur",
        'email' => 'Adresse email',
        'phone' => 'Numéro de téléphone',
        'country' => 'Pays',

        'save' => 'Enregistrer les modifications',
        'saved' => 'Modifications enregistrées.',

        'balance' => 'Solde',
        'bonus_balance' => 'Solde bonus',
        'investments' => 'Investissements',
        'deposit' => 'Dépôt',
        'withdraw' => 'Retrait',
        'dashboard' => 'Tableau de bord',
        'logout' => 'Déconnexion',

        'welcome' => 'Bienvenue',
        'profile' => 'Profil',

        'french' => 'Français',
        'english' => 'Anglais',
        'spanish' => 'Espagnol',

        'back' => 'Retour'
    ],

    'en' => [

        'settings' => 'Settings',
        'appearance' => 'Appearance',
        'dark_mode' => 'Dark mode',
        'language' => 'Language',
        'currency' => 'Currency',
        'personal_information' => 'Personal information',

        'username' => 'Username',
        'email' => 'Email address',
        'phone' => 'Phone number',
        'country' => 'Country',

        'save' => 'Save changes',
        'saved' => 'Changes saved.',

        'balance' => 'Balance',
        'bonus_balance' => 'Bonus balance',
        'investments' => 'Investments',
        'deposit' => 'Deposit',
        'withdraw' => 'Withdraw',
        'dashboard' => 'Dashboard',
        'logout' => 'Logout',

        'welcome' => 'Welcome',
        'profile' => 'Profile',

        'french' => 'French',
        'english' => 'English',
        'spanish' => 'Spanish',

        'back' => 'Back'
    ],

    'es' => [

        'settings' => 'Configuración',
        'appearance' => 'Apariencia',
        'dark_mode' => 'Modo oscuro',
        'language' => 'Idioma',
        'currency' => 'Moneda',
        'personal_information' => 'Información personal',

        'username' => 'Nombre de usuario',
        'email' => 'Correo electrónico',
        'phone' => 'Número de teléfono',
        'country' => 'País',

        'save' => 'Guardar cambios',
        'saved' => 'Cambios guardados.',

        'balance' => 'Saldo',
        'bonus_balance' => 'Saldo de bonificación',
        'investments' => 'Inversiones',
        'deposit' => 'Depósito',
        'withdraw' => 'Retiro',
        'dashboard' => 'Panel',
        'logout' => 'Cerrar sesión',

        'welcome' => 'Bienvenido',
        'profile' => 'Perfil',

        'french' => 'Francés',
        'english' => 'Inglés',
        'spanish' => 'Español',

        'back' => 'Volver'
    ]
];


/*
|--------------------------------------------------------------------------
| Fonction de traduction
|--------------------------------------------------------------------------
*/

function t(string $key): string
{
    global $TRANSLATIONS;

    $language = $_SESSION['language_code'] ?? 'fr';

    return $TRANSLATIONS[$language][$key]
        ?? $TRANSLATIONS['fr'][$key]
        ?? $key;
}


/*
|--------------------------------------------------------------------------
| Conversion XAF → devise utilisateur
|--------------------------------------------------------------------------
*/

function convertAmount(float $xaf, string $currency): float
{
    global $AVAILABLE_CURRENCIES;

    if (!isset($AVAILABLE_CURRENCIES[$currency])) {
        $currency = 'XAF';
    }

    return $xaf * $AVAILABLE_CURRENCIES[$currency]['rate'];
}


/*
|--------------------------------------------------------------------------
| Affichage d'un montant
|--------------------------------------------------------------------------
*/

function formatUserAmount(float $xaf, ?string $currency = null): string
{
    global $AVAILABLE_CURRENCIES;

    $currency =
        $currency
        ?? $_SESSION['currency_code']
        ?? 'XAF';

    if (!isset($AVAILABLE_CURRENCIES[$currency])) {
        $currency = 'XAF';
    }

    $converted = convertAmount($xaf, $currency);

    if ($currency === 'XAF') {
        return number_format(
            $converted,
            0,
            ',',
            ' '
        ) . ' FCFA';
    }

    return $AVAILABLE_CURRENCIES[$currency]['symbol']
        . ' '
        . number_format(
            $converted,
            2,
            '.',
            ','
        );
}
