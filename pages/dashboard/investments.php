<?php
session_start();

$success = $_SESSION['success'] ?? '';
unset($_SESSION['success']);

require_once '../../config/database.php';

require_once '../../config/user_preferences.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$user = getUserPreferences(
    $pdo,
    (int) $_SESSION['user_id']
);

$language = $user['language_code'] ?? 'fr';
$currency = $user['currency_code'] ?? 'XAF';

// Garder les préférences synchronisées
$_SESSION['language_code'] = $language;
$_SESSION['currency_code'] = $currency;

/*
|--------------------------------------------------------------------------
| AUTHENTIFICATION
|--------------------------------------------------------------------------
*/

if (!isLoggedIn()) {
    header("Location: ../login.php");
    exit;
}



if (!$user) {
    session_destroy();
    header("Location: ../login.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| DEVISE
|--------------------------------------------------------------------------
| Les plans sont enregistrés en XAF dans la base.
| La devise sert ici à l'affichage.
|--------------------------------------------------------------------------
*/

$currencyMap = [
    'XAF' => ['symbol' => 'FCFA', 'name' => 'Franc CFA'],
    'EUR' => ['symbol' => '€', 'name' => 'Euro'],
    'CHF' => ['symbol' => 'CHF', 'name' => 'Franc suisse'],
    'CAD' => ['symbol' => 'CA$', 'name' => 'Dollar canadien'],
    'USD' => ['symbol' => '$', 'name' => 'Dollar américain'],
    'GBP' => ['symbol' => '£', 'name' => 'Livre sterling'],
];

$currency = strtoupper($user['currency_code'] ?? 'XAF');

if (!isset($currencyMap[$currency])) {
    $currency = 'XAF';
}

$currencySymbol = $currencyMap[$currency]['symbol'];

/*
|--------------------------------------------------------------------------
| TAUX D'AFFICHAGE
|--------------------------------------------------------------------------
| Les investissements restent calculés en XAF.
| Ces taux servent uniquement à convertir l'affichage.
|--------------------------------------------------------------------------
*/

$exchangeRates = [
    'XAF' => 1,
    'EUR' => 0.001524,
    'CHF' => 0.00142,
    'CAD' => 0.00225,
    'USD' => 0.00165,
    'GBP' => 0.00131,
];

$exchangeRate = $exchangeRates[$currency] ?? 1;

/*
|--------------------------------------------------------------------------
| FONCTIONS
|--------------------------------------------------------------------------
*/

function formatMoney($amount, $currencySymbol, $exchangeRate)
{
    $converted = (float)$amount * $exchangeRate;

    if ($currencySymbol === 'FCFA') {
        return number_format($converted, 0, ',', ' ') . ' ' . $currencySymbol;
    }

    return number_format($converted, 2, ',', ' ') . ' ' . $currencySymbol;
}

function getLevelClass($level)
{
    if (stripos($level, 'Premium') !== false) {
        return 'premium';
    }

    if (stripos($level, 'Level 2') !== false) {
        return 'level-2';
    }

    return 'level-1';
}

/*
|--------------------------------------------------------------------------
| RÉCUPÉRER LES PLANS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        id,
        level,
        name,
        amount,
        return_amount,
        profit_amount,
        profit_percent,
        duration_days,
        daily_profit,
        description,
        is_active
    FROM investment_plans
    WHERE is_active = 1
    ORDER BY
        CASE
            WHEN level = 'Level 1' THEN 1
            WHEN level = 'Level 2' THEN 2
            WHEN level = 'Level 3 Premium' THEN 3
            ELSE 4
        END,
        amount ASC
");

$plans = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| INVESTISSEMENTS ACTUELS
|--------------------------------------------------------------------------
*/

$userId = (int)$user['id'];

$stmt = $pdo->prepare("
    SELECT
        COUNT(*) AS total,
        COALESCE(SUM(amount), 0) AS total_amount
    FROM investments
    WHERE user_id = ?
      AND status IN ('pending', 'active')
");

$stmt->execute([$userId]);

$currentInvestments = $stmt->fetch(PDO::FETCH_ASSOC);

$totalActiveInvestments = (int)($currentInvestments['total'] ?? 0);
$totalInvested = (float)($currentInvestments['total_amount'] ?? 0);

/*
|--------------------------------------------------------------------------
| TRAITEMENT D'INVESTISSEMENT
|--------------------------------------------------------------------------
*/

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['invest'])) {

    $planId = filter_input(INPUT_POST, 'plan_id', FILTER_VALIDATE_INT);

    if (!$planId) {
        $error = "Plan d'investissement invalide.";
    } else {

        try {

            /*
             * Récupérer le plan depuis la base.
             * On ne fait jamais confiance aux montants envoyés
             * par le navigateur.
             */
            $stmt = $pdo->prepare("
                SELECT *
                FROM investment_plans
                WHERE id = ?
                  AND is_active = 1
                LIMIT 1
            ");

            $stmt->execute([$planId]);

            $plan = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$plan) {
                throw new Exception("Ce plan n'est plus disponible.");
            }

            $amount = (float)$plan['amount'];
            $duration = (int)$plan['duration_days'];
            $returnAmount = (float)$plan['return_amount'];
            $profitAmount = (float)$plan['profit_amount'];

            /*
             * Récupérer le solde actuel directement depuis la DB.
             */
           /*
|--------------------------------------------------------------------------
| Vérifier si l'utilisateur possède déjà ce plan actif
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM investments
    WHERE user_id = ?
    AND plan_name = ?
    AND status = 'active'
");

$stmt->execute([
    $userId,
    $plan['name']
]);

if ($stmt->fetchColumn() > 0) {

    throw new Exception(
        "Vous avez déjà un investissement actif avec ce plan."
    );

}



/*
|--------------------------------------------------------------------------
| Vérification des données du plan
|--------------------------------------------------------------------------
*/

$amount = (float)$plan['amount'];

$duration = (int)$plan['duration_days'];

$returnAmount = (float)$plan['return_amount'];

$profitAmount = (float)$plan['profit_amount'];


if ($amount <= 0) {

    throw new Exception(
        "Montant d'investissement invalide."
    );

}



/*
|--------------------------------------------------------------------------
| Vérification du solde utilisateur
|--------------------------------------------------------------------------
*/

$pdo->beginTransaction();


$stmt = $pdo->prepare("
    SELECT balance, bonus_balance
    FROM users
    WHERE id = ?
    FOR UPDATE
");


$stmt->execute([
    $userId
]);


$currentUser = $stmt->fetch(PDO::FETCH_ASSOC);



if (!$currentUser) {

    throw new Exception(
        "Utilisateur introuvable."
    );

}



$balance = (float)$currentUser['balance'];

$bonusBalance = (float)$currentUser['bonus_balance'];



if ($balance < $amount) {

    throw new Exception(

        "Solde insuffisant. Votre solde est de " .
        formatMoney(
            $balance,
            $currencySymbol,
            $exchangeRate
        )

    );

}
            /*
             * Dates de l'investissement.
             */
            $startDate = date('Y-m-d H:i:s');
            $endDate = date(
                'Y-m-d H:i:s',
                strtotime("+{$duration} days")
            );

            /*
             * Débiter le compte.
             */
            $stmt = $pdo->prepare("
                UPDATE users
                SET balance = balance - ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");

            $stmt->execute([
                $amount,
                $userId
            ]);

            /*
             * Créer l'investissement.
             */
            $stmt = $pdo->prepare("
                INSERT INTO investments (
                    user_id,
                    plan_name,
                    amount,
                    profit_percent,
                    duration_days,
                    start_date,
                    end_date,
                    status,
                    total_profit
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, 'active', ?)
            ");

            $stmt->execute([
                $userId,
                $plan['name'],
                $amount,
                $plan['profit_percent'],
                $duration,
                $startDate,
                $endDate,
                $profitAmount
            ]);

            $investmentId = $pdo->lastInsertId();

            /*
             * Enregistrer la transaction.
             */
            $reference = 'INV-' . date('YmdHis') . '-' . $investmentId;

            $stmt = $pdo->prepare("
                INSERT INTO transactions (
                    user_id,
                    type,
                    amount,
                    fee,
                    status,
                    payment_method,
                    reference,
                    description,
                    completed_at
                )
                VALUES (
                    ?,
                    'investment',
                    ?,
                    0,
                    'completed',
                    'balance',
                    ?,
                    ?,
                    CURRENT_TIMESTAMP
                )
            ");

            $stmt->execute([
                $userId,
                $amount,
                $reference,
                'Investissement dans ' . $plan['name']
            ]);

            /*
             * Ajouter une notification.
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
                VALUES (?, 'success', ?, ?, 0, ?)
            ");

            $stmt->execute([
                $userId,
                'Investissement activé',
                'Votre investissement ' .
                $plan['name'] .
                ' de ' .
                formatMoney($amount, $currencySymbol, $exchangeRate) .
                ' est maintenant actif.',
                'investments.php'
            ]);

            $pdo->commit();

            $_SESSION['success'] =
    "Investissement activé avec succès ! Votre capital passera à " .
    formatMoney(
        $returnAmount,
        $currencySymbol,
        $exchangeRate
    ) .
    " à la fin du cycle.";

header("Location: investments.php");
exit;


        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $error = $e->getMessage();
        }
    }
}

/*
|--------------------------------------------------------------------------
| CALCUL AUTOMATIQUE DES PROFITS QUOTIDIENS
|--------------------------------------------------------------------------
*/

try {

    $stmt = $pdo->prepare("
        SELECT
            id,
            user_id,
            plan_name,
            total_profit,
            duration_days,
            start_date,
            end_date,
            status
        FROM investments
        WHERE user_id = ?
          AND status = 'active'
    ");

    $stmt->execute([$userId]);

    $activeInvestments = $stmt->fetchAll(PDO::FETCH_ASSOC);


    foreach ($activeInvestments as $investment) {

        $investmentId = (int)$investment['id'];

        $startDate = new DateTime(
            $investment['start_date']
        );

        $today = new DateTime();

        /*
        |--------------------------------------------------------------
        | Nombre de jours écoulés
        |--------------------------------------------------------------
        */

        $daysPassed =
            $startDate->diff($today)->days;

        $duration =
            (int)$investment['duration_days'];

        if ($daysPassed > $duration) {
            $daysPassed = $duration;
        }

        if ($daysPassed <= 0) {
            continue;
        }


        /*
        |--------------------------------------------------------------
        | GAIN QUOTIDIEN
        |--------------------------------------------------------------
        */

        $totalProfit =
            (float)$investment['total_profit'];

        $dailyProfit =
            $totalProfit / max(1, $duration);


        /*
        |--------------------------------------------------------------
        | ENREGISTRER CHAQUE JOUR MANQUANT
        |--------------------------------------------------------------
        */

        $profitDate = clone $startDate;

        $profitDate->modify('+1 day');


        for (
            $day = 1;
            $day <= $daysPassed;
            $day++
        ) {

            $dateString =
                $profitDate->format('Y-m-d');


            /*
            | Vérifier si ce jour a déjà été enregistré
            */

            $check = $pdo->prepare("
                SELECT id
                FROM investment_profit_logs
                WHERE investment_id = ?
                  AND profit_date = ?
                LIMIT 1
            ");

            $check->execute([
                $investmentId,
                $dateString
            ]);


            if ($check->fetchColumn()) {

                $profitDate->modify('+1 day');

                continue;
            }


            /*
            |----------------------------------------------------------
            | ENREGISTRER LE PROFIT
            |----------------------------------------------------------
            */

            $insert = $pdo->prepare("
                INSERT INTO investment_profit_logs (
                    investment_id,
                    user_id,
                    profit_date,
                    amount
                )
                VALUES (?, ?, ?, ?)
            ");

            $insert->execute([
                $investmentId,
                $userId,
                $dateString,
                $dailyProfit
            ]);


            /*
            |----------------------------------------------------------
            | NOTIFICATION
            |----------------------------------------------------------
            */

            $notification = $pdo->prepare("
                INSERT INTO notifications (
                    user_id,
                    type,
                    title,
                    message,
                    is_read,
                    link
                )
                VALUES (?, 'success', ?, ?, 0, ?)
            ");

            $notification->execute([

                $userId,

                'Nouveau profit',

                'Votre investissement ' .
                $investment['plan_name'] .
                ' a généré ' .
                formatMoney(
                    $dailyProfit,
                    $currencySymbol,
                    $exchangeRate
                ) .
                ' de profit.',

                'investments.php'

            ]);


            $profitDate->modify('+1 day');
        }
    }

} catch (Throwable $e) {

    error_log(
        'PROFIT AUTOMATIQUE : ' .
        $e->getMessage()
    );
}


/*
|--------------------------------------------------------------------------
| RÉCUPÉRER À NOUVEAU LES INVESTISSEMENTS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT *
    FROM investments
    WHERE user_id = ?
    ORDER BY created_at DESC
    LIMIT 10
");

$stmt->execute([$userId]);

$userInvestments = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| PROFIT ACCUMULÉ DE CHAQUE INVESTISSEMENT
|--------------------------------------------------------------------------
*/

foreach ($userInvestments as &$investment) {

    $stmtProfit = $pdo->prepare("
        SELECT COALESCE(SUM(amount), 0)
        FROM investment_profit_logs
        WHERE investment_id = ?
    ");

    $stmtProfit->execute([
        (int)$investment['id']
    ]);

    $investment['earned_profit'] =
        (float)$stmtProfit->fetchColumn();


    /*
    |--------------------------------------------------------------
    | GAIN QUOTIDIEN
    |--------------------------------------------------------------
    */

    $investment['daily_profit'] =
        (float)$investment['total_profit']
        / max(
            1,
            (int)$investment['duration_days']
        );
}

unset($investment);

?><!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Investissements - InvestPro</title>

<link
    rel="stylesheet"
    href="assets/css/style.css"
>

<link
    rel="stylesheet"
    href="assets/index.css"
>

<link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
>

<style>

    * {
        box-sizing: border-box;
    }

    body {
        margin: 0;
        background: #f5f8fc;
        color: #172033;
        font-family: Arial, Helvetica, sans-serif;
    }

    .dashboard-container {
        min-height: 100vh;
        display: flex;
    }

    /*
    |--------------------------------------------------------------------------
    | SIDEBAR
    |--------------------------------------------------------------------------
    */

    .sidebar {
        width: 250px;
        background: #ffffff;
        border-right: 1px solid #e6ebf2;
        min-height: 100vh;
        position: fixed;
        left: 0;
        top: 0;
        z-index: 100;
    }

    .sidebar-brand {
        height: 75px;
        display: flex;
        align-items: center;
        padding: 0 24px;
        border-bottom: 1px solid #edf1f6;
    }

    .brand-icon {
        font-size: 25px;
        margin-right: 9px;
    }

    .brand-text {
        font-size: 18px;
        font-weight: 700;
    }

    .brand-text span {
        color: #3478f6;
    }

    .sidebar-nav {
        padding: 20px 14px;
    }

    .sidebar-nav ul {
        list-style: none;
        margin: 0;
        padding: 0;
    }

    .sidebar-nav li {
        margin-bottom: 6px;
    }

    .sidebar-nav a {
        display: flex;
        align-items: center;
        gap: 13px;
        padding: 13px 15px;
        border-radius: 10px;
        color: #5e6878;
        text-decoration: none;
        font-size: 14px;
        transition: .2s;
    }

    .sidebar-nav a:hover,
    .sidebar-nav li.active a {
        background: #edf4ff;
        color: #3478f6;
    }

    .sidebar-nav i {
        width: 20px;
        text-align: center;
    }

    .logout {
        margin-top: 30px;
    }

    .logout a {
        color: #e05252;
    }

    /*
    |--------------------------------------------------------------------------
    | MAIN
    |--------------------------------------------------------------------------
    */

    .main-content {
        width: calc(100% - 250px);
        margin-left: 250px;
        min-height: 100vh;
    }

    .topbar {
        height: 75px;
        background: #ffffff;
        border-bottom: 1px solid #e6ebf2;
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0 35px;
    }

    .topbar h1 {
        margin: 0;
        font-size: 23px;
    }

    .topbar p {
        margin: 5px 0 0;
        color: #7a8494;
        font-size: 13px;
    }

    .user-box {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: #3478f6;
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
    }

    /*
    |--------------------------------------------------------------------------
    | CONTENT
    |--------------------------------------------------------------------------
    */

    .content {
        padding: 32px 35px 50px;
    }

    .page-intro {
        margin-bottom: 25px;
    }

    .page-intro h2 {
        margin: 0 0 8px;
        font-size: 27px;
    }

    .page-intro p {
        margin: 0;
        color: #748094;
    }

    /*
    |--------------------------------------------------------------------------
    | ALERTS
    |--------------------------------------------------------------------------
    */

    .alert {
        padding: 15px 18px;
        border-radius: 10px;
        margin-bottom: 25px;
        display: flex;
        gap: 10px;
        align-items: center;
    }

    .alert-success {
        background: #eaf8f0;
        color: #24784b;
        border: 1px solid #c8ecd7;
    }

    .alert-error {
        background: #fff0f0;
        color: #a43c3c;
        border: 1px solid #f2cccc;
    }

    /*
    |--------------------------------------------------------------------------
    | SUMMARY
    |--------------------------------------------------------------------------
    */

    .summary-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 18px;
        margin-bottom: 35px;
    }

    .summary-card {
        background: #ffffff;
        border: 1px solid #e6ebf2;
        border-radius: 15px;
        padding: 20px;
        display: flex;
        align-items: center;
        gap: 15px;
        box-shadow: 0 5px 20px rgba(30, 50, 80, .04);
    }

    .summary-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #edf4ff;
        color: #3478f6;
        font-size: 20px;
    }

    .summary-label {
        display: block;
        color: #7b8494;
        font-size: 12px;
        margin-bottom: 5px;
    }

    .summary-value {
        display: block;
        font-weight: 700;
        font-size: 18px;
    }

    /*
    |--------------------------------------------------------------------------
    | PLANS
    |--------------------------------------------------------------------------
    */

    .section-title {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 18px;
    }

    .section-title h2 {
        margin: 0;
        font-size: 21px;
    }

    .plans-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 22px;
    }

    .plan-card {
        background: #ffffff;
        border: 1px solid #e5eaf1;
        border-radius: 17px;
        padding: 24px;
        position: relative;
        overflow: hidden;
        box-shadow: 0 8px 25px rgba(28, 52, 84, .05);
        transition: transform .2s, box-shadow .2s;
    }

    .plan-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 14px 35px rgba(28, 52, 84, .10);
    }

    .plan-card::before {
        content: "";
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: #3478f6;
    }

    .plan-card.level-2::before {
        background: #7b61ff;
    }

    .plan-card.premium::before {
        background: #d59b2b;
    }

    .plan-level {
        display: inline-block;
        padding: 6px 10px;
        border-radius: 20px;
        background: #edf4ff;
        color: #3478f6;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
    }

    .level-2 .plan-level {
        background: #f0edff;
        color: #6953d7;
    }

    .premium .plan-level {
        background: #fff6df;
        color: #a77712;
    }

    .plan-name {
        font-size: 19px;
        margin: 16px 0 8px;
    }

    .plan-description {
        min-height: 42px;
        color: #7a8494;
        font-size: 13px;
        line-height: 1.6;
    }

    .plan-investment {
        margin-top: 20px;
        padding: 17px;
        background: #f7f9fc;
        border-radius: 12px;
    }

    .amount-label {
        display: block;
        color: #7b8494;
        font-size: 11px;
        margin-bottom: 5px;
    }

    .amount-value {
        font-size: 25px;
        font-weight: 800;
    }

    .return-row {
        display: flex;
        justify-content: space-between;
        margin-top: 18px;
        padding-bottom: 15px;
        border-bottom: 1px solid #e7ebf1;
    }

    .return-item span {
        display: block;
    }

    .return-label {
        font-size: 11px;
        color: #8992a0;
        margin-bottom: 4px;
    }

    .return-value {
        font-weight: 700;
    }

    .profit-value {
        color: #24945a;
    }

    .plan-meta {
        display: flex;
        justify-content: space-between;
        margin: 17px 0;
        font-size: 12px;
        color: #697587;
    }

    .plan-meta i {
        margin-right: 5px;
        color: #3478f6;
    }

    .daily-profit {
        padding: 10px 12px;
        border-radius: 9px;
        background: #effaf4;
        color: #278353;
        font-size: 12px;
        margin-bottom: 15px;
    }

    .invest-btn {
        width: 100%;
        border: 0;
        border-radius: 10px;
        padding: 13px;
        background: #3478f6;
        color: white;
        font-weight: 700;
        cursor: pointer;
        transition: .2s;
    }

    .invest-btn:hover {
        background: #2567df;
    }

    .level-2 .invest-btn {
        background: #6953d7;
    }

    .premium .invest-btn {
        background: #bd8a20;
    }

    /*
    |--------------------------------------------------------------------------
    | CURRENT INVESTMENTS
    |--------------------------------------------------------------------------
    */

    .my-investments {
        margin-top: 45px;
    }

    .investment-table-wrapper {
        overflow-x: auto;
        background: #fff;
        border: 1px solid #e5eaf1;
        border-radius: 15px;
    }

    .investment-table {
        width: 100%;
        border-collapse: collapse;
        min-width: 750px;
    }

    .investment-table th {
        text-align: left;
        padding: 15px;
        background: #f7f9fc;
        color: #687386;
        font-size: 12px;
    }

    .investment-table td {
        padding: 16px 15px;
        border-top: 1px solid #edf0f4;
        font-size: 13px;
    }

    .status {
        display: inline-block;
        padding: 5px 9px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 700;
    }

    .status-active {
        background: #e9f8ef;
        color: #23804d;
    }

    .status-completed {
        background: #e9f0ff;
        color: #3268bf;
    }

    .status-pending {
        background: #fff5df;
        color: #a26d10;
    }

    /*
    |--------------------------------------------------------------------------
    | RESPONSIVE
    |--------------------------------------------------------------------------
    */

    @media (max-width: 1100px) {

        .plans-grid {
            grid-template-columns: repeat(2, 1fr);
        }

    }

    @media (max-width: 800px) {

        .sidebar {
            width: 70px;
        }

        .sidebar-brand {
            justify-content: center;
            padding: 0;
        }

        .brand-text {
            display: none;
        }

        .sidebar-nav a {
            justify-content: center;
            padding: 13px;
        }


        .main-content {
            width: calc(100% - 70px);
            margin-left: 70px;
        }

        .plans-grid,
        .summary-grid {
            grid-template-columns: 1fr;
        }

        .content {
            padding: 25px 18px;
        }

        .topbar {
            padding: 0 18px;
        }

    }

    @media (max-width: 500px) {

        .topbar h1 {
            font-size: 18px;
        }

        .topbar p {
            display: none;
        }

        .user-details {
            display: none;
        }

        .page-intro h2 {
            font-size: 23px;
        }

    }

/* =========================================================
   MENU HAMBURGER MOBILE
   ========================================================= */

.menu-toggle {
    display: none;
}

.sidebar-mobile-header {
    display: none;
}

.sidebar-overlay {
    display: none;
}

/* =========================
   TABLETTE / MOBILE
   ========================= */

@media (max-width: 900px) {

    .menu-toggle {
        position: fixed;
        top: 16px;
        left: 16px;

        width: 46px;
        height: 46px;

        display: flex;
        align-items: center;
        justify-content: center;

        border: 1px solid #e5eaf1;
        border-radius: 12px;

        background: #ffffff;
        color: #172033;

        box-shadow: 0 5px 18px rgba(30, 50, 80, .10);

        font-size: 20px;

        cursor: pointer;

        z-index: 2000;

        transition: .2s;
    }

    .menu-toggle:hover {
        background: #f5f8fc;
        transform: translateY(-1px);
    }

    /*
    SIDEBAR
    */

    .sidebar {
        position: fixed;

        top: 0;
        left: 0;
        bottom: 0;

        width: 280px;

        min-height: 100vh;

        background: #ffffff;

        transform: translateX(-105%);

        transition: transform .3s ease;

        z-index: 1900;

        overflow-y: auto;

        box-shadow: 8px 0 30px rgba(15, 23, 42, .12);
    }

    .sidebar.open {
        transform: translateX(0);
    }

    /*
    HEADER MOBILE DU MENU
    */

    .sidebar-mobile-header {
        height: 75px;

        padding: 0 18px;

        display: flex;

        align-items: center;

        justify-content: space-between;

        border-bottom: 1px solid #edf1f6;
    }

    .sidebar-mobile-title {
        display: flex;
        align-items: center;
    }

    .sidebar-mobile-title .brand-icon {
        font-size: 23px;
        margin-right: 8px;
    }

    .sidebar-mobile-title .brand-text {
        display: block;
        font-size: 18px;
        font-weight: 700;
    }

    .sidebar-mobile-title .brand-text span {
        color: #3478f6;
    }

    /*
    BOUTON FERMER
    */

    .sidebar-close {
        width: 38px;
        height: 38px;

        display: flex;

        align-items: center;
        justify-content: center;

        border: 0;
        border-radius: 10px;

        background: #f4f6f9;
        color: #475569;

        font-size: 17px;

        cursor: pointer;

        transition: .2s;
    }

    .sidebar-close:hover {
        background: #edf1f6;
        color: #e05252;
    }

    /*
    ON CACHE L'ANCIEN BRAND SUR MOBILE
    */

    .sidebar > .sidebar-brand {
        display: none;
    }

    /*
    NAVIGATION
    */

    .sidebar-nav {
        padding: 20px 14px;
    }

    .sidebar-nav a {
        padding: 14px 15px;
        font-size: 14px;
    }

    /*
    OVERLAY
    */

    .sidebar-overlay {
        position: fixed;

        inset: 0;

        display: block;

        background: rgba(15, 23, 42, .35);

        backdrop-filter: blur(2px);

        opacity: 0;
        visibility: hidden;

        transition:
            opacity .3s ease,
            visibility .3s ease;

        z-index: 1800;
    }

    .sidebar-overlay.show {
        opacity: 1;
        visibility: visible;
    }

    /*
    MAIN
    */

    .main-content {
        width: 100%;
        margin-left: 0;
    }

    /*
    LE BOUTON NE DOIT PAS CACHER LE TITRE
    */

    .topbar {
        padding-left: 78px;
    }

    /*
    BLOQUER LE SCROLL QUAND LE MENU EST OUVERT
    */

    body.menu-open {
        overflow: hidden;
    }
}


/* =========================================================
   PETITS ÉCRANS
   ========================================================= */

@media (max-width: 500px) {

    .menu-toggle {
        top: 12px;
        left: 12px;

        width: 42px;
        height: 42px;
    }

    .sidebar {
        width: min(285px, 86vw);
    }

    .topbar {
        padding-left: 68px;
        padding-right: 15px;
    }

}
</style>

</head><body><div class="dashboard-container"><!-- SIDEBAR -->

<!-- Bouton hamburger -->
<button class="menu-toggle" id="menuToggle" aria-label="Ouvrir le menu">
    <i class="fas fa-bars"></i>
</button>

<!-- Overlay mobile -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
    
    <div class="sidebar-brand">

        <span class="brand-icon">📈</span>

        <span class="brand-text">
            Invest<span>Pro</span>
        </span>

        <!-- Bouton fermeture -->
        <button class="sidebar-close" id="sidebarClose" aria-label="Fermer le menu">
            <i class="fas fa-times"></i>
        </button>

    </div>

    <nav class="sidebar-nav">

        <ul>

            <li>
                <a href="index.php">
                    <i class="fas fa-home"></i>
                    <span>Tableau de bord</span>
                </a>
            </li>

            <li class="active">
                <a href="investments.php">
                    <i class="fas fa-chart-line"></i>
                    <span>Investissements</span>
                </a>
            </li>

            <li>
                <a href="deposit.php">
                    <i class="fas fa-wallet"></i>
                    <span>Déposer</span>
                </a>
            </li>

            <li>
                <a href="withdraw.php">
                    <i class="fas fa-arrow-up"></i>
                    <span>Retirer</span>
                </a>
            </li>

            <li>
                <a href="transactions.php">
                    <i class="fas fa-exchange-alt"></i>
                    <span>Transactions</span>
                </a>
            </li>

            <li>
                <a href="support.php">
                    <i class="fas fa-headset"></i>
                    <span>Support</span>
                </a>
            </li>

            <li>
                <a href="settings.php">
                    <i class="fas fa-cog"></i>
                    <span>Paramètres</span>
                </a>
            </li>

            <li class="logout">
                <a href="../logout.php">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Déconnexion</span>
                </a>
            </li>

        </ul>

    </nav>

</aside>
<!-- MAIN -->

<main class="main-content">

    <header class="topbar">

        <div>
            <h1>Investissements</h1>

            <p>
                Choisissez le plan qui correspond à vos objectifs.
            </p>
        </div>

        <div class="user-box">

            <div class="avatar">
                <?php
                echo strtoupper(
                    substr($user['username'], 0, 1)
                );
                ?>
            </div>

            <div class="user-details">

                <strong>
                    <?php
                    echo htmlspecialchars($user['username']);
                    ?>
                </strong>

                <small>
                    <?php echo htmlspecialchars($currency); ?>
                </small>

            </div>

        </div>

    </header>


    <div class="content">

        <div class="page-intro">

            <h2>
                Plans d'investissement
            </h2>

            <p>
                Consultez les conditions, le rendement prévu et la durée
                de chaque plan avant de confirmer votre investissement.
            </p>

        </div>


        <?php if ($success): ?>

            <div class="alert alert-success">

                <i class="fas fa-check-circle"></i>

                <span>
                    <?php echo htmlspecialchars($success); ?>
                </span>

            </div>

        <?php endif; ?>


        <?php if ($error): ?>

            <div class="alert alert-error">

                <i class="fas fa-exclamation-circle"></i>

                <span>
                    <?php echo htmlspecialchars($error); ?>
                </span>

            </div>

        <?php endif; ?>


        <!-- SUMMARY -->

        <div class="summary-grid">

            <div class="summary-card">

                <div class="summary-icon">
                    <i class="fas fa-wallet"></i>
                </div>

                <div>
                    <span class="summary-label">
                        Solde disponible
                    </span>

                    <span class="summary-value">
                        <?php
                        echo formatMoney(
                            $user['balance'],
                            $currencySymbol,
                            $exchangeRate
                        );
                        ?>
                    </span>
                </div>

            </div>


            <div class="summary-card">

                <div class="summary-icon">
                    <i class="fas fa-chart-line"></i>
                </div>

                <div>
                    <span class="summary-label">
                        Investissements actifs
                    </span>

                    <span class="summary-value">
                        <?php echo $totalActiveInvestments; ?>
                    </span>
                </div>

            </div>


            <div class="summary-card">

                <div class="summary-icon">
                    <i class="fas fa-coins"></i>
                </div>

                <div>
                    <span class="summary-label">
                        Capital investi
                    </span>

                    <span class="summary-value">
                        <?php
                        echo formatMoney(
                            $totalInvested,
                            $currencySymbol,
                            $exchangeRate
                        );
                        ?>
                    </span>
                </div>

            </div>

        </div>


        <!-- PLANS -->

        <div class="section-title">

            <h2>
                <i class="fas fa-layer-group"></i>
                Nos plans
            </h2>

        </div>


        <div class="plans-grid">

            <?php foreach ($plans as $plan): ?>

                <?php
                $levelClass = getLevelClass($plan['level']);
                ?>

                <article class="plan-card <?php echo $levelClass; ?>">

                    <span class="plan-level">
                        <?php
                        echo htmlspecialchars($plan['level']);
                        ?>
                    </span>

                    <h3 class="plan-name">
                        <?php
                        echo htmlspecialchars($plan['name']);
                        ?>
                    </h3>

                    <p class="plan-description">
                        <?php
                        echo htmlspecialchars($plan['description']);
                        ?>
                    </p>


                    <div class="plan-investment">

                        <span class="amount-label">
                            Montant à investir
                        </span>

                        <span class="amount-value">
                            <?php
                            echo formatMoney(
                                $plan['amount'],
                                $currencySymbol,
                                $exchangeRate
                            );
                            ?>
                        </span>

                    </div>


                    <div class="return-row">

                        <div class="return-item">

                            <span class="return-label">
                                Retour
                            </span>

                            <span class="return-value">
                                <?php
                                echo formatMoney(
                                    $plan['return_amount'],
                                    $currencySymbol,
                                    $exchangeRate
                                );
                                ?>
                            </span>

                        </div>


                        <div class="return-item">

                            <span class="return-label">
                                Profit
                            </span>

                            <span class="return-value profit-value">
                                +<?php
                                echo formatMoney(
                                    $plan['profit_amount'],
                                    $currencySymbol,
                                    $exchangeRate
                                );
                                ?>
                            </span>

                        </div>

                    </div>


                    <div class="plan-meta">

                        <span>
                            <i class="fas fa-calendar-alt"></i>
                            <?php echo $plan['duration_days']; ?> jours
                        </span>

                        <span>
                            <i class="fas fa-percentage"></i>
                            <?php
                            echo number_format(
                                $plan['profit_percent'],
                                2,
                                ',',
                                ' '
                            );
                            ?>%
                        </span>

                    </div>


                    <div class="daily-profit">

                        <i class="fas fa-chart-line"></i>

                        Gain quotidien indicatif :
                        <strong>
                            <?php
                            echo formatMoney(
                                $plan['daily_profit'],
                                $currencySymbol,
                                $exchangeRate
                            );
                            ?>
                        </strong>

                    </div>


                   <form method="POST">

    <input 
        type="hidden" 
        name="plan_id" 
        value="<?= (int)$plan['id']; ?>"
    >

    <button
        type="submit"
        name="invest"
        class="invest-btn"
        onclick="return confirmInvest(
            '<?= htmlspecialchars($plan['name']) ?>',
            '<?= formatMoney($plan['amount'], $currencySymbol, $exchangeRate) ?>'
        );"
    >

        <i class="fas fa-chart-line"></i>
        Investir maintenant

    </button>

</form>
                </article>

            <?php endforeach; ?>

        </div>


        <!-- MES INVESTISSEMENTS -->

        <section class="my-investments">

            <div class="section-title">

                <h2>
                    <i class="fas fa-history"></i>
                    Mes investissements
                </h2>

            </div>


            <?php if ($userInvestments): ?>

                <div class="investment-table-wrapper">

                    <table class="investment-table">

                        <thead>

                            <tr>

                                <th>Plan</th>
                                <th>Montant</th>
                                <th>Profit prévu</th>
                                <th>Durée</th>
                                <th>Début</th>
                                <th>Fin</th>
                                <th>Statut</th>

                            </tr>

                        </thead>

                        <tbody>

                        <?php foreach ($userInvestments as $investment): ?>

<?php

$start = new DateTime($investment['start_date']);
$now = new DateTime();

$daysPassed = $start->diff($now)->days;


if ($daysPassed > $investment['duration_days']) {
    $daysPassed = $investment['duration_days'];
}


$daysRemaining =
    $investment['duration_days']
    -
    $daysPassed;


if ($daysRemaining < 0) {
    $daysRemaining = 0;
}

?>



                            <tr>

                               <td>

<a href="investment-details.php?id=<?= (int)$investment['id'] ?>">

<strong>
<?= htmlspecialchars($investment['plan_name']) ?>
</strong>

</a>

</td>
                                <td>
                                    <?php
                                    echo formatMoney(
                                        $investment['amount'],
                                        $currencySymbol,
                                        $exchangeRate
                                    );
                                    ?>
                                </td>

                                <td class="profit-value">

    <strong>
        +<?= formatMoney(
            $investment['daily_profit'],
            $currencySymbol,
            $exchangeRate
        ); ?>
    </strong>

    <br>

    <small>
        Gain quotidien
    </small>

</td>

<td class="profit-value">

    <strong>
        +<?= formatMoney(
            $investment['earned_profit'],
            $currencySymbol,
            $exchangeRate
        ); ?>
    </strong>

    <br>

    <small>
        Profit accumulé
    </small>

</td>
                               <td>
    <strong>
        <?= $daysPassed ?> / <?= $investment['duration_days'] ?>
    </strong>
    <br>

    <small>
        <?= $daysRemaining ?> jours restants
    </small>
</td>
                                <td>
                                    <?php
                                    echo date(
                                        'd/m/Y',
                                        strtotime(
                                            $investment['start_date']
                                        )
                                    );
                                    ?>
                                </td>

                                <td>
                                    <?php
                                    echo date(
                                        'd/m/Y',
                                        strtotime(
                                            $investment['end_date']
                                        )
                                    );
                                    ?>
                                </td>

                                <td>

                                    <?php
                                    $status = $investment['status'];

                                    $statusClass =
                                        'status-' . $status;

                                    $statusLabels = [
                                        'active' => 'Actif',
                                        'completed' => 'Terminé',
                                        'pending' => 'En attente',
                                        'cancelled' => 'Annulé'
                                    ];

                                    $statusLabel =
                                        $statusLabels[$status]
                                        ?? $status;
                                    ?>

                                    <span
                                        class="status <?php
                                            echo $statusClass;
                                        ?>"
                                    >
                                        <?php
                                        echo htmlspecialchars(
                                            $statusLabel
                                        );
                                        ?>
                                    </span>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php else: ?>

                <div class="summary-card">

                    <div class="summary-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>

                    <div>

                        <strong>
                            Aucun investissement
                        </strong>

                        <span class="summary-label">
                            Choisissez un plan ci-dessus pour commencer.
                        </span>

                    </div>

                </div>

            <?php endif; ?>

        </section>

    </div>

</main>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {

    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.querySelector('.sidebar');
    const sidebarClose = document.getElementById('sidebarClose');
    const overlay = document.getElementById('sidebarOverlay');

    function openMenu() {
        sidebar.classList.add('open');
        overlay.classList.add('show');
        document.body.classList.add('menu-open');

        menuToggle.innerHTML = '<i class="fas fa-times"></i>';
    }

    function closeMenu() {
        sidebar.classList.remove('open');
        overlay.classList.remove('show');
        document.body.classList.remove('menu-open');

        menuToggle.innerHTML = '<i class="fas fa-bars"></i>';
    }

    menuToggle.addEventListener('click', function () {

        if (sidebar.classList.contains('open')) {
            closeMenu();
        } else {
            openMenu();
        }

    });

    sidebarClose.addEventListener('click', function () {
        closeMenu();
    });

    overlay.addEventListener('click', function () {
        closeMenu();
    });

    /*
     * Fermer le menu après avoir choisi une page
     */

    document.querySelectorAll('.sidebar-nav a').forEach(function (link) {

        link.addEventListener('click', function () {

            if (window.innerWidth <= 900) {
                closeMenu();
            }

        });

    });

    /*
     * Si on repasse en mode desktop
     */

    window.addEventListener('resize', function () {

        if (window.innerWidth > 900) {
            closeMenu();
        }

    });

});



function confirmInvest(plan, amount){

    return confirm(
        "Confirmer votre investissement ?\n\n" +
        "Plan : " + plan +
        "\nMontant : " + amount
    );

}

</script>


</body>
</html>
