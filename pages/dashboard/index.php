<?php
session_start();

require_once '../../config/database.php';
require_once '../../config/user_preferences.php';


if (!isLoggedIn()) {
    header("Location: ../login.php");
    exit;
}

$user = getUserPreferences(
    $pdo,
    (int) $_SESSION['user_id']
);

$_SESSION['language_code'] =
    $user['language_code'];

$_SESSION['currency_code'] =
    $user['currency_code'];

if (!$user) {
    session_destroy();
    header("Location: ../login.php");
    exit;
}

$user_id = (int) $user['id'];

/*
|--------------------------------------------------------------------------
| DEVISES
|--------------------------------------------------------------------------
| Les plans sont enregistrés en XAF dans la base.
| L'affichage est converti selon le pays de l'utilisateur.
|--------------------------------------------------------------------------
*/

$currencies = [
    'CM' => ['code' => 'XAF', 'symbol' => 'FCFA', 'rate' => 1],
    'GA' => ['code' => 'XAF', 'symbol' => 'FCFA', 'rate' => 1],
    'CG' => ['code' => 'XAF', 'symbol' => 'FCFA', 'rate' => 1],

    'FR' => ['code' => 'EUR', 'symbol' => '€', 'rate' => 0.00152],
    'BE' => ['code' => 'EUR', 'symbol' => '€', 'rate' => 0.00152],
    'DE' => ['code' => 'EUR', 'symbol' => '€', 'rate' => 0.00152],
    'IT' => ['code' => 'EUR', 'symbol' => '€', 'rate' => 0.00152],
    'ES' => ['code' => 'EUR', 'symbol' => '€', 'rate' => 0.00152],
    'PT' => ['code' => 'EUR', 'symbol' => '€', 'rate' => 0.00152],
    'NL' => ['code' => 'EUR', 'symbol' => '€', 'rate' => 0.00152],
    'LU' => ['code' => 'EUR', 'symbol' => '€', 'rate' => 0.00152],

    'CH' => ['code' => 'CHF', 'symbol' => 'CHF', 'rate' => 0.00143],
    'CA' => ['code' => 'CAD', 'symbol' => 'CA$', 'rate' => 0.00222],
    'US' => ['code' => 'USD', 'symbol' => '$', 'rate' => 0.00169],
    'GB' => ['code' => 'GBP', 'symbol' => '£', 'rate' => 0.00131],

    'SN' => ['code' => 'XOF', 'symbol' => 'FCFA', 'rate' => 1],
    'CI' => ['code' => 'XOF', 'symbol' => 'FCFA', 'rate' => 1],
    'BF' => ['code' => 'XOF', 'symbol' => 'FCFA', 'rate' => 1],
    'ML' => ['code' => 'XOF', 'symbol' => 'FCFA', 'rate' => 1],
    'NE' => ['code' => 'XOF', 'symbol' => 'FCFA', 'rate' => 1],
    'TG' => ['code' => 'XOF', 'symbol' => 'FCFA', 'rate' => 1],
    'BJ' => ['code' => 'XOF', 'symbol' => 'FCFA', 'rate' => 1],

    'CD' => ['code' => 'CDF', 'symbol' => 'FC', 'rate' => 4.55],
    'AO' => ['code' => 'AOA', 'symbol' => 'Kz', 'rate' => 1.55],
    'GH' => ['code' => 'GHS', 'symbol' => 'GH₵', 'rate' => 0.028],
    'NG' => ['code' => 'NGN', 'symbol' => '₦', 'rate' => 2.55],
    'ZA' => ['code' => 'ZAR', 'symbol' => 'R', 'rate' => 0.030],
    'MA' => ['code' => 'MAD', 'symbol' => 'DH', 'rate' => 0.0167],
    'DZ' => ['code' => 'DZD', 'symbol' => 'DA', 'rate' => 0.227],
    'TN' => ['code' => 'TND', 'symbol' => 'DT', 'rate' => 0.00525],
    'LY' => ['code' => 'LYD', 'symbol' => 'LD', 'rate' => 0.00815],
    'EG' => ['code' => 'EGP', 'symbol' => 'E£', 'rate' => 0.083]
];

$countryCode = strtoupper($user['country_code'] ?? 'CM');

$currency = $currencies[$countryCode] ?? [
    'code' => 'XAF',
    'symbol' => 'FCFA',
    'rate' => 1
];

$currencyCode = $currency['code'];
$currencySymbol = $currency['symbol'];
$currencyRate = $currency['rate'];

/*
|--------------------------------------------------------------------------
| FORMAT MONÉTAIRE
|--------------------------------------------------------------------------
*/

function money($xaf, $currency)
{
    $value = (float)$xaf * $currency['rate'];

    if ($currency['code'] === 'XAF' || $currency['code'] === 'XOF') {
        return number_format($value, 0, ',', ' ') . ' ' . $currency['symbol'];
    }

    return number_format($value, 2, ',', ' ') . ' ' . $currency['symbol'];
}

/*
|--------------------------------------------------------------------------
| STATISTIQUES
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT COUNT(*) AS total
    FROM investments
    WHERE user_id = ?
    AND status = 'active'
");
$stmt->execute([$user_id]);
$activeInvestments = (int)$stmt->fetch()['total'];

/*
|--------------------------------------------------------------------------
| TOTAL INVESTI
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(amount), 0) AS total_amount
    FROM investments
    WHERE user_id = ?
");
$stmt->execute([$user_id]);
$totalInvested = (float)$stmt->fetch()['total_amount'];

/*
|--------------------------------------------------------------------------
| PROFIT
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(total_profit), 0) AS total_profit
    FROM investments
    WHERE user_id = ?
    AND status = 'completed'
");
$stmt->execute([$user_id]);
$totalProfit = (float)$stmt->fetch()['total_profit'];

/*
|--------------------------------------------------------------------------
| BONUS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(bonus_amount), 0) AS total_bonus
    FROM daily_bonuses
    WHERE user_id = ?
    AND status = 'credited'
");
$stmt->execute([$user_id]);
$totalBonus = (float)$stmt->fetch()['total_bonus'];

/*
|--------------------------------------------------------------------------
| PLANS DISPONIBLES
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT *
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
| INVESTISSEMENTS ACTIFS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT *
    FROM investments
    WHERE user_id = ?
    AND status = 'active'
    ORDER BY created_at DESC
    LIMIT 5
");

$stmt->execute([$user_id]);

$activeList = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| TRANSACTIONS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT *
    FROM transactions
    WHERE user_id = ?
    ORDER BY created_at DESC
    LIMIT 5
");

$stmt->execute([$user_id]);

$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| NOTIFICATIONS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT *
    FROM notifications
    WHERE user_id = ?
    ORDER BY created_at DESC
    LIMIT 10
");

$stmt->execute([$user_id]);

$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
$stmt->execute([$user_id]);

$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM notifications
    WHERE user_id = ?
      AND is_read = 0
");

$stmt->execute([$user_id]);

$unreadNotificationCount = (int) $stmt->fetchColumn();

$firstLetter = strtoupper(substr($user['username'], 0, 1));

?>
<!DOCTYPE html>
<html lang="fr">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Tableau de bord — InvestPro</title>

<link rel="stylesheet"
      href="assets/index.css">

<link rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

</head>

<body>

<!-- OVERLAY MOBILE -->

<div class="sidebar-overlay"
     id="sidebarOverlay"></div>


<!-- SIDEBAR -->

<aside class="sidebar" id="sidebar">

    <div class="sidebar-header">

        <div class="brand">

            <div class="brand-icon">
                <i class="fas fa-chart-line"></i>
            </div>

            <div>
                <strong>Invest<span>Pro</span></strong>

                <small>Espace membre</small>
            </div>

        </div>

        <button class="close-sidebar"
                id="closeSidebar">

            <i class="fas fa-xmark"></i>

        </button>

    </div>


    <nav class="sidebar-menu">

        <div class="menu-title">
            MENU
        </div>

        <a href="index.php"
           class="menu-item active">

            <i class="fas fa-house"></i>

            <span>Tableau de bord</span>

        </a>


        <a href="investments.php"
           class="menu-item">

            <i class="fas fa-chart-pie"></i>

            <span>Investir</span>

        </a>


        <a href="deposit.php"
           class="menu-item">

            <i class="fas fa-wallet"></i>

            <span>Dépôt</span>

        </a>


        <a href="withdraw.php"
           class="menu-item">

            <i class="fas fa-money-bill-transfer"></i>

            <span>Retrait</span>

        </a>


        <a href="transactions.php"
           class="menu-item">

            <i class="fas fa-clock-rotate-left"></i>

            <span>Transactions</span>

        </a>


        <div class="menu-title">
            COMPTE
        </div>


        <a href="support.php"
           class="menu-item">

            <i class="fas fa-headset"></i>

            <span>Support</span>

        </a>


        <a href="settings.php"
           class="menu-item">

            <i class="fas fa-gear"></i>

            <span>Paramètres</span>

        </a>

       
        <a href="elite.php"
           class="menu-item">

            <i class="fas fa-money-bill-wave"></i>

            <span style="color:blue;font-weight:700; font-size:20px">Elite</span>

        </a>
       <a href="../chat.php"
           class="menu-item">

           <i class="fas fa-comments"></i>
<span>Discussion</span>
        </a>

    </nav>


    <div class="sidebar-bottom">

        <div class="sidebar-user">

            <div class="avatar">
                <?= htmlspecialchars($firstLetter) ?>
            </div>

            <div>

                <strong>
                    <?= htmlspecialchars($user['username']) ?>
                </strong>

                <small>
                    <?= htmlspecialchars($currencyCode) ?>
                </small>

            </div>

        </div>


        <a href="../logout.php"
           class="logout-link">

            <i class="fas fa-right-from-bracket"></i>

            Déconnexion

        </a>

    </div>

</aside>


<!-- CONTENU -->

<div class="app">


<header class="topbar">

    <button class="hamburger"
            id="openSidebar">

        <i class="fas fa-bars"></i>

    </button>


    <div class="topbar-title">

        <strong>Tableau de bord</strong>

        <span>
            Vue générale de votre compte
        </span>

    </div>


    <div class="topbar-right">

        <div class="currency-badge">

            <i class="fas fa-coins"></i>

            <?= htmlspecialchars($currencyCode) ?>

        </div>


        <div class="notification-container">

           <button class="notification-button"
        id="notificationButton">

    <i class="fas fa-bell"></i>

    <?php if ($unreadNotificationCount > 0): ?>

        <span>
            <?= $unreadNotificationCount ?>
        </span>

    <?php endif; ?>

</button>


           <div class="notification-dropdown"
     id="notificationDropdown">

    <div class="notification-header">

        <strong>
            Notifications
        </strong>

        <?php if ($notifications): ?>

            <div class="notification-actions">

                <?php if ($unreadNotificationCount > 0): ?>

                    <form method="POST"
                          action="notification_action.php">

                        <input type="hidden"
                               name="action"
                               value="read_all">

                        <button type="submit">
                            <i class="fas fa-check-double"></i>
                            Tout lire
                        </button>

                    </form>

                <?php endif; ?>

                <form method="POST"
                      action="notification_action.php"
                      onsubmit="return confirm('Supprimer toutes les notifications ?');">

                    <input type="hidden"
                           name="action"
                           value="delete_all">

                    <button type="submit"
                            class="delete-all">

                        <i class="fas fa-trash"></i>
                        Tout supprimer

                    </button>

                </form>

            </div>

        <?php endif; ?>

    </div>


    <?php if ($notifications): ?>

        <div class="notification-list">

            <?php foreach ($notifications as $notification): ?>

                <div class="notification-item
                    <?= ((int)$notification['is_read'] === 0)
                        ? 'notification-unread'
                        : 'notification-read'
                    ?>">

                    <div class="notification-icon">

                        <?php

                        $icon = 'fa-info';

                        if ($notification['type'] === 'success') {
                            $icon = 'fa-check';
                        } elseif ($notification['type'] === 'warning') {
                            $icon = 'fa-triangle-exclamation';
                        } elseif ($notification['type'] === 'error') {
                            $icon = 'fa-xmark';
                        }

                        ?>

                        <i class="fas <?= $icon ?>"></i>

                    </div>


                    <div class="notification-content">

                        <strong>
                            <?= htmlspecialchars(
                                $notification['title']
                            ) ?>
                        </strong>

                        <p>
                            <?= htmlspecialchars(
                                $notification['message']
                            ) ?>
                        </p>

                        <small>
                            <?= date(
                                'd/m/Y H:i',
                                strtotime(
                                    $notification['created_at']
                                )
                            ) ?>
                        </small>


                        <div class="notification-buttons">

                            <?php if ((int)$notification['is_read'] === 0): ?>

                                <form method="POST"
                                      action="notification_action.php">

                                    <input type="hidden"
                                           name="action"
                                           value="read">

                                    <input type="hidden"
                                           name="notification_id"
                                           value="<?= (int)$notification['id'] ?>">

                                    <button type="submit">

                                        <i class="fas fa-check"></i>

                                        Marquer comme lu

                                    </button>

                                </form>

                            <?php endif; ?>


                            <form method="POST"
                                  action="notification_action.php"
                                  onsubmit="return confirm('Supprimer cette notification ?');">

                                <input type="hidden"
                                       name="action"
                                       value="delete">

                                <input type="hidden"
                                       name="notification_id"
                                       value="<?= (int)$notification['id'] ?>">

                                <button type="submit"
                                        class="delete-notification">

                                    <i class="fas fa-trash"></i>

                                    Supprimer

                                </button>

                            </form>

                        </div>

                    </div>

                </div>

            <?php endforeach; ?>

        </div>

    <?php else: ?>

        <div class="empty-notifications">

            <i class="fas fa-bell-slash"></i>

            <p>
                Aucune notification
            </p>

        </div>

    <?php endif; ?>

</div>
</header>


<main class="content">


<!-- HERO -->

<section class="welcome">

    <div>

        <span class="welcome-label">
            ESPACE INVESTISSEUR
        </span>

        <h1>
            Bonjour,
            <span>
                <?= htmlspecialchars($user['username']) ?>
            </span>
        </h1>

        <p>
            Gérez vos investissements et suivez
            l'évolution de votre portefeuille depuis
            un seul espace.
        </p>

    </div>


    <a href="services.php"
       class="primary-button">

        <i class="fas fa-plus"></i>

        Nos services

    </a>

</section>


<!-- STATISTIQUES -->

<section class="stats">

    <div class="stat-card">

        <div class="stat-icon blue">
            <i class="fas fa-wallet"></i>
        </div>

        <div>

            <span>Solde disponible</span>

            <strong>
                <?= money($user['balance'], $currency) ?>
            </strong>

        </div>

    </div>


    <div class="stat-card">

        <div class="stat-icon green">
            <i class="fas fa-chart-line"></i>
        </div>

        <div>

            <span>Total investi</span>

            <strong>
                <?= money($totalInvested, $currency) ?>
            </strong>

        </div>

    </div>


    <div class="stat-card">

        <div class="stat-icon orange">
            <i class="fas fa-coins"></i>
        </div>

        <div>

            <span>Bonus</span>

            <strong>
                <?= money(
                    (float)$user['bonus_balance'] + $totalBonus,
                    $currency
                ) ?>
            </strong>

        </div>

    </div>


    <div class="stat-card">

        <div class="stat-icon purple">
            <i class="fas fa-trophy"></i>
        </div>

        <div>

            <span>Profit réalisé</span>

            <strong>
                <?= money($totalProfit, $currency) ?>
            </strong>

        </div>

    </div>

</section>


<!-- PLANS -->

<section class="plans-section">

    <div class="section-heading">

        <div>

            <span class="section-label">
                OPPORTUNITÉS
            </span>

            <h2>
                Choisissez votre investissement
            </h2>

            <p>
                Sélectionnez un plan adapté à votre budget
                et à vos objectifs.
            </p>

        </div>

    </div>


    <div class="plans-grid">

        <?php foreach ($plans as $plan): ?>

            <?php

            $levelClass = '';

            if ($plan['level'] === 'Level 2') {
                $levelClass = 'level-two';
            }

            if ($plan['level'] === 'Level 3 Premium') {
                $levelClass = 'premium';
            }

            ?>

            <article class="plan-card <?= $levelClass ?>">

                <div class="plan-top">

                    <span class="plan-level">

                        <?php if ($plan['level'] === 'Level 3 Premium'): ?>

                            <i class="fas fa-crown"></i>

                        <?php else: ?>

                            <i class="fas fa-layer-group"></i>

                        <?php endif; ?>

                        <?= htmlspecialchars($plan['level']) ?>

                    </span>

                </div>


                <h3>
                    <?= htmlspecialchars($plan['name']) ?>
                </h3>


                <p class="plan-description">

                    <?= htmlspecialchars($plan['description']) ?>

                </p>


                <div class="plan-amount">

                    <span>Investissement</span>

                    <strong>
                        <?= money($plan['amount'], $currency) ?>
                    </strong>

                </div>


                <div class="plan-return">

                    <div>

                        <span>
                            Retour total
                        </span>

                        <strong>
                            <?= money($plan['return_amount'], $currency) ?>
                        </strong>

                    </div>


                    <div>

                        <span>
                            Profit
                        </span>

                        <strong class="profit">

                            +<?= money(
                                $plan['profit_amount'],
                                $currency
                            ) ?>

                        </strong>

                    </div>

                </div>


                <div class="plan-details">

                    <div>

                        <i class="fas fa-calendar-days"></i>

                        <span>
                            <?= (int)$plan['duration_days'] ?> jours
                        </span>

                    </div>


                    <div>

                        <i class="fas fa-arrow-trend-up"></i>

                        <span>
                            <?= number_format(
                                $plan['profit_percent'],
                                2,
                                ',',
                                ' '
                            ) ?>%
                        </span>

                    </div>

                </div>


                <div class="daily-profit">

                    <i class="fas fa-coins"></i>

                    <div>

                        <span>
                            Profit quotidien
                        </span>

                        <strong>

                            +<?= money(
                                $plan['daily_profit'],
                                $currency
                            ) ?>

                        </strong>

                    </div>

                </div>


                <a href="investments.php?plan=<?= (int)$plan['id'] ?>"
                   class="plan-button">

                    plans disponibles

                    <i class="fas fa-arrow-right"></i>

                </a>

            </article>

        <?php endforeach; ?>

    </div>

</section>


<!-- INVESTISSEMENTS ACTIFS -->

<section class="active-section">

    <div class="section-heading">

        <div>

            <span class="section-label">
                PORTEFEUILLE
            </span>

            <h2>
                Mes investissements actifs
            </h2>

        </div>


        <a href="investments.php"
           class="view-link">

            Voir tout
            <i class="fas fa-arrow-right"></i>

        </a>

    </div>


    <?php if ($activeList): ?>

        <div class="active-investments">

            <?php foreach ($activeList as $investment): ?>

                <?php

                $start = strtotime($investment['start_date']);
                $end = strtotime($investment['end_date']);
                $now = time();

                $totalTime = max(1, $end - $start);
                $elapsed = max(0, $now - $start);

                $progress = min(
                    100,
                    max(
                        0,
                        ($elapsed / $totalTime) * 100
                    )
                );

                ?>

                <div class="active-card">

                    <div class="active-card-header">

                        <div>

                            <span class="active-icon">

                                <i class="fas fa-chart-line"></i>

                            </span>

                            <div>

                                <strong>
                                    <?= htmlspecialchars(
                                        $investment['plan_name']
                                    ) ?>
                                </strong>

                                <small>
                                    Investissement actif
                                </small>

                            </div>

                        </div>


                        <span class="status-active">
                            Actif
                        </span>

                    </div>


                    <div class="active-info">

                        <div>

                            <span>Montant</span>

                            <strong>
                                <?= money(
                                    $investment['amount'],
                                    $currency
                                ) ?>
                            </strong>

                        </div>


                        <div>

                            <span>Profit</span>

                            <strong class="profit">

                                +<?= number_format(
                                    $investment['profit_percent'],
                                    2,
                                    ',',
                                    ' '
                                ) ?>%

                            </strong>

                        </div>


                        <div>

                            <span>Durée</span>

                            <strong>
                                <?= (int)$investment['duration_days'] ?>
                                jours
                            </strong>

                        </div>

                    </div>


                    <div class="progress-container">

                        <div class="progress-header">

                            <span>
                                Progression du cycle
                            </span>

                            <strong>
                                <?= round($progress) ?>%
                            </strong>

                        </div>

                        <div class="progress-bar">

                            <div class="progress-fill"
                                 style="width: <?= $progress ?>%">
                            </div>

                        </div>

                    </div>

                </div>

            <?php endforeach; ?>

        </div>

    <?php else: ?>

        <div class="empty-card">

            <div class="empty-icon">
                <i class="fas fa-chart-pie"></i>
            </div>

            <h3>
                Aucun investissement actif
            </h3>

            <p>
                Choisissez l'un de nos plans ci-dessus
                pour commencer.
            </p>

            <a href="investments.php"
               class="primary-button">

                Découvrir les plans

            </a>

        </div>

    <?php endif; ?>

</section>


<!-- TRANSACTIONS -->

<section class="transactions-section">

    <div class="section-heading">

        <div>

            <span class="section-label">
                ACTIVITÉ
            </span>

            <h2>
                Dernières transactions
            </h2>

        </div>


        <a href="transactions.php"
           class="view-link">

            Historique
            <i class="fas fa-arrow-right"></i>

        </a>

    </div>


    <div class="transactions-card">

        <?php if ($transactions): ?>

            <?php foreach ($transactions as $transaction): ?>

                <?php

                $positive = in_array(
                    $transaction['type'],
                    ['deposit', 'profit', 'bonus']
                );

                ?>

                <div class="transaction-row">

                    <div class="transaction-icon-row">

                        <i class="fas
                        <?= $transaction['type'] === 'deposit'
                            ? 'fa-arrow-down'
                            : ($transaction['type'] === 'withdraw'
                                ? 'fa-arrow-up'
                                : 'fa-chart-line')
                        ?>"></i>

                    </div>


                    <div class="transaction-name">

                        <strong>

                            <?php

                            $labels = [
                                'deposit' => 'Dépôt',
                                'withdraw' => 'Retrait',
                                'investment' => 'Investissement',
                                'profit' => 'Profit',
                                'bonus' => 'Bonus'
                            ];

                            echo htmlspecialchars(
                                $labels[$transaction['type']]
                                ?? $transaction['type']
                            );

                            ?>

                        </strong>

                        <span>
                            <?= date(
                                'd/m/Y H:i',
                                strtotime(
                                    $transaction['created_at']
                                )
                            ) ?>
                        </span>

                    </div>


                    <strong class="<?= $positive
                        ? 'amount-positive'
                        : 'amount-negative'
                    ?>">

                        <?= $positive ? '+' : '-' ?>

                        <?= money(
                            $transaction['amount'],
                            $currency
                        ) ?>

                    </strong>

                </div>

            <?php endforeach; ?>

        <?php else: ?>

            <div class="no-transactions">

                <i class="fas fa-receipt"></i>

                <p>
                    Aucune transaction pour le moment.
                </p>

            </div>

        <?php endif; ?>

    </div>

</section>


<!-- FOOTER -->

<footer>

    <span>
        © 2026 InvestPro
    </span>

    <div>

        <a href="#">
            Confidentialité
        </a>

        <a href="#">
            Conditions
        </a>

        <a href="support.php">
            Support
        </a>

    </div>

</footer>


</main>

</div>


<script>

const sidebar = document.getElementById('sidebar');

const overlay = document.getElementById('sidebarOverlay');

const openSidebar = document.getElementById('openSidebar');

const closeSidebar = document.getElementById('closeSidebar');


openSidebar.addEventListener('click', () => {

    sidebar.classList.add('open');

    overlay.classList.add('show');

});


closeSidebar.addEventListener('click', () => {

    sidebar.classList.remove('open');

    overlay.classList.remove('show');

});


overlay.addEventListener('click', () => {

    sidebar.classList.remove('open');

    overlay.classList.remove('show');

});


const notificationButton =
    document.getElementById('notificationButton');

const notificationDropdown =
    document.getElementById('notificationDropdown');


notificationButton.addEventListener('click', (event) => {

    event.stopPropagation();

    notificationDropdown.classList.toggle('show');

});


document.addEventListener('click', () => {

    notificationDropdown.classList.remove('show');

});

</script>

</body>
</html>
