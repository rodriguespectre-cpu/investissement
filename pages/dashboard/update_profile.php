<?php

session_start();

require_once '../../config/database.php';
require_once '../../config/user_preferences.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: settings.php');
    exit;
}

$username = trim($_POST['username'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$countryCode = strtoupper(trim($_POST['country_code'] ?? ''));

$languageCode = strtolower(
    trim($_POST['language_code'] ?? 'fr')
);

$currencyCode = strtoupper(
    trim($_POST['currency_code'] ?? 'XAF')
);


/*
|--------------------------------------------------------------------------
| Validation
|--------------------------------------------------------------------------
*/

if ($username === '') {
    die('Le nom est obligatoire.');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    die('Adresse email invalide.');
}

if (!array_key_exists($languageCode, $AVAILABLE_LANGUAGES)) {
    die('Langue invalide.');
}

if (!array_key_exists($currencyCode, $AVAILABLE_CURRENCIES)) {
    die('Devise invalide.');
}


/*
|--------------------------------------------------------------------------
| Nettoyage du téléphone
|--------------------------------------------------------------------------
*/

$phone = preg_replace('/[\s().-]+/', '', $phone);

if ($phone !== '') {

    if (!preg_match('/^\+?[0-9]{7,20}$/', $phone)) {
        die('Numéro de téléphone invalide.');
    }

}


/*
|--------------------------------------------------------------------------
| Vérification email
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT id
    FROM users
    WHERE email = ?
      AND id != ?
    LIMIT 1
");

$stmt->execute([
    $email,
    $userId
]);

if ($stmt->fetch()) {
    die('Cette adresse email est déjà utilisée.');
}


/*
|--------------------------------------------------------------------------
| Mise à jour
|--------------------------------------------------------------------------
*/

try {

    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        UPDATE users
        SET
            username = ?,
            email = ?,
            phone = ?,
            country_code = ?,
            language_code = ?,
            currency_code = ?,
            updated_at = NOW()
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([
        $username,
        $email,
        $phone !== '' ? $phone : null,
        $countryCode !== '' ? $countryCode : null,
        $languageCode,
        $currencyCode,
        $userId
    ]);

    $pdo->commit();


    /*
    |--------------------------------------------------------------------------
    | Session
    |--------------------------------------------------------------------------
    */

    $_SESSION['username'] = $username;
    $_SESSION['email'] = $email;

    $_SESSION['language_code'] =
        $languageCode;

    $_SESSION['currency_code'] =
        $currencyCode;


    header(
        'Location: settings.php?success=1'
    );

    exit;

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'update_profile.php: ' .
        $e->getMessage()
    );

    die(
        'Une erreur est survenue lors de l’enregistrement.'
    );
}
