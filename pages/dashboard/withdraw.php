<?php

session_start();

require_once '../../config/database.php';

require_once '../../config/referral.php';


/*
|--------------------------------------------------------------------------
| AUTHENTIFICATION
|--------------------------------------------------------------------------
*/

if (!isLoggedIn()) {
    header("Location: ../login.php");
    exit;
}

$user = getCurrentUser();

if (!$user) {
    session_destroy();
    header("Location: ../login.php");
    exit;
}

$userId = (int) $user['id'];


/*
|--------------------------------------------------------------------------
| CONFIGURATION
|--------------------------------------------------------------------------
*/

$minimumWithdrawal = 2000.00;

/*
 * 4 000 FCFA => 250 FCFA de frais
 *
 * 250 / 4000 = 6.25 %
 */
$withdrawalFeeRate = 0.0625;


/*
|--------------------------------------------------------------------------
| PAYS ET MOYENS DE PAIEMENT
|--------------------------------------------------------------------------
*/

$paymentMethodsByCountry = [

    'CM' => [
        'name' => 'Cameroun',
        'flag' => '🇨🇲',
        'phone_code' => '+237',
        'methods' => [
            'MTN MoMo' => 'MTN MoMo',
            'Orange Money' => 'Orange Money'
        ]
    ],

    'PT' => [
        'name' => 'Portugal',
        'flag' => '🇵🇹',
        'phone_code' => '+351',
        'methods' => [
            'MB WAY' => 'MB WAY',
            'Virement bancaire' => 'Virement bancaire'
        ]
    ],

    'SN' => [
        'name' => 'Sénégal',
        'flag' => '🇸🇳',
        'phone_code' => '+221',
        'methods' => [
            'Orange Money' => 'Orange Money',
            'Wave' => 'Wave',
            'Free Money' => 'Free Money'
        ]
    ],

    'CI' => [
        'name' => 'Côte d’Ivoire',
        'flag' => '🇨🇮',
        'phone_code' => '+225',
        'methods' => [
            'Orange Money' => 'Orange Money',
            'MTN MoMo' => 'MTN MoMo',
            'Moov Money' => 'Moov Money',
            'Wave' => 'Wave'
        ]
    ],

    'TG' => [
        'name' => 'Togo',
        'flag' => '🇹🇬',
        'phone_code' => '+228',
        'methods' => [
            'TMoney' => 'TMoney',
            'Flooz' => 'Flooz'
        ]
    ],

    'BJ' => [
        'name' => 'Bénin',
        'flag' => '🇧🇯',
        'phone_code' => '+229',
        'methods' => [
            'MTN MoMo' => 'MTN MoMo',
            'Moov Money' => 'Moov Money'
        ]
    ],

    'GA' => [
        'name' => 'Gabon',
        'flag' => '🇬🇦',
        'phone_code' => '+241',
        'methods' => [
            'Airtel Money' => 'Airtel Money',
            'Moov Money' => 'Moov Money'
        ]
    ],

    'CD' => [
        'name' => 'RDC',
        'flag' => '🇨🇩',
        'phone_code' => '+243',
        'methods' => [
            'M-Pesa' => 'M-Pesa',
            'Airtel Money' => 'Airtel Money',
            'Orange Money' => 'Orange Money'
        ]
    ]

];


/*
|--------------------------------------------------------------------------
| FONCTION : CALCUL DES FRAIS
|--------------------------------------------------------------------------
*/

function calculateWithdrawalFee(
    float $amount,
    float $feeRate
): float {

    $fee = $amount * $feeRate;

    /*
     * Arrondi à 50 FCFA pour garder des frais propres.
     */
    $fee = round($fee / 50) * 50;

    return max(0, $fee);
}


/*
|--------------------------------------------------------------------------
| RÉCUPÉRATION DU SOLDE
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        username,
        email,
        balance,
        bonus_balance,
        currency_code,
        country_code,
        phone
    FROM users
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([$userId]);

$userData = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$userData) {
    session_destroy();
    header("Location: ../login.php");
    exit;
}

$currentBalance = (float) ($userData['balance'] ?? 0);

$userCountry = strtoupper(
    trim($userData['country_code'] ?? '')
);


/*
|--------------------------------------------------------------------------
| VARIABLES
|--------------------------------------------------------------------------
*/

$errors = [];
$success = '';

$amountInput = '';
$selectedCountry = $userCountry;
$selectedMethod = '';
$withdrawalPhone = '';


/*
|--------------------------------------------------------------------------
| TRAITEMENT DU RETRAIT
|--------------------------------------------------------------------------
*/



if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $amountInput = trim($_POST['amount'] ?? '');
    $selectedCountry = strtoupper(
        trim($_POST['withdrawal_country'] ?? '')
    );
    $selectedMethod = trim(
        $_POST['payment_method'] ?? ''
    );
    $withdrawalPhone = trim(
        $_POST['withdrawal_phone'] ?? ''
    );


    /*
    |--------------------------------------------------------------------------
    | MONTANT
    |--------------------------------------------------------------------------
    */

    $normalizedAmount = str_replace(
        [' ', ','],
        ['', '.'],
        $amountInput
    );

$balanceType = $_POST['balance_type'] ?? 'balance';

if ($balanceType === 'bonus_balance') {
    if (!canWithdrawReferralBonus($pdo, $userId)) {
        $errors[] = "Vous devez avoir un filleul ayant souscrit à un abonnement.";
    }
} 

    if (
        $normalizedAmount === ''
        || !is_numeric($normalizedAmount)
    ) {

        $errors[] =
            "Veuillez entrer un montant de retrait valide.";

    } else {

        $withdrawalAmount =
            (float) $normalizedAmount;

        if ($withdrawalAmount < $minimumWithdrawal) {

            $errors[] =
                "Le montant minimum de retrait est de "
                . number_format(
                    $minimumWithdrawal,
                    0,
                    ',',
                    ' '
                )
                . " FCFA.";

        }

        if ($withdrawalAmount <= 0) {

            $errors[] =
                "Le montant doit être supérieur à zéro.";

        }
    }


    /*
    |--------------------------------------------------------------------------
    | PAYS
    |--------------------------------------------------------------------------
    */

    if (!isset($paymentMethodsByCountry[$selectedCountry])) {

        $errors[] =
            "Veuillez sélectionner un pays valide.";

    }


    /*
    |--------------------------------------------------------------------------
    | MOYEN DE PAIEMENT
    |--------------------------------------------------------------------------
    */

    if (
        isset($paymentMethodsByCountry[$selectedCountry])
        && !array_key_exists(
            $selectedMethod,
            $paymentMethodsByCountry[$selectedCountry]['methods']
        )
    ) {

        $errors[] =
            "Le moyen de paiement sélectionné "
            . "n'est pas disponible dans ce pays.";

    }


    /*
    |--------------------------------------------------------------------------
    | NUMÉRO DE PORTEFEUILLE
    |--------------------------------------------------------------------------
    */

    if ($withdrawalPhone === '') {

        $errors[] =
            "Veuillez renseigner le numéro du portefeuille.";

    } else {

        /*
         * On conserve uniquement chiffres, espaces,
         * +, -, parenthèses.
         */
        if (
            !preg_match(
                '/^[0-9+\s().-]{7,30}$/',
                $withdrawalPhone
            )
        ) {

            $errors[] =
                "Le numéro du portefeuille est invalide.";

        }
    }


    /*
    |--------------------------------------------------------------------------
    | VÉRIFICATION D'INVESTISSEMENT ACTIF
    |--------------------------------------------------------------------------
    */

    if (empty($errors)) {

        $stmt = $pdo->prepare("
            SELECT id
            FROM investments
            WHERE user_id = ?
              AND status = 'active'
            LIMIT 1
        ");

        $stmt->execute([$userId]);

        $activeInvestment =
            $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$activeInvestment) {

            $errors[] =
                "Vous devez avoir au moins un investissement "
                . "actif avant de pouvoir effectuer un retrait.";

        }
    }


    /*
    |--------------------------------------------------------------------------
    | CALCUL DES FRAIS
    |--------------------------------------------------------------------------
    */

    if (empty($errors)) {

        $withdrawalFee =
            calculateWithdrawalFee(
                $withdrawalAmount,
                $withdrawalFeeRate
            );

        /*
         * Ici le montant demandé représente le montant
         * retiré du solde.
         *
         * Le bénéficiaire reçoit :
         *
         * montant - frais
         */
        $netAmount =
            $withdrawalAmount
            - $withdrawalFee;

        if ($netAmount <= 0) {

            $errors[] =
                "Le montant net après frais est invalide.";

        }


        /*
        |--------------------------------------------------------------------------
        | SOLDE
        |--------------------------------------------------------------------------
        */

        if (
            empty($errors)
            && $withdrawalAmount > $currentBalance
        ) {

            $errors[] =
                "Solde insuffisant. Votre solde disponible est de "
                . number_format(
                    $currentBalance,
                    0,
                    ',',
                    ' '
                )
                . " FCFA.";

        }
    }


    /*
    |--------------------------------------------------------------------------
    | CRÉATION DU RETRAIT
    |--------------------------------------------------------------------------
    */

    if (empty($errors)) {

        try {

            $pdo->beginTransaction();


            /*
            |--------------------------------------------------------------------------
            | VERROUILLER LE COMPTE
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                SELECT
                    id,
                    balance
                FROM users
                WHERE id = ?
                FOR UPDATE
            ");

            $stmt->execute([$userId]);

            $lockedUser =
                $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$lockedUser) {

                throw new Exception(
                    "Utilisateur introuvable."
                );
            }


            $lockedBalance =
                (float) $lockedUser['balance'];


            /*
            |--------------------------------------------------------------------------
            | DOUBLE VÉRIFICATION DU SOLDE
            |--------------------------------------------------------------------------
            */

            if ($withdrawalAmount > $lockedBalance) {

                throw new Exception(
                    "Votre solde disponible a changé. "
                    . "Veuillez actualiser la page."
                );
            }


            /*
            |--------------------------------------------------------------------------
            | RÉFÉRENCE UNIQUE
            |--------------------------------------------------------------------------
            */

            $reference =
                'WD-'
                . date('YmdHis')
                . '-'
                . strtoupper(
                    bin2hex(
                        random_bytes(3)
                    )
                );


            /*
            |--------------------------------------------------------------------------
            | DÉBIT DU SOLDE
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                UPDATE users
                SET balance = balance - ?
                WHERE id = ?
                  AND balance >= ?
            ");

            $stmt->execute([
                $withdrawalAmount,
                $userId,
                $withdrawalAmount
            ]);


            if ($stmt->rowCount() !== 1) {

                throw new Exception(
                    "Impossible de débiter le solde."
                );
            }


            /*
            |--------------------------------------------------------------------------
            | CRÉATION DE LA TRANSACTION
            |--------------------------------------------------------------------------
            */

            $description =
                "Demande de retrait de "
                . number_format(
                    $withdrawalAmount,
                    0,
                    ',',
                    ' '
                )
                . " FCFA via "
                . $selectedMethod
                . ". Frais : "
                . number_format(
                    $withdrawalFee,
                    0,
                    ',',
                    ' '
                )
                . " FCFA. Montant net à envoyer : "
                . number_format(
                    $netAmount,
                    0,
                    ',',
                    ' '
                )
                . " FCFA.";


            $stmt = $pdo->prepare("
                INSERT INTO transactions (
                    user_id,
                    type,
                    amount,
                    fee,
                    status,
                    payment_method,
                    withdrawal_country,
                    withdrawal_phone,
                    reference,
                    description
                )
                VALUES (
                    ?,
                    'withdraw',
                    ?,
                    ?,
                    'pending',
                    ?,
                    ?,
                    ?,
                    ?,
                    ?
                )
            ");

            $stmt->execute([
                $userId,
                $withdrawalAmount,
                $withdrawalFee,
                $selectedMethod,
                $selectedCountry,
                $withdrawalPhone,
                $reference,
                $description
            ]);


            $transactionId =
                (int) $pdo->lastInsertId();


            /*
            |--------------------------------------------------------------------------
            | ENREGISTRER LE DÉBIT DANS BALANCES
            |--------------------------------------------------------------------------
            */

            $balanceDescription =
                "Retrait en attente "
                . $reference
                . " - "
                . $selectedMethod;


            $stmt = $pdo->prepare("
                INSERT INTO balances (
                    user_id,
                    amount,
                    type,
                    source,
                    reference_id,
                    description
                )
                VALUES (
                    ?,
                    ?,
                    'debit',
                    'withdraw',
                    ?,
                    ?
                )
            ");

            $stmt->execute([
                $userId,
                $withdrawalAmount,
                $transactionId,
                $balanceDescription
            ]);


            /*
            |--------------------------------------------------------------------------
            | NOTIFICATION
            |--------------------------------------------------------------------------
            */

            $countryName =
                $paymentMethodsByCountry[
                    $selectedCountry
                ]['name'];


            $notificationTitle =
                "Demande de retrait enregistrée";


            $notificationMessage =
                "Votre demande de retrait de "
                . number_format(
                    $withdrawalAmount,
                    0,
                    ',',
                    ' '
                )
                . " FCFA via "
                . $selectedMethod
                . " a été enregistrée. "
                . "Montant net à recevoir : "
                . number_format(
                    $netAmount,
                    0,
                    ',',
                    ' '
                )
                . " FCFA. "
                . "Référence : "
                . $reference
                . ". "
                . "Le traitement peut prendre jusqu'à "
                . "24 heures ouvrées.";


            /*
             * IMPORTANT :
             *
             * notifications.type accepte uniquement :
             * info / success / warning / error
             */
            $stmt = $pdo->prepare("
                INSERT INTO notifications (
                    user_id,
                    type,
                    title,
                    message,
                    is_read,
                    link
                )
                VALUES (
                    ?,
                    'info',
                    ?,
                    ?,
                    0,
                    ?
                )
            ");

            $notificationLink =
                'withdraw.php';


            $stmt->execute([
                $userId,
                $notificationTitle,
                $notificationMessage,
                $notificationLink
            ]);


            /*
            |--------------------------------------------------------------------------
            | VALIDATION SQL
            |--------------------------------------------------------------------------
            */

            $pdo->commit();


            /*
            |--------------------------------------------------------------------------
            | MISE À JOUR DU SOLDE AFFICHÉ
            |--------------------------------------------------------------------------
            */

            $currentBalance =
                $lockedBalance
                - $withdrawalAmount;


            /*
            |--------------------------------------------------------------------------
            | MESSAGE DE SUCCÈS
            |--------------------------------------------------------------------------
            */

            $success =
                "Votre demande de retrait a été enregistrée "
                . "avec succès. Référence : "
                . $reference
                . ". Montant net à recevoir : "
                . number_format(
                    $netAmount,
                    0,
                    ',',
                    ' '
                )
                . " FCFA.";


            /*
            | Nettoyage du formulaire
            */

            $amountInput = '';
            $selectedMethod = '';
            $withdrawalPhone = '';


        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $errors[] =
                "Impossible d'enregistrer le retrait. "
                . "Veuillez réessayer.";
        }
    }
}


/*
|--------------------------------------------------------------------------
| HISTORIQUE DES RETRAITS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        amount,
        fee,
        status,
        payment_method,
        withdrawal_country,
        withdrawal_phone,
        reference,
        description,
        completed_at,
        created_at
    FROM transactions
    WHERE user_id = ?
      AND type = 'withdraw'
    ORDER BY created_at DESC
    LIMIT 20
");

$stmt->execute([$userId]);

$withdrawals =
    $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| LABELS STATUT
|--------------------------------------------------------------------------
*/

$statusLabels = [

    'pending' => [
        'label' => 'En attente',
        'class' => 'pending',
        'icon' => 'fa-clock'
    ],

    'completed' => [
        'label' => 'Validé',
        'class' => 'completed',
        'icon' => 'fa-circle-check'
    ],

    'failed' => [
        'label' => 'Échec',
        'class' => 'failed',
        'icon' => 'fa-circle-xmark'
    ],

    'cancelled' => [
        'label' => 'Annulé',
        'class' => 'cancelled',
        'icon' => 'fa-ban'
    ]

];

?>
<!DOCTYPE html>

<html lang="fr">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>
    Retrait — InvestPro
</title>

<link
    rel="stylesheet"
    href="assets/index.css"
>

<link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    background: #f5f7fb;
    color: #172033;
    font-family: Arial, Helvetica, sans-serif;
}

.withdraw-page {
    width: min(1180px, calc(100% - 30px));
    margin: 30px auto 60px;
}

.page-heading {
    margin-bottom: 25px;
}

.page-heading .eyebrow {
    display: block;
    font-size: 12px;
    font-weight: 800;
    letter-spacing: 1.5px;
    color: #64748b;
    margin-bottom: 8px;
}

.page-heading h1 {
    margin: 0;
    font-size: 32px;
}

.page-heading p {
    margin: 8px 0 0;
    color: #64748b;
}


/*
|--------------------------------------------------------------------------
| GRID
|--------------------------------------------------------------------------
*/

.withdraw-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 360px;
    gap: 22px;
    align-items: start;
}

.card {
    background: #ffffff;
    border: 1px solid #e7ebf2;
    border-radius: 20px;
    box-shadow: 0 10px 35px rgba(15, 23, 42, .06);
    padding: 25px;
}

.card-title {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 22px;
}

.card-title-icon {
    width: 45px;
    height: 45px;
    border-radius: 13px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #eef4ff;
    color: #2563eb;
    font-size: 18px;
}

.card-title h2 {
    margin: 0;
    font-size: 20px;
}

.card-title p {
    margin: 4px 0 0;
    color: #64748b;
    font-size: 13px;
}


/*
|--------------------------------------------------------------------------
| ALERTES
|--------------------------------------------------------------------------
*/

.alert {
    border-radius: 14px;
    padding: 14px 16px;
    margin-bottom: 18px;
    font-size: 14px;
}

.alert-error {
    background: #fff1f2;
    color: #be123c;
    border: 1px solid #fecdd3;
}

.alert-success {
    background: #ecfdf5;
    color: #047857;
    border: 1px solid #a7f3d0;
}


/*
|--------------------------------------------------------------------------
| SOLDE
|--------------------------------------------------------------------------
*/

.balance-box {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    padding: 18px;
    margin-bottom: 22px;
    border-radius: 16px;
    background: linear-gradient(
        135deg,
        #eff6ff,
        #f8fafc
    );
    border: 1px solid #dbeafe;
}

.balance-box small {
    display: block;
    color: #64748b;
    margin-bottom: 5px;
}

.balance-box strong {
    font-size: 25px;
}

.balance-icon {
    width: 48px;
    height: 48px;
    border-radius: 14px;
    background: #2563eb;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
}


/*
|--------------------------------------------------------------------------
| FORMULAIRE
|--------------------------------------------------------------------------
*/

.form-group {
    margin-bottom: 18px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-size: 14px;
    font-weight: 700;
}

.form-control {
    width: 100%;
    height: 50px;
    border: 1px solid #d8dee9;
    border-radius: 12px;
    padding: 0 14px;
    background: #fff;
    color: #172033;
    font-size: 15px;
    outline: none;
    transition: .2s;
}

.form-control:focus {
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, .10);
}

.phone-wrapper {
    display: flex;
    gap: 8px;
}

.phone-prefix {
    min-width: 100px;
    height: 50px;
    padding: 0 10px;
    border-radius: 12px;
    background: #f1f5f9;
    border: 1px solid #d8dee9;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 13px;
}

.phone-wrapper .form-control {
    flex: 1;
}

.help-text {
    display: block;
    margin-top: 7px;
    color: #64748b;
    font-size: 12px;
}


/*
|--------------------------------------------------------------------------
| CALCUL
|--------------------------------------------------------------------------
*/

.calculation {
    background: #f8fafc;
    border: 1px solid #e5e7eb;
    border-radius: 16px;
    padding: 17px;
    margin: 20px 0;
}

.calculation-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    padding: 8px 0;
    font-size: 14px;
}

.calculation-row span:first-child {
    color: #64748b;
}

.calculation-row.total {
    margin-top: 8px;
    padding-top: 15px;
    border-top: 1px solid #e2e8f0;
    font-weight: 800;
}

.calculation-row.total strong {
    font-size: 19px;
    color: #047857;
}


/*
|--------------------------------------------------------------------------
| BOUTON
|--------------------------------------------------------------------------
*/

.submit-button {
    width: 100%;
    height: 53px;
    border: 0;
    border-radius: 13px;
    background: #2563eb;
    color: white;
    font-size: 15px;
    font-weight: 800;
    cursor: pointer;
    transition: .2s;
}

.submit-button:hover {
    background: #1d4ed8;
    transform: translateY(-1px);
}


/*
|--------------------------------------------------------------------------
| INFO
|--------------------------------------------------------------------------
*/

.info-list {
    display: grid;
    gap: 14px;
}

.info-item {
    display: flex;
    gap: 12px;
    align-items: flex-start;
}

.info-item i {
    width: 32px;
    height: 32px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #eff6ff;
    color: #2563eb;
    flex-shrink: 0;
}

.info-item strong {
    display: block;
    margin-bottom: 4px;
    font-size: 14px;
}

.info-item p {
    margin: 0;
    color: #64748b;
    line-height: 1.5;
    font-size: 13px;
}


/*
|--------------------------------------------------------------------------
| HISTORIQUE
|--------------------------------------------------------------------------
*/

.history-card {
    margin-top: 22px;
}

.withdrawal-list {
    display: grid;
    gap: 12px;
}

.withdrawal-item {
    display: grid;
    grid-template-columns: 48px minmax(0, 1fr) auto;
    gap: 13px;
    align-items: center;
    padding: 15px;
    border: 1px solid #e8edf4;
    border-radius: 15px;
}

.withdrawal-icon {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    background: #f1f5f9;
    color: #475569;
    display: flex;
    align-items: center;
    justify-content: center;
}

.withdrawal-main strong {
    display: block;
    margin-bottom: 5px;
}

.withdrawal-main small {
    display: block;
    color: #64748b;
    margin-top: 3px;
}

.withdrawal-amount {
    text-align: right;
}

.withdrawal-amount strong {
    display: block;
    font-size: 16px;
}

.status {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    margin-top: 6px;
    padding: 5px 9px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 800;
}

.status.pending {
    background: #fff7ed;
    color: #c2410c;
}

.status.completed {
    background: #ecfdf5;
    color: #047857;
}

.status.failed,
.status.cancelled {
    background: #fff1f2;
    color: #be123c;
}

.empty-history {
    text-align: center;
    padding: 35px 15px;
    color: #64748b;
}

.empty-history i {
    font-size: 30px;
    margin-bottom: 10px;
}


/*
|--------------------------------------------------------------------------
| RESPONSIVE
|--------------------------------------------------------------------------
*/

@media (max-width: 850px) {

    .withdraw-grid {
        grid-template-columns: 1fr;
    }

}

@media (max-width: 600px) {

    .withdraw-page {
        width: min(100% - 20px, 1180px);
        margin-top: 20px;
    }

    .card {
        padding: 18px;
        border-radius: 17px;
    }

    .page-heading h1 {
        font-size: 27px;
    }

    .withdrawal-item {
        grid-template-columns: 42px minmax(0, 1fr);
    }

    .withdrawal-amount {
        grid-column: 2;
        text-align: left;
    }

    .phone-wrapper {
        gap: 6px;
    }

    .phone-prefix {
        min-width: 85px;
        font-size: 12px;
    }

}

.custom-select {
    font-size: 18px;
    font-weight: 600;
    width: 100%; /* prend toute la largeur */
    max-width: 400px; /* mais pas plus de 400px */
    padding: 14px 20px;
    margin: 15px 0; /* marge haut/bas de 15px */
    
    background: #ffffff;
    color: #333;
    border: 2px solid #667eea; /* bordure violette */
    border-radius: 12px; /* coins arrondis */
    
    appearance: none; /* enlever la flèche par défaut */
    -webkit-appearance: none;
    -moz-appearance: none;
    
    background-image: url("data:image/svg+xml;charset=US-ASCII,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='10' viewBox='0 0 14 10'%3E%3Cpath fill='%23667eea' d='M7 10L0 0h14z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 20px center;
    padding-right: 50px; /* espace pour la flèche */
    
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 2px 8px rgba(102, 126, 234, 0.15);
}

.custom-select:hover {
    border-color: #764ba2;
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.25);
}

.custom-select:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.2); /* halo au focus */
}

</style>

</head>


<body>

<div class="withdraw-page">


    <!--
    |--------------------------------------------------------------------------
    | EN-TÊTE
    |--------------------------------------------------------------------------
    -->

    <div class="page-heading">

        <span class="eyebrow">
            PORTEFEUILLE
        </span>

        <h1>
            Retirer des fonds
        </h1>

        <p>
            Retirez vos fonds vers votre portefeuille de paiement.
        </p>

    </div>


    <!--
    |--------------------------------------------------------------------------
    | MESSAGES
    |--------------------------------------------------------------------------
    -->

    <?php if (!empty($errors)): ?>

        <div class="alert alert-error">

            <?php foreach ($errors as $error): ?>

                <div>
                    <i class="fas fa-circle-exclamation"></i>
                    <?= htmlspecialchars($error) ?>
                </div>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>


    <?php if ($success): ?>

        <div class="alert alert-success">

            <i class="fas fa-circle-check"></i>

            <?= htmlspecialchars($success) ?>

        </div>

    <?php endif; ?>


    <!--
    |--------------------------------------------------------------------------
    | GRID
    |--------------------------------------------------------------------------
    -->

    <div class="withdraw-grid">


        <!--
        |--------------------------------------------------------------------------
        | FORMULAIRE
        |--------------------------------------------------------------------------
        -->

        <section class="card">

            <div class="card-title">

                <div class="card-title-icon">

                    <i class="fas fa-money-bill-transfer"></i>

                </div>

                <div>

                    <h2>
                        Nouvelle demande
                    </h2>

                    <p>
                        Remplissez les informations de votre retrait.
                    </p>

                </div>

            </div>


            <!-- SOLDE -->

            <div class="balance-box">

                <div>

                    <small>
                        Solde disponible
                    </small>

                    <strong id="availableBalance">

                        <?= number_format(
                            $currentBalance,
                            0,
                            ',',
                            ' '
                        ) ?>

                        FCFA

                    </strong>

                </div>

                <div class="balance-icon">

                    <i class="fas fa-wallet"></i>

                </div>

            </div>


            <form
                method="POST"
                id="withdrawForm"
                autocomplete="off"
            >


                <!--
                |--------------------------------------------------------------------------
                | PAYS
                |--------------------------------------------------------------------------
                -->

                <div class="form-group">

                    <label for="withdrawal_country">

                        <i class="fas fa-globe"></i>

                        Pays du portefeuille

                    </label>

                    <select
                        class="form-control"
                        id="withdrawal_country"
                        name="withdrawal_country"
                        required
                    >

                        <option value="">
                            Sélectionner un pays
                        </option>

                        <?php foreach (
                            $paymentMethodsByCountry
                            as $countryCode => $country
                        ): ?>

                            <option
                                value="<?= htmlspecialchars($countryCode) ?>"
                                <?= $selectedCountry === $countryCode ? 'selected' : '' ?>
                            >

                                <?= htmlspecialchars($country['flag']) ?>

                                <?= htmlspecialchars($country['name']) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <!--
                |--------------------------------------------------------------------------
                | MOYEN
                |--------------------------------------------------------------------------
                -->

                <div class="form-group">

                    <label for="payment_method">

                        <i class="fas fa-credit-card"></i>

                        Moyen de paiement

                    </label>

                    <select
                        class="form-control"
                        id="payment_method"
                        name="payment_method"
                        required
                    >

                        <option value="">
                            Sélectionner un moyen
                        </option>

                    </select>

                    <small class="help-text">

                        Les moyens disponibles dépendent du pays sélectionné.

                    </small>

                </div>


                <!--
                |--------------------------------------------------------------------------
                | NUMÉRO
                |--------------------------------------------------------------------------
                -->

                <div class="form-group">

                    <label for="withdrawal_phone">

                        <i class="fas fa-mobile-screen-button"></i>

                        Numéro du portefeuille

                    </label>

                    <div class="phone-wrapper">

                        <div
                            class="phone-prefix"
                            id="phonePrefix"
                        >
                            +237
                        </div>

                        <input
                            type="text"
                            class="form-control"
                            id="withdrawal_phone"
                            name="withdrawal_phone"
                            value="<?= htmlspecialchars($withdrawalPhone) ?>"
                            placeholder="6XXXXXXXX"
                            maxlength="30"
                            required
                        >

                    </div>

                    <small class="help-text">

                        Entrez le numéro associé au portefeuille
                        qui recevra les fonds.

                    </small>

                </div>


                <!--
                |--------------------------------------------------------------------------
                | MONTANT
                |--------------------------------------------------------------------------
                -->

                <div class="form-group">

                    <select name="balance_type" class="custom-select">
    <option value="balance">Solde principal</option>
    <option value="bonus_balance">Bonus parrainage</option>
</select>

                    <label for="amount">

                        <i class="fas fa-coins"></i>

                        Montant à retirer

                    </label>

                    <input
                        type="text"
                        inputmode="decimal"
                        class="form-control"
                        id="amount"
                        name="amount"
                        value="<?= htmlspecialchars($amountInput) ?>"
                        placeholder="Ex : 10000"
                        required
                    >

                    <small class="help-text">

                        Montant minimum :
                        <strong>
                            2 000 FCFA
                        </strong>

                    </small>

                </div>


                <!--
                |--------------------------------------------------------------------------
                | CALCUL
                |--------------------------------------------------------------------------
                -->

                <div class="calculation">

                    <div class="calculation-row">

                        <span>
                            Montant demandé
                        </span>

                        <strong id="displayAmount">
                            0 FCFA
                        </strong>

                    </div>


                    <div class="calculation-row">

                        <span>
                            Frais de retrait
                        </span>

                        <strong id="displayFee">
                            0 FCFA
                        </strong>

                    </div>


                    <div class="calculation-row total">

                        <span>
                            Vous recevrez
                        </span>

                        <strong id="displayNet">
                            0 FCFA
                        </strong>

                    </div>

                </div>


                <!--
                |--------------------------------------------------------------------------
                | BOUTON
                |--------------------------------------------------------------------------
                -->

                <button
                    type="submit"
                    class="submit-button"
                >

                    <i class="fas fa-paper-plane"></i>

                    Demander le retrait

                </button>


            </form>

        </section>


        <!--
        |--------------------------------------------------------------------------
        | INFORMATIONS
        |--------------------------------------------------------------------------
        -->

        <aside class="card">

            <div class="card-title">

                <div class="card-title-icon">

                    <i class="fas fa-circle-info"></i>

                </div>

                <div>

                    <h2>
                        Informations
                    </h2>

                    <p>
                        À connaître avant votre retrait.
                    </p>

                </div>

            </div>


            <div class="info-list">


                <div class="info-item">

                    <i class="fas fa-wallet"></i>

                    <div>

                        <strong>
                            Minimum de retrait
                        </strong>

                        <p>
                            Le montant minimum autorisé est
                            de 2 000 FCFA.
                        </p>

                    </div>

                </div>


                <div class="info-item">

                    <i class="fas fa-chart-line"></i>

                    <div>

                        <strong>
                            Investissement actif
                        </strong>

                        <p>
                            Vous devez avoir au moins un
                            investissement actif.
                        </p>

                    </div>

                </div>


                <div class="info-item">

                    <i class="fas fa-percent"></i>

                    <div>

                        <strong>
                            Frais de retrait
                        </strong>

                        <p>
                            Les frais sont calculés automatiquement.
                            Pour 4 000 FCFA, ils sont de 250 FCFA.
                        </p>

                    </div>

                </div>


                <div class="info-item">

                    <i class="fas fa-clock"></i>

                    <div>

                        <strong>
                            Délai de traitement
                        </strong>

                        <p>
                            Une demande est d'abord mise en attente.
                            Après validation, l'arrivée des fonds peut
                            prendre jusqu'à 24 heures ouvrées selon
                            le moyen de paiement.
                        </p>

                    </div>

                </div>


                <div class="info-item">

                    <i class="fas fa-shield-halved"></i>

                    <div>

                        <strong>
                            Sécurité
                        </strong>

                        <p>
                            Vérifiez attentivement le pays,
                            le moyen de paiement et le numéro
                            avant de confirmer.
                        </p>

                    </div>

                </div>


            </div>

        </aside>

    </div>


    <!--
    |--------------------------------------------------------------------------
    | HISTORIQUE
    |--------------------------------------------------------------------------
    -->

    <section class="card history-card">

        <div class="card-title">

            <div class="card-title-icon">

                <i class="fas fa-clock-rotate-left"></i>

            </div>

            <div>

                <h2>
                    Historique des retraits
                </h2>

                <p>
                    Retrouvez vos dernières demandes.
                </p>

            </div>

        </div>


        <?php if (!empty($withdrawals)): ?>

            <div class="withdrawal-list">

                <?php foreach ($withdrawals as $withdrawal): ?>

                    <?php

$statusKey = (string) ($withdrawal['status'] ?? 'pending');

$withdrawalStatus =
    $statusLabels[$statusKey]
    ?? [
        'label' => ucfirst($statusKey),
        'class' => 'pending',
        'icon' => 'fa-clock'
    ];


/*
|--------------------------------------------------------------------------
| PAYS
|--------------------------------------------------------------------------
*/

$withdrawalCountry =
    trim(
        (string) (
            $withdrawal['withdrawal_country']
            ?? ''
        )
    );

$historyCountry = null;

if (
    $withdrawalCountry !== ''
    &&
    isset(
        $paymentMethodsByCountry[
            $withdrawalCountry
        ]
    )
) {

    $historyCountry =
        $paymentMethodsByCountry[
            $withdrawalCountry
        ];

}

$countryLabel =
    '';

if ($historyCountry) {

    $countryLabel =
        ($historyCountry['flag'] ?? '')
        . ' '
        . ($historyCountry['name'] ?? '');

} elseif ($withdrawalCountry !== '') {

    $countryLabel =
        strtoupper($withdrawalCountry);

} else {

    $countryLabel =
        'Pays non renseigné';

}


/*
|--------------------------------------------------------------------------
| MOYEN DE PAIEMENT
|--------------------------------------------------------------------------
*/

$historyPaymentMethod =
    trim(
        (string) (
            $withdrawal['payment_method']
            ?? ''
        )
    );

if ($historyPaymentMethod === '') {

    $historyPaymentMethod =
        'Moyen non renseigné';

}


/*
|--------------------------------------------------------------------------
| NUMÉRO
|--------------------------------------------------------------------------
*/

$historyPhone =
    trim(
        (string) (
            $withdrawal['withdrawal_phone']
            ?? ''
        )
    );

if ($historyPhone === '') {

    $historyPhone =
        'Numéro non renseigné';

}


/*
|--------------------------------------------------------------------------
| RÉFÉRENCE
|--------------------------------------------------------------------------
*/

$historyReference =
    trim(
        (string) (
            $withdrawal['reference']
            ?? ''
        )
    );

if ($historyReference === '') {

    $historyReference =
        '—';

}


/*
|--------------------------------------------------------------------------
| MONTANTS
|--------------------------------------------------------------------------
*/

$historyAmount =
    (float) (
        $withdrawal['amount']
        ?? 0
    );

$historyFee =
    (float) (
        $withdrawal['fee']
        ?? 0
    );

$netHistoryAmount =
    $historyAmount
    - $historyFee;


/*
|--------------------------------------------------------------------------
| DATE
|--------------------------------------------------------------------------
*/

$createdAt =
    $withdrawal['created_at']
    ?? null;

$historyDate =
    $createdAt
    ? date(
        'd/m/Y à H:i',
        strtotime($createdAt)
    )
    : 'Date inconnue';

?>

                    <div class="withdrawal-item">


                        <div class="withdrawal-icon">

                            <i class="fas fa-money-bill-transfer"></i>

                        </div>


                        <div class="withdrawal-main">

                            <strong>

                               <?= htmlspecialchars(
    $historyPaymentMethod,
    ENT_QUOTES,
    'UTF-8'
) ?>
                            </strong>

                            <small>

                               <?= htmlspecialchars(
    $countryLabel,
    ENT_QUOTES,
    'UTF-8'
) ?>
                                ·

                               <?= htmlspecialchars(
    $historyPhone,
    ENT_QUOTES,
    'UTF-8'
) ?>
                            </small>

                            <small>

                                Réf.
                               <?= htmlspecialchars(
    $historyReference,
    ENT_QUOTES,
    'UTF-8'
) ?>
                            </small>

                            <small>

                               <?= htmlspecialchars(
    $historyDate,
    ENT_QUOTES,
    'UTF-8'
) ?>
                            </small>

                        </div>


                        <div class="withdrawal-amount">

                            <strong>

                                <?= number_format(
                                    (float) $withdrawal['amount'],
                                    0,
                                    ',',
                                    ' '
                                ) ?>

                                FCFA

                            </strong>

                            <small>

                                Frais :

                                <?= number_format(
                                    (float) $withdrawal['fee'],
                                    0,
                                    ',',
                                    ' '
                                ) ?>

                                FCFA

                            </small>

                            <small>

                                Net :

                                <?= number_format(
                                    $netHistoryAmount,
                                    0,
                                    ',',
                                    ' '
                                ) ?>

                                FCFA

                            </small>


                            <span
                                class="status <?= htmlspecialchars(
                                    $withdrawalStatus['class']
                                ) ?>"
                            >

                                <i class="fas <?= htmlspecialchars(
                                    $withdrawalStatus['icon']
                                ) ?>"></i>

                                <?= htmlspecialchars(
                                    $withdrawalStatus['label']
                                ) ?>

                            </span>

                        </div>


                    </div>

                <?php endforeach; ?>

            </div>

        <?php else: ?>

            <div class="empty-history">

                <i class="fas fa-money-bill-transfer"></i>

                <p>
                    Aucun retrait effectué pour le moment.
                </p>

            </div>

        <?php endif; ?>

    </section>

</div>


<script>

/*
|--------------------------------------------------------------------------
| DONNÉES DES MOYENS DE PAIEMENT
|--------------------------------------------------------------------------
*/

const paymentMethods = <?= json_encode(
    $paymentMethodsByCountry,
    JSON_UNESCAPED_UNICODE |
    JSON_UNESCAPED_SLASHES
) ?>;


/*
|--------------------------------------------------------------------------
| ÉLÉMENTS
|--------------------------------------------------------------------------
*/

const countrySelect =
    document.getElementById(
        'withdrawal_country'
    );

const methodSelect =
    document.getElementById(
        'payment_method'
    );

const phonePrefix =
    document.getElementById(
        'phonePrefix'
    );

const amountInput =
    document.getElementById(
        'amount'
    );

const displayAmount =
    document.getElementById(
        'displayAmount'
    );

const displayFee =
    document.getElementById(
        'displayFee'
    );

const displayNet =
    document.getElementById(
        'displayNet'
    );

const availableBalance =
    <?= json_encode($currentBalance) ?>;

const feeRate =
    <?= json_encode($withdrawalFeeRate) ?>;


/*
|--------------------------------------------------------------------------
| FORMAT FCFA
|--------------------------------------------------------------------------
*/

function formatFCFA(value) {

    return new Intl.NumberFormat(
        'fr-FR'
    ).format(
        Math.max(
            0,
            Math.round(value)
        )
    ) + ' FCFA';
}


/*
|--------------------------------------------------------------------------
| CALCUL FRAIS
|--------------------------------------------------------------------------
*/

function calculateFee(amount) {

    if (!amount || amount <= 0) {
        return 0;
    }

    let fee =
        amount * feeRate;

    /*
     * Arrondi à 50 FCFA.
     */
    fee =
        Math.round(fee / 50) * 50;

    return Math.max(
        0,
        fee
    );
}


/*
|--------------------------------------------------------------------------
| ACTUALISER LES MOYENS
|--------------------------------------------------------------------------
*/

function updatePaymentMethods() {

    const country =
        countrySelect.value;

    methodSelect.innerHTML =
        '<option value="">Sélectionner un moyen</option>';

    if (
        !country
        || !paymentMethods[country]
    ) {

        phonePrefix.textContent =
            '+237';

        return;
    }


    const countryData =
        paymentMethods[country];


    phonePrefix.textContent =
        countryData.flag
        + ' '
        + countryData.phone_code;


    Object.entries(
        countryData.methods
    ).forEach(
        ([value, label]) => {

            const option =
                document.createElement(
                    'option'
                );

            option.value =
                value;

            option.textContent =
                label;

            methodSelect.appendChild(
                option
            );
        }
    );
}


/*
|--------------------------------------------------------------------------
| ACTUALISER LE CALCUL
|--------------------------------------------------------------------------
*/

function updateCalculation() {

    let raw =
        amountInput.value
            .replace(/\s/g, '')
            .replace(',', '.');

    let amount =
        parseFloat(raw);


    if (
        Number.isNaN(amount)
        || amount <= 0
    ) {

        amount = 0;
    }


    const fee =
        calculateFee(amount);


    const net =
        Math.max(
            0,
            amount - fee
        );


    displayAmount.textContent =
        formatFCFA(amount);


    displayFee.textContent =
        formatFCFA(fee);


    displayNet.textContent =
        formatFCFA(net);


    /*
     * Indication visuelle si le solde
     * est insuffisant.
     */

    if (amount > availableBalance) {

        displayAmount.style.color =
            '#dc2626';

    } else {

        displayAmount.style.color =
            '';
    }
}


/*
|--------------------------------------------------------------------------
| ÉVÉNEMENTS
|--------------------------------------------------------------------------
*/

countrySelect.addEventListener(
    'change',
    updatePaymentMethods
);

amountInput.addEventListener(
    'input',
    updateCalculation
);


/*
|--------------------------------------------------------------------------
| INITIALISATION
|--------------------------------------------------------------------------
*/

updatePaymentMethods();
updateCalculation();

</script>


</body>

</html>
