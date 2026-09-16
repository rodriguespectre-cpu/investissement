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
| DEVISE
|--------------------------------------------------------------------------
*/

$currencyCode = strtoupper($user['currency_code'] ?? 'XAF');

$currencyMap = [
    'XAF' => [
        'symbol' => 'FCFA',
        'name'   => 'Franc CFA'
    ],
    'EUR' => [
        'symbol' => '€',
        'name'   => 'Euro'
    ],
    'USD' => [
        'symbol' => '$',
        'name'   => 'Dollar américain'
    ],
    'GBP' => [
        'symbol' => '£',
        'name'   => 'Livre sterling'
    ],
    'CHF' => [
        'symbol' => 'CHF',
        'name'   => 'Franc suisse'
    ],
    'CAD' => [
        'symbol' => 'CA$',
        'name'   => 'Dollar canadien'
    ]
];

if (!isset($currencyMap[$currencyCode])) {
    $currencyCode = 'XAF';
}

$currencySymbol = $currencyMap[$currencyCode]['symbol'];

/*
|--------------------------------------------------------------------------
| FORMATAGE
|--------------------------------------------------------------------------
*/

function formatTransactionMoney($amount, $currencyCode, $currencySymbol)
{
    $amount = (float) $amount;

    if ($currencyCode === 'XAF') {
        return number_format($amount, 0, ',', ' ') . ' FCFA';
    }

    return number_format($amount, 2, ',', ' ') . ' ' . $currencySymbol;
}

/*
|--------------------------------------------------------------------------
| FILTRE
|--------------------------------------------------------------------------
*/

$filter = $_GET['type'] ?? 'all';

$allowedFilters = [
    'all',
    'deposit',
    'withdraw',
    'investment',
    'profit',
    'bonus'
];

if (!in_array($filter, $allowedFilters, true)) {
    $filter = 'all';
}

/*
|--------------------------------------------------------------------------
| PAGINATION
|--------------------------------------------------------------------------
*/

$perPage = 15;

$page = filter_input(
    INPUT_GET,
    'page',
    FILTER_VALIDATE_INT
);

if (!$page || $page < 1) {
    $page = 1;
}

$offset = ($page - 1) * $perPage;

/*
|--------------------------------------------------------------------------
| COMPTER LES TRANSACTIONS
|--------------------------------------------------------------------------
*/

if ($filter === 'all') {

    $countStmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM transactions
        WHERE user_id = ?
    ");

    $countStmt->execute([$userId]);

} else {

    $countStmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM transactions
        WHERE user_id = ?
          AND type = ?
    ");

    $countStmt->execute([
        $userId,
        $filter
    ]);
}

$totalTransactions = (int) $countStmt->fetchColumn();

$totalPages = max(
    1,
    (int) ceil($totalTransactions / $perPage)
);

if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

/*
|--------------------------------------------------------------------------
| RÉCUPÉRER LES TRANSACTIONS
|--------------------------------------------------------------------------
*/

if ($filter === 'all') {

    $stmt = $pdo->prepare("
        SELECT
            id,
            type,
            amount,
            fee,
            status,
            payment_method,
            reference,
            description,
            completed_at,
            created_at,
            updated_at
        FROM transactions
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT $perPage OFFSET $offset
    ");

    $stmt->execute([
        $userId
    ]);

} else {

    $stmt = $pdo->prepare("
        SELECT
            id,
            type,
            amount,
            fee,
            status,
            payment_method,
            reference,
            description,
            completed_at,
            created_at,
            updated_at
        FROM transactions
        WHERE user_id = ?
          AND type = ?
        ORDER BY created_at DESC
        LIMIT $perPage OFFSET $offset
    ");

    $stmt->execute([
        $userId,
        $filter
    ]);
}

$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| LIBELLÉS
|--------------------------------------------------------------------------
*/

$typeLabels = [
    'deposit'     => 'Dépôt',
    'withdraw'    => 'Retrait',
    'investment' => 'Investissement',
    'profit'     => 'Profit',
    'bonus'      => 'Bonus'
];

$typeIcons = [
    'deposit'     => 'fa-arrow-down',
    'withdraw'    => 'fa-arrow-up',
    'investment' => 'fa-chart-line',
    'profit'     => 'fa-coins',
    'bonus'      => 'fa-gift'
];

$statusLabels = [
    'pending'   => 'En attente',
    'completed' => 'Validé',
    'failed'    => 'Échec',
    'cancelled' => 'Annulé'
];

$statusIcons = [
    'pending'   => 'fa-clock',
    'completed' => 'fa-check',
    'failed'    => 'fa-xmark',
    'cancelled' => 'fa-ban'
];

/*
|--------------------------------------------------------------------------
| STATISTIQUES
|--------------------------------------------------------------------------
*/

$statsStmt = $pdo->prepare("
    SELECT

        COALESCE(
            SUM(
                CASE
                    WHEN type = 'deposit'
                    AND status = 'completed'
                    THEN amount
                    ELSE 0
                END
            ),
            0
        ) AS total_deposits,

        COALESCE(
            SUM(
                CASE
                    WHEN type = 'withdraw'
                    AND status = 'completed'
                    THEN amount
                    ELSE 0
                END
            ),
            0
        ) AS total_withdrawals,

        COALESCE(
            SUM(
                CASE
                    WHEN type = 'profit'
                    AND status = 'completed'
                    THEN amount
                    ELSE 0
                END
            ),
            0
        ) AS total_profits

    FROM transactions
    WHERE user_id = ?
");

$statsStmt->execute([
    $userId
]);

$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

$totalDeposits = (float) ($stats['total_deposits'] ?? 0);
$totalWithdrawals = (float) ($stats['total_withdrawals'] ?? 0);
$totalProfits = (float) ($stats['total_profits'] ?? 0);

$firstLetter = strtoupper(
    substr(
        $user['username'] ?? 'U',
        0,
        1
    )
);

?><!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><meta
name="viewport"
content="width=device-width, initial-scale=1.0"

«»

<title>Historique des transactions — InvestPro</title><link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
><style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    background: #f5f7fb;
    color: #172033;
    font-family:
        Inter,
        -apple-system,
        BlinkMacSystemFont,
        "Segoe UI",
        sans-serif;
}

a {
    text-decoration: none;
    color: inherit;
}

.content {
    width: min(1200px, calc(100% - 32px));
    margin: 0 auto;
    padding: 30px 0 60px;
}

/* HEADER */

.page-header {
    display: flex;
    align-items: center;
    gap: 20px;
    margin-bottom: 30px;
}

.back-button {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 11px 16px;
    border-radius: 12px;
    background: #ffffff;
    border: 1px solid #e5e9f2;
    color: #263247;
    font-weight: 600;
    transition: .2s;
}

.back-button:hover {
    transform: translateY(-1px);
    box-shadow: 0 7px 20px rgba(30, 50, 90, .08);
}

.page-label {
    display: block;
    color: #64748b;
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 1.5px;
    margin-bottom: 5px;
}

.page-header h1 {
    margin: 0;
    font-size: 30px;
}

/* USER */

.user-card {
    background: linear-gradient(
        135deg,
        #101828,
        #1e293b
    );
    color: #fff;
    border-radius: 20px;
    padding: 22px 25px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
    margin-bottom: 25px;
}

.user-info {
    display: flex;
    align-items: center;
    gap: 14px;
}

.avatar {
    width: 48px;
    height: 48px;
    border-radius: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(255,255,255,.12);
    font-size: 20px;
    font-weight: 800;
}

.user-info strong {
    display: block;
    font-size: 16px;
}

.user-info small {
    display: block;
    margin-top: 4px;
    color: #cbd5e1;
}

.balance {
    text-align: right;
}

.balance small {
    display: block;
    color: #cbd5e1;
    margin-bottom: 5px;
}

.balance strong {
    font-size: 22px;
}

/* STATS */

.stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 18px;
    margin-bottom: 25px;
}

.stat {
    background: #fff;
    border: 1px solid #e8ecf3;
    border-radius: 18px;
    padding: 20px;
}

.stat-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 14px;
}

.stat-icon {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f1f5f9;
    color: #334155;
}

.stat small {
    color: #64748b;
}

.stat strong {
    display: block;
    margin-top: 5px;
    font-size: 19px;
}

/* FILTERS */

.history-card {
    background: #fff;
    border: 1px solid #e8ecf3;
    border-radius: 20px;
    overflow: hidden;
}

.history-header {
    padding: 22px 24px;
    border-bottom: 1px solid #edf0f5;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
}

.history-header h2 {
    margin: 0;
    font-size: 19px;
}

.history-header p {
    margin: 5px 0 0;
    color: #64748b;
    font-size: 13px;
}

.filters {
    padding: 15px 24px;
    border-bottom: 1px solid #edf0f5;
    display: flex;
    flex-wrap: wrap;
    gap: 9px;
}

.filter {
    padding: 9px 14px;
    border-radius: 10px;
    border: 1px solid #e2e8f0;
    background: #fff;
    color: #475569;
    font-size: 13px;
    font-weight: 700;
}

.filter:hover {
    background: #f8fafc;
}

.filter.active {
    background: #111827;
    border-color: #111827;
    color: #fff;
}

/* TRANSACTIONS */

.transaction-list {
    width: 100%;
}

.transaction {
    display: grid;
    grid-template-columns: 48px minmax(180px, 1fr) auto auto;
    gap: 18px;
    align-items: center;
    padding: 19px 24px;
    border-bottom: 1px solid #edf0f5;
}

.transaction:last-child {
    border-bottom: 0;
}

.transaction-icon {
    width: 45px;
    height: 45px;
    border-radius: 13px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f1f5f9;
    color: #334155;
}

.transaction-main strong {
    display: block;
    font-size: 14px;
}

.transaction-main small {
    display: block;
    color: #64748b;
    margin-top: 5px;
    line-height: 1.4;
}

.transaction-date {
    color: #64748b;
    font-size: 12px;
    text-align: right;
}

.transaction-amount {
    min-width: 150px;
    text-align: right;
}

.amount {
    font-weight: 800;
    font-size: 15px;
}

.amount.positive {
    color: #059669;
}

.amount.negative {
    color: #dc2626;
}

.fee {
    display: block;
    margin-top: 4px;
    color: #94a3b8;
    font-size: 11px;
}

/* BADGES */

.badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 5px 9px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 800;
    margin-top: 7px;
}

.badge-pending {
    color: #92400e;
    background: #fef3c7;
}

.badge-completed {
    color: #065f46;
    background: #d1fae5;
}

.badge-failed {
    color: #991b1b;
    background: #fee2e2;
}

.badge-cancelled {
    color: #475569;
    background: #e2e8f0;
}

/* EMPTY */

.empty {
    padding: 70px 25px;
    text-align: center;
}

.empty-icon {
    width: 65px;
    height: 65px;
    margin: 0 auto 15px;
    border-radius: 20px;
    background: #f1f5f9;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #64748b;
    font-size: 25px;
}

.empty h3 {
    margin: 0 0 7px;
}

.empty p {
    color: #64748b;
    margin: 0;
}

/* PAGINATION */

.pagination {
    padding: 20px 24px;
    border-top: 1px solid #edf0f5;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
}

.pagination-info {
    color: #64748b;
    font-size: 13px;
}

.pagination-links {
    display: flex;
    gap: 7px;
}

.pagination a {
    min-width: 38px;
    height: 38px;
    padding: 0 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 10px;
    border: 1px solid #e2e8f0;
    background: #fff;
    font-weight: 700;
    font-size: 13px;
}

.pagination a.active {
    background: #111827;
    border-color: #111827;
    color: #fff;
}

/* MOBILE */

@media (max-width: 800px) {

    .content {
        width: min(100% - 20px, 700px);
        padding-top: 18px;
    }

    .page-header {
        align-items: flex-start;
    }

    .page-header h1 {
        font-size: 24px;
    }

    .user-card {
        align-items: flex-start;
        flex-direction: column;
    }

    .balance {
        text-align: left;
    }

    .stats {
        grid-template-columns: 1fr;
    }

    .history-header {
        align-items: flex-start;
        flex-direction: column;
    }

    .transaction {
        grid-template-columns: 45px 1fr;
        gap: 12px;
    }

    .transaction-date {
        grid-column: 2;
        text-align: left;
    }

    .transaction-amount {
        grid-column: 2;
        min-width: 0;
        text-align: left;
    }

    .pagination {
        flex-direction: column;
        align-items: flex-start;
    }

}

</style></head><body><div class="content"><!-- HEADER -->

<div class="page-header">

    <a
        href="index.php"
        class="back-button"
    >
        <i class="fas fa-arrow-left"></i>
        Tableau de bord
    </a>

    <div>

        <span class="page-label">
            ACTIVITÉ DU COMPTE
        </span>

        <h1>
            Historique
        </h1>

    </div>

</div>


<!-- UTILISATEUR -->

<div class="user-card">

    <div class="user-info">

        <div class="avatar">
            <?= htmlspecialchars($firstLetter) ?>
        </div>

        <div>

            <strong>
                <?= htmlspecialchars($user['username']) ?>
            </strong>

            <small>
                Historique de vos opérations
            </small>

        </div>

    </div>

    <div class="balance">

        <small>
            Solde disponible
        </small>

        <strong>
            <?= formatTransactionMoney(
                $user['balance'] ?? 0,
                $currencyCode,
                $currencySymbol
            ) ?>
        </strong>

    </div>

</div>


<!-- STATISTIQUES -->

<div class="stats">

    <div class="stat">

        <div class="stat-top">

            <div>
                <small>
                    Total des dépôts
                </small>

                <strong>
                    <?= formatTransactionMoney(
                        $totalDeposits,
                        $currencyCode,
                        $currencySymbol
                    ) ?>
                </strong>
            </div>

            <div class="stat-icon">
                <i class="fas fa-arrow-down"></i>
            </div>

        </div>

    </div>


    <div class="stat">

        <div class="stat-top">

            <div>
                <small>
                    Total des retraits
                </small>

                <strong>
                    <?= formatTransactionMoney(
                        $totalWithdrawals,
                        $currencyCode,
                        $currencySymbol
                    ) ?>
                </strong>
            </div>

            <div class="stat-icon">
                <i class="fas fa-arrow-up"></i>
            </div>

        </div>

    </div>


    <div class="stat">

        <div class="stat-top">

            <div>
                <small>
                    Profits reçus
                </small>

                <strong>
                    <?= formatTransactionMoney(
                        $totalProfits,
                        $currencyCode,
                        $currencySymbol
                    ) ?>
                </strong>
            </div>

            <div class="stat-icon">
                <i class="fas fa-chart-line"></i>
            </div>

        </div>

    </div>

</div>


<!-- HISTORIQUE -->

<section class="history-card">

    <div class="history-header">

        <div>

            <h2>
                Toutes les transactions
            </h2>

            <p>
                Retrouvez l'ensemble de vos opérations.
            </p>

        </div>

    </div>


    <!-- FILTRES -->

    <div class="filters">

        <?php foreach ($allowedFilters as $filterValue): ?>

            <a
                href="?type=<?= urlencode($filterValue) ?>&page=1"
                class="filter <?= $filter === $filterValue ? 'active' : '' ?>"
            >

                <?php

                if ($filterValue === 'all') {
                    echo 'Toutes';
                } else {
                    echo htmlspecialchars(
                        $typeLabels[$filterValue]
                    );
                }

                ?>

            </a>

        <?php endforeach; ?>

    </div>


    <!-- LISTE -->

    <?php if (!empty($transactions)): ?>

        <div class="transaction-list">

            <?php foreach ($transactions as $transaction): ?>

                <?php

                $type = $transaction['type'];

                $status = $transaction['status'];

                $amount = (float) $transaction['amount'];

                $fee = (float) $transaction['fee'];

                $isPositive = in_array(
                    $type,
                    ['deposit', 'profit', 'bonus'],
                    true
                );

                $typeLabel =
                    $typeLabels[$type]
                    ?? ucfirst($type);

                $typeIcon =
                    $typeIcons[$type]
                    ?? 'fa-money-bill';

                $statusLabel =
                    $statusLabels[$status]
                    ?? ucfirst($status);

                $statusIcon =
                    $statusIcons[$status]
                    ?? 'fa-circle';

                ?>

                <div class="transaction">

                    <!-- ICON -->

                    <div class="transaction-icon">

                        <i class="fas <?= htmlspecialchars($typeIcon) ?>"></i>

                    </div>


                    <!-- INFORMATIONS -->

                    <div class="transaction-main">

                        <strong>
                            <?= htmlspecialchars($typeLabel) ?>
                        </strong>

                        <?php if (!empty($transaction['description'])): ?>

                            <small>
                                <?= htmlspecialchars(
                                    $transaction['description']
                                ) ?>
                            </small>

                        <?php elseif (!empty($transaction['payment_method'])): ?>

                            <small>
                                <?= htmlspecialchars(
                                    $transaction['payment_method']
                                ) ?>
                            </small>

                        <?php endif; ?>


                        <span class="badge badge-<?= htmlspecialchars($status) ?>">

                            <i class="fas <?= htmlspecialchars($statusIcon) ?>"></i>

                            <?= htmlspecialchars($statusLabel) ?>

                        </span>

                    </div>


                    <!-- DATE -->

                    <div class="transaction-date">

                        <?= date(
                            'd/m/Y',
                            strtotime($transaction['created_at'])
                        ) ?>

                        <br>

                        <?= date(
                            'H:i',
                            strtotime($transaction['created_at'])
                        ) ?>

                        <?php if (!empty($transaction['reference'])): ?>

                            <br>

                            <small>
                                Réf. <?= htmlspecialchars(
                                    $transaction['reference']
                                ) ?>
                            </small>

                        <?php endif; ?>

                    </div>


                    <!-- MONTANT -->

                    <div class="transaction-amount">

                        <span class="amount <?= $isPositive ? 'positive' : 'negative' ?>">

                            <?= $isPositive ? '+' : '-' ?>

                            <?= formatTransactionMoney(
                                $amount,
                                $currencyCode,
                                $currencySymbol
                            ) ?>

                        </span>

                        <?php if ($fee > 0): ?>

                            <span class="fee">

                                Frais :
                                <?= formatTransactionMoney(
                                    $fee,
                                    $currencyCode,
                                    $currencySymbol
                                ) ?>

                            </span>

                        <?php endif; ?>

                    </div>

                </div>

            <?php endforeach; ?>

        </div>


        <!-- PAGINATION -->

        <?php if ($totalPages > 1): ?>

            <div class="pagination">

                <div class="pagination-info">

                    Page
                    <?= $page ?>
                    sur
                    <?= $totalPages ?>

                    —
                    <?= $totalTransactions ?>
                    transaction<?= $totalTransactions > 1 ? 's' : '' ?>

                </div>


                <div class="pagination-links">

                    <?php if ($page > 1): ?>

                        <a
                            href="?type=<?= urlencode($filter) ?>&page=<?= $page - 1 ?>"
                        >

                            <i class="fas fa-chevron-left"></i>

                        </a>

                    <?php endif; ?>


                    <?php

                    $startPage = max(
                        1,
                        $page - 2
                    );

                    $endPage = min(
                        $totalPages,
                        $page + 2
                    );

                    for (
                        $p = $startPage;
                        $p <= $endPage;
                        $p++
                    ):
                    ?>

                        <a
                            href="?type=<?= urlencode($filter) ?>&page=<?= $p ?>"
                            class="<?= $p === $page ? 'active' : '' ?>"
                        >
                            <?= $p ?>
                        </a>

                    <?php endfor; ?>


                    <?php if ($page < $totalPages): ?>

                        <a
                            href="?type=<?= urlencode($filter) ?>&page=<?= $page + 1 ?>"
                        >

                            <i class="fas fa-chevron-right"></i>

                        </a>

                    <?php endif; ?>

                </div>

            </div>

        <?php endif; ?>


    <?php else: ?>

        <div class="empty">

            <div class="empty-icon">

                <i class="fas fa-receipt"></i>

            </div>

            <h3>
                Aucune transaction
            </h3>

            <p>
                Aucune opération ne correspond à ce filtre.
            </p>

        </div>

    <?php endif; ?>

</section>


<!-- RETOUR -->

<div style="margin-top:22px;">

    <a
        href="index.php"
        class="back-button"
    >

        <i class="fas fa-arrow-left"></i>

        Retour au tableau de bord

    </a>

</div>

</div></body></html>
