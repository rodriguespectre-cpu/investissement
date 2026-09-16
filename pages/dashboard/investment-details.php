<?php

session_start();

require_once '../../config/database.php';


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
| ID INVESTISSEMENT
|--------------------------------------------------------------------------
*/

$id = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (!$id) {
    header("Location: investments.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| RÉCUPÉRER L'INVESTISSEMENT
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT *
    FROM investments
    WHERE id = ?
      AND user_id = ?
    LIMIT 1
");

$stmt->execute([
    $id,
    $userId
]);

$investment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$investment) {
    header("Location: investments.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| DEVISE
|--------------------------------------------------------------------------
*/

$currencyMap = [

    'XAF' => [
        'symbol' => 'FCFA',
        'name'   => 'Franc CFA'
    ],

    'EUR' => [
        'symbol' => '€',
        'name'   => 'Euro'
    ],

    'CHF' => [
        'symbol' => 'CHF',
        'name'   => 'Franc suisse'
    ],

    'CAD' => [
        'symbol' => 'CA$',
        'name'   => 'Dollar canadien'
    ],

    'USD' => [
        'symbol' => '$',
        'name'   => 'Dollar américain'
    ],

    'GBP' => [
        'symbol' => '£',
        'name'   => 'Livre sterling'
    ]

];

$currencyCode = strtoupper(
    $investment['currency_code'] ?? 'XAF'
);

if (!isset($currencyMap[$currencyCode])) {
    $currencyCode = 'XAF';
}

$currencySymbol =
    $currencyMap[$currencyCode]['symbol'];


/*
|--------------------------------------------------------------------------
| TAUX DE CONVERSION
|--------------------------------------------------------------------------
|
| Les montants de la base sont conservés en XAF.
|--------------------------------------------------------------------------
*/

$exchangeRates = [

    'XAF' => 1,

    'EUR' => 0.001524,

    'CHF' => 0.00142,

    'CAD' => 0.00225,

    'USD' => 0.00165,

    'GBP' => 0.00131

];

$exchangeRate =
    $exchangeRates[$currencyCode] ?? 1;


/*
|--------------------------------------------------------------------------
| FORMATAGE MONÉTAIRE
|--------------------------------------------------------------------------
*/

function formatInvestmentMoney(
    $amount,
    $currencySymbol,
    $exchangeRate
) {

    $converted =
        (float) $amount * (float) $exchangeRate;

    if ($currencySymbol === 'FCFA') {

        return number_format(
            $converted,
            0,
            ',',
            ' '
        ) . ' FCFA';
    }

    return number_format(
        $converted,
        2,
        ',',
        ' '
    ) . ' ' . $currencySymbol;
}


/*
|--------------------------------------------------------------------------
| DATES
|--------------------------------------------------------------------------
*/

try {

    $startDate = new DateTime(
        $investment['start_date']
    );

    $now = new DateTime();

} catch (Exception $e) {

    header("Location: investments.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| DURÉE
|--------------------------------------------------------------------------
*/

$durationDays = max(
    0,
    (int) ($investment['duration_days'] ?? 0)
);


/*
|--------------------------------------------------------------------------
| PROGRESSION
|--------------------------------------------------------------------------
*/

$daysPassed =
    $startDate->diff($now)->days;

if ($daysPassed < 0) {
    $daysPassed = 0;
}

if ($daysPassed > $durationDays) {
    $daysPassed = $durationDays;
}


/*
|--------------------------------------------------------------------------
| JOURS RESTANTS
|--------------------------------------------------------------------------
*/

$daysRemaining =
    $durationDays - $daysPassed;

if ($daysRemaining < 0) {
    $daysRemaining = 0;
}


/*
|--------------------------------------------------------------------------
| PROGRESSION %
|--------------------------------------------------------------------------
*/

if ($durationDays > 0) {

    $progress =
        ($daysPassed / $durationDays) * 100;

} else {

    $progress = 0;
}

$progress =
    max(0, min(100, $progress));


/*
|--------------------------------------------------------------------------
| PROFIT TOTAL PRÉVU
|--------------------------------------------------------------------------
*/

$totalExpectedProfit =
    (float) (
        $investment['total_profit'] ?? 0
    );


/*
|--------------------------------------------------------------------------
| GAIN QUOTIDIEN
|--------------------------------------------------------------------------
*/

if ($durationDays > 0) {

    $dailyProfit =
        $totalExpectedProfit / $durationDays;

} else {

    $dailyProfit = 0;
}


/*
|--------------------------------------------------------------------------
| PROFIT ACCUMULÉ THÉORIQUE
|--------------------------------------------------------------------------
*/

$accumulatedProfit =
    $dailyProfit * $daysPassed;

if ($accumulatedProfit > $totalExpectedProfit) {

    $accumulatedProfit =
        $totalExpectedProfit;
}


/*
|--------------------------------------------------------------------------
| GAIN DU JOUR
|--------------------------------------------------------------------------
*/

$currentDayProfit =
    $dailyProfit;


/*
|--------------------------------------------------------------------------
| CYCLE TERMINÉ
|--------------------------------------------------------------------------
*/

if (
    $durationDays > 0
    && $daysPassed >= $durationDays
) {

    $currentDayProfit =
        $totalExpectedProfit
        -
        (
            $dailyProfit *
            ($durationDays - 1)
        );
}


/*
|--------------------------------------------------------------------------
| PROFIT RÉELLEMENT CRÉDITÉ
|--------------------------------------------------------------------------
|
| IMPORTANT :
|
| balances.type  = credit
| balances.source = investment_profit
| balances.reference_id = ID investissement
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        COALESCE(SUM(amount), 0) AS real_profit
    FROM balances
    WHERE user_id = ?
      AND type = 'credit'
      AND source = 'investment_profit'
      AND reference_id = ?
");

$stmt->execute([
    $userId,
    $id
]);

$balanceResult =
    $stmt->fetch(PDO::FETCH_ASSOC);

$realProfitReceived =
    (float) (
        $balanceResult['real_profit'] ?? 0
    );


/*
|--------------------------------------------------------------------------
| PROFIT RESTANT
|--------------------------------------------------------------------------
*/

$remainingProfit =
    $totalExpectedProfit
    -
    $realProfitReceived;

if ($remainingProfit < 0) {
    $remainingProfit = 0;
}


/*
|--------------------------------------------------------------------------
| PROFIT NON ENCORE CRÉDITÉ
|--------------------------------------------------------------------------
*/

$profitNotReceived =
    $accumulatedProfit
    -
    $realProfitReceived;

if ($profitNotReceived < 0) {
    $profitNotReceived = 0;
}


/*
|--------------------------------------------------------------------------
| POURCENTAGE DU PROFIT RÉELLEMENT REÇU
|--------------------------------------------------------------------------
*/

if ($totalExpectedProfit > 0) {

    $profitReceivedPercent =
        (
            $realProfitReceived
            /
            $totalExpectedProfit
        ) * 100;

} else {

    $profitReceivedPercent = 0;
}

$profitReceivedPercent =
    max(
        0,
        min(100, $profitReceivedPercent)
    );


/*
|--------------------------------------------------------------------------
| TOTAL RÉEL REÇU
|--------------------------------------------------------------------------
*/

$realTotalReceived =
    (float) ($investment['amount'] ?? 0)
    +
    $realProfitReceived;


/*
|--------------------------------------------------------------------------
| STATUT
|--------------------------------------------------------------------------
*/

$status =
    $investment['status'] ?? 'active';

$statusLabels = [

    'pending'   => 'En attente',

    'active'    => 'Actif',

    'completed' => 'Terminé',

    'cancelled' => 'Annulé'

];

$statusLabel =
    $statusLabels[$status]
    ?? ucfirst($status);

$statusClass =
    'status-' . $status;


/*
|--------------------------------------------------------------------------
| DATES FORMATÉES
|--------------------------------------------------------------------------
*/

$startTimestamp =
    strtotime($investment['start_date']);

$endTimestamp =
    strtotime($investment['end_date']);

$startDateFormatted =
    $startTimestamp
        ? date('d/m/Y à H:i', $startTimestamp)
        : '—';

$endDateFormatted =
    $endTimestamp
        ? date('d/m/Y à H:i', $endTimestamp)
        : '—';


/*
|--------------------------------------------------------------------------
| NOTIFICATION DU GAIN
|--------------------------------------------------------------------------
|
| La table notifications.type accepte uniquement :
|
| info
| success
| warning
| error
|
| On utilise donc "success".
|
| Le lien contient le jour afin d'éviter
| de créer plusieurs notifications pour
| le même investissement et le même jour.
|--------------------------------------------------------------------------
*/

if (
    $status === 'active'
    && $daysPassed > 0
    && $durationDays > 0
    && $currentDayProfit > 0
) {

    $notificationLink =
        'investment-details.php?id='
        . (int) $id
        . '&day='
        . (int) $daysPassed;


    /*
    |--------------------------------------------------------------------------
    | Vérifier si la notification existe
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT id
        FROM notifications
        WHERE user_id = ?
          AND type = 'success'
          AND link = ?
        LIMIT 1
    ");

    $stmt->execute([
        $userId,
        $notificationLink
    ]);

    $notificationExists =
        $stmt->fetch(PDO::FETCH_ASSOC);


    /*
    |--------------------------------------------------------------------------
    | Créer la notification
    |--------------------------------------------------------------------------
    */

    if (!$notificationExists) {

        $notificationTitle =
            'Gain quotidien';

        $notificationMessage =
            'Votre investissement '
            . $investment['plan_name']
            . ' a généré '
            . formatInvestmentMoney(
                $currentDayProfit,
                $currencySymbol,
                $exchangeRate
            )
            . ' pour le jour '
            . $daysPassed
            . '.';

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
                'success',
                ?,
                ?,
                0,
                ?
            )
        ");

        $stmt->execute([
            $userId,
            $notificationTitle,
            $notificationMessage,
            $notificationLink
        ]);
    }
}


/*
|--------------------------------------------------------------------------
| HISTORIQUE DES GAINS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        amount,
        description,
        created_at
    FROM balances
    WHERE user_id = ?
      AND type = 'credit'
      AND source = 'investment_profit'
      AND reference_id = ?
    ORDER BY created_at DESC
");

$stmt->execute([
    $userId,
    $id
]);

$profitHistory =
    $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    Détails investissement -
    <?= htmlspecialchars(
        $investment['plan_name'],
        ENT_QUOTES,
        'UTF-8'
    ) ?>
</title>

<link
    rel="stylesheet"
    href="assets/investment-details.css"
>

<link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
>

</head>


<body>


<div class="content">


<!--
|--------------------------------------------------------------------------
| HEADER
|--------------------------------------------------------------------------
-->

<div class="page-header">

    <a
        href="investments.php"
        class="back-button"
    >

        <i class="fas fa-arrow-left"></i>

        Retour

    </a>


    <div>

        <span class="page-label">
            INVESTISSEMENT
        </span>

        <h1>

            <?= htmlspecialchars(
                $investment['plan_name'],
                ENT_QUOTES,
                'UTF-8'
            ) ?>

        </h1>

    </div>

</div>


<!--
|--------------------------------------------------------------------------
| STATUT
|--------------------------------------------------------------------------
-->

<div class="status-banner">

    <div>

        <span class="status-label">
            Statut
        </span>

        <strong
            class="<?= htmlspecialchars(
                $statusClass,
                ENT_QUOTES,
                'UTF-8'
            ) ?>"
        >

            <?= htmlspecialchars(
                $statusLabel,
                ENT_QUOTES,
                'UTF-8'
            ) ?>

        </strong>

    </div>


    <div>

        <span class="status-label">
            Devise
        </span>

        <strong>

            <?= htmlspecialchars(
                $currencyCode,
                ENT_QUOTES,
                'UTF-8'
            ) ?>

        </strong>

    </div>

</div>


<!--
|--------------------------------------------------------------------------
| PROGRESSION
|--------------------------------------------------------------------------
-->

<section class="investment-card">

    <div class="card-header">

        <div>

            <span class="card-label">
                PROGRESSION DU CYCLE
            </span>

            <h2>

                Jour
                <?= (int) $daysPassed ?>

                /

                <?= (int) $durationDays ?>

            </h2>

        </div>


        <strong class="progress-percent">

            <?= round($progress) ?>%

        </strong>

    </div>


    <div class="progress-bar">

        <div
            class="progress-fill"
            style="width: <?= htmlspecialchars(
                (string) $progress,
                ENT_QUOTES,
                'UTF-8'
            ) ?>%;"
        ></div>

    </div>


    <div class="progress-dates">

        <span>

            <i class="fas fa-play"></i>

            Début :

            <?= htmlspecialchars(
                $startDateFormatted,
                ENT_QUOTES,
                'UTF-8'
            ) ?>

        </span>


        <span>

            <i class="fas fa-flag-checkered"></i>

            Fin :

            <?= htmlspecialchars(
                $endDateFormatted,
                ENT_QUOTES,
                'UTF-8'
            ) ?>

        </span>

    </div>

</section>


<!--
|--------------------------------------------------------------------------
| STATISTIQUES
|--------------------------------------------------------------------------
-->

<section class="stats-grid">


    <!-- CAPITAL -->

    <div class="stat-card">

        <div class="stat-icon">

            <i class="fas fa-wallet"></i>

        </div>

        <div>

            <small>
                Capital investi
            </small>

            <strong>

                <?= formatInvestmentMoney(
                    $investment['amount'],
                    $currencySymbol,
                    $exchangeRate
                ) ?>

            </strong>

        </div>

    </div>


    <!-- GAIN QUOTIDIEN -->

    <div class="stat-card profit-card">

        <div class="stat-icon">

            <i class="fas fa-coins"></i>

        </div>

        <div>

            <small>
                Gain quotidien
            </small>

            <strong class="profit-value">

                +

                <?= formatInvestmentMoney(
                    $currentDayProfit,
                    $currencySymbol,
                    $exchangeRate
                ) ?>

            </strong>

        </div>

    </div>


    <!-- PROFIT ACCUMULÉ -->

    <div class="stat-card profit-card">

        <div class="stat-icon">

            <i class="fas fa-chart-line"></i>

        </div>

        <div>

            <small>
                Profit accumulé
            </small>

            <strong class="profit-value">

                +

                <?= formatInvestmentMoney(
                    $accumulatedProfit,
                    $currencySymbol,
                    $exchangeRate
                ) ?>

            </strong>

        </div>

    </div>


    <!-- PROFIT RÉEL -->

    <div class="stat-card profit-card">

        <div class="stat-icon">

            <i class="fas fa-money-bill-wave"></i>

        </div>

        <div>

            <small>
                Gain réellement reçu
            </small>

            <strong class="profit-value">

                +

                <?= formatInvestmentMoney(
                    $realProfitReceived,
                    $currencySymbol,
                    $exchangeRate
                ) ?>

            </strong>

        </div>

    </div>


    <!-- JOURS RESTANTS -->

    <div class="stat-card">

        <div class="stat-icon">

            <i class="fas fa-hourglass-half"></i>

        </div>

        <div>

            <small>
                Jours restants
            </small>

            <strong>

                <?= (int) $daysRemaining ?>

                jour<?= $daysRemaining > 1 ? 's' : '' ?>

            </strong>

        </div>

    </div>


    <!-- PROFIT RESTANT -->

    <div class="stat-card">

        <div class="stat-icon">

            <i class="fas fa-coins"></i>

        </div>

        <div>

            <small>
                Profit restant
            </small>

            <strong>

                <?= formatInvestmentMoney(
                    $remainingProfit,
                    $currencySymbol,
                    $exchangeRate
                ) ?>

            </strong>

        </div>

    </div>

</section>


<!--
|--------------------------------------------------------------------------
| RENDEMENT QUOTIDIEN
|--------------------------------------------------------------------------
-->

<section class="investment-card daily-profit-card">

    <div class="card-header">

        <div>

            <span class="card-label">
                RENDEMENT QUOTIDIEN
            </span>

            <h2>

                Jour
                <?= (int) $daysPassed ?>

                /

                <?= (int) $durationDays ?>

            </h2>

        </div>


        <span class="profit-percent">

            +

            <?= formatInvestmentMoney(
                $currentDayProfit,
                $currencySymbol,
                $exchangeRate
            ) ?>

        </span>

    </div>


    <p class="info-text">

        Gain quotidien prévu selon le rendement
        du plan d'investissement.

    </p>


    <div class="profit-summary">


        <div>

            <small>
                Gain du jour
            </small>

            <strong class="profit-value">

                +

                <?= formatInvestmentMoney(
                    $currentDayProfit,
                    $currencySymbol,
                    $exchangeRate
                ) ?>

            </strong>

        </div>


        <div>

            <small>
                Profit accumulé
            </small>

            <strong class="profit-value">

                +

                <?= formatInvestmentMoney(
                    $accumulatedProfit,
                    $currencySymbol,
                    $exchangeRate
                ) ?>

            </strong>

        </div>


        <div>

            <small>
                Encore à créditer
            </small>

            <strong>

                <?= formatInvestmentMoney(
                    $profitNotReceived,
                    $currencySymbol,
                    $exchangeRate
                ) ?>

            </strong>

        </div>

    </div>

</section>


<!--
|--------------------------------------------------------------------------
| PROGRESSION DU PROFIT RÉEL
|--------------------------------------------------------------------------
-->

<section class="investment-card">

    <div class="card-header">

        <div>

            <span class="card-label">
                RENDEMENT
            </span>

            <h2>
                Gains réels
            </h2>

        </div>


        <span class="profit-percent">

            <?= number_format(
                $profitReceivedPercent,
                2,
                ',',
                ' '
            ) ?>%

        </span>

    </div>


    <p class="info-text">

        Ce montant correspond uniquement aux sommes
        effectivement créditées dans votre solde.

    </p>


    <div class="profit-progress">

        <div
            class="profit-progress-fill"
            style="width: <?= $profitReceivedPercent ?>%;"
        ></div>

    </div>


    <div class="profit-summary">


        <div>

            <small>
                Profit reçu
            </small>

            <strong class="profit-value">

                +

                <?= formatInvestmentMoney(
                    $realProfitReceived,
                    $currencySymbol,
                    $exchangeRate
                ) ?>

            </strong>

        </div>


        <div>

            <small>
                Profit prévu
            </small>

            <strong class="profit-value">

                +

                <?= formatInvestmentMoney(
                    $totalExpectedProfit,
                    $currencySymbol,
                    $exchangeRate
                ) ?>

            </strong>

        </div>


        <div>

            <small>
                Total reçu
            </small>

            <strong>

                <?= formatInvestmentMoney(
                    $realTotalReceived,
                    $currencySymbol,
                    $exchangeRate
                ) ?>

            </strong>

        </div>

    </div>

</section>


<!--
|--------------------------------------------------------------------------
| HISTORIQUE DES GAINS
|--------------------------------------------------------------------------
-->

<section class="investment-card">

    <div class="card-header">

        <div>

            <span class="card-label">
                HISTORIQUE
            </span>

            <h2>
                Gains crédités
            </h2>

        </div>

    </div>


    <?php if (!empty($profitHistory)): ?>

        <div class="profit-history">

            <?php foreach ($profitHistory as $profit): ?>

                <div class="profit-history-item">

                    <div>

                        <strong class="profit-value">

                            +

                            <?= formatInvestmentMoney(
                                $profit['amount'],
                                $currencySymbol,
                                $exchangeRate
                            ) ?>

                        </strong>


                        <small>

                            <?= htmlspecialchars(
                                $profit['description']
                                    ?? 'Profit quotidien',
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </small>

                    </div>


                    <span>

                        <?php

                        $profitTimestamp =
                            strtotime(
                                $profit['created_at']
                            );

                        echo $profitTimestamp
                            ? date(
                                'd/m/Y H:i',
                                $profitTimestamp
                            )
                            : '—';

                        ?>

                    </span>

                </div>

            <?php endforeach; ?>

        </div>

    <?php else: ?>

        <p class="info-text">

            Aucun profit n'a encore été crédité.

        </p>

    <?php endif; ?>

</section>


<!--
|--------------------------------------------------------------------------
| RETOUR
|--------------------------------------------------------------------------
-->

<a
    href="investments.php"
    class="back-link"
>

    <i class="fas fa-arrow-left"></i>

    Retour à mes investissements

</a>


</div>


</body>

</html>
