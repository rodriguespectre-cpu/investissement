<?php

session_start();
require_once "../../config/database.php";

/*
|--------------------------------------------------------------------------
| PROTECTION ADMIN
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION['admin_id']) ||
    !isset($_SESSION['admin_role']) ||
    !in_array($_SESSION['admin_role'], ['admin', 'super_admin'], true)
) {
    header("Location: login.php");
    exit;
}

$adminId = (int) $_SESSION['admin_id'];

/*
|--------------------------------------------------------------------------
| VÉRIFICATION ADMIN
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT id, username, email, role, is_active
    FROM users
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([$adminId]);

$admin = $stmt->fetch(PDO::FETCH_ASSOC);

if (
    !$admin ||
    (int)$admin['is_active'] !== 1 ||
    !in_array($admin['role'], ['admin', 'super_admin'], true)
) {
    http_response_code(403);
    exit("Accès interdit.");
}

/*
|--------------------------------------------------------------------------
| FILTRES
|--------------------------------------------------------------------------
*/

$type = $_GET['type'] ?? '';
$status = $_GET['status'] ?? '';
$search = trim($_GET['search'] ?? '');

$allowedTypes = [
    'deposit',
    'withdraw',
    'investment',
    'profit',
    'bonus'
];

$allowedStatuses = [
    'pending',
    'completed',
    'failed',
    'cancelled'
];

$where = [];
$params = [];

if (in_array($type, $allowedTypes, true)) {
    $where[] = "t.type = ?";
    $params[] = $type;
}

if (in_array($status, $allowedStatuses, true)) {
    $where[] = "t.status = ?";
    $params[] = $status;
}

if ($search !== '') {

    $where[] = "
        (
            u.username LIKE ?
            OR u.email LIKE ?
            OR t.reference LIKE ?
            OR t.payment_method LIKE ?
        )
    ";

    $like = "%{$search}%";

    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$whereSql = '';

if ($where) {
    $whereSql = 'WHERE ' . implode(' AND ', $where);
}

/*
|--------------------------------------------------------------------------
| TRANSACTIONS
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        t.id,
        t.user_id,
        t.type,
        t.amount,
        t.fee,
        t.status,
        t.payment_method,
        t.withdrawal_country,
        t.withdrawal_phone,
        t.reference,
        t.description,
        t.created_at,
        t.completed_at,

        u.username,
        u.email

    FROM transactions t

    INNER JOIN users u
        ON u.id = t.user_id

    {$whereSql}

    ORDER BY t.created_at DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| STATISTIQUES
|--------------------------------------------------------------------------
*/

$statsStmt = $pdo->query("
    SELECT

        COUNT(*) AS total_transactions,

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
                    WHEN type = 'investment'
                    AND status = 'completed'
                    THEN amount
                    ELSE 0
                END
            ),
            0
        ) AS total_investments,

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
");

$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/

function money($amount): string
{
    return number_format(
        (float)$amount,
        2,
        ',',
        ' '
    ) . ' XAF';
}

function typeLabel(string $type): string
{
    return match ($type) {
        'deposit'    => 'Dépôt',
        'withdraw'   => 'Retrait',
        'investment' => 'Investissement',
        'profit'     => 'Profit',
        'bonus'      => 'Bonus',
        default      => ucfirst($type)
    };
}

function statusLabel(string $status): string
{
    return match ($status) {
        'pending'   => 'En attente',
        'completed' => 'Terminé',
        'failed'    => 'Échoué',
        'cancelled' => 'Annulé',
        default     => ucfirst($status)
    };
}

function typeClass(string $type): string
{
    return match ($type) {
        'deposit'    => 'deposit',
        'withdraw'   => 'withdraw',
        'investment' => 'investment',
        'profit'     => 'profit',
        'bonus'      => 'bonus',
        default      => 'other'
    };
}

?>

<!DOCTYPE html>
<html lang="fr">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>InvestPro — Transactions</title>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family:
        Inter,
        -apple-system,
        BlinkMacSystemFont,
        "Segoe UI",
        sans-serif;

    background: #f5f7fb;
    color: #172033;
}

/* HEADER */

.header {
    background: #fff;
    border-bottom: 1px solid #e7eaf0;
    padding: 18px 24px;

    display: flex;
    align-items: center;
    justify-content: space-between;
}

.header h1 {
    margin: 0;
    font-size: 22px;
}

.back {
    color: #4f46e5;
    text-decoration: none;
    font-weight: 700;
}

/* CONTAINER */

.container {
    max-width: 1250px;
    margin: 28px auto;
    padding: 0 18px;
}

/* STATS */

.stats {
    display: grid;
    grid-template-columns:
        repeat(4, minmax(0, 1fr));

    gap: 14px;
    margin-bottom: 20px;
}

.stat {
    background: #fff;
    border: 1px solid #e7eaf0;
    border-radius: 15px;
    padding: 18px;
}

.stat-label {
    color: #778195;
    font-size: 13px;
    margin-bottom: 8px;
}

.stat-value {
    font-size: 21px;
    font-weight: 800;
}

/* FILTERS */

.filters {
    background: #fff;
    border: 1px solid #e7eaf0;
    border-radius: 15px;
    padding: 15px;
    margin-bottom: 18px;

    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.filters input,
.filters select {
    border: 1px solid #dfe3ea;
    border-radius: 9px;
    padding: 10px 12px;
    background: #fff;
    font-size: 14px;
}

.filters input {
    min-width: 230px;
}

.filters button {
    border: 0;
    border-radius: 9px;
    padding: 10px 18px;
    background: #4f46e5;
    color: white;
    font-weight: 700;
    cursor: pointer;
}

/* TABLE */

.card {
    background: #fff;
    border: 1px solid #e7eaf0;
    border-radius: 18px;
    overflow: hidden;
}

.table-wrapper {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 1100px;
}

th {
    padding: 14px;
    text-align: left;
    background: #f8f9fc;

    color: #697386;
    font-size: 11px;
    text-transform: uppercase;
}

td {
    padding: 15px 14px;
    border-top: 1px solid #eef0f4;
    vertical-align: middle;
}

.user {
    font-weight: 700;
}

.email {
    color: #8790a0;
    font-size: 12px;
    margin-top: 3px;
}

.amount {
    font-weight: 800;
}

/* TYPE */

.type {
    display: inline-block;
    padding: 6px 9px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 700;
}

.deposit {
    background: #eafaf1;
    color: #16804b;
}

.withdraw {
    background: #fff0f0;
    color: #bd2b2b;
}

.investment {
    background: #eef2ff;
    color: #4f46e5;
}

.profit {
    background: #e9f8ff;
    color: #08759c;
}

.bonus {
    background: #fff6dc;
    color: #966b00;
}

/* STATUS */

.status {
    display: inline-block;
    padding: 6px 9px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 700;
}

.status-pending {
    background: #fff4d6;
    color: #936500;
}

.status-completed {
    background: #e8f8ef;
    color: #147a45;
}

.status-failed,
.status-cancelled {
    background: #ffe9e9;
    color: #b42318;
}

.empty {
    padding: 60px 20px;
    text-align: center;
    color: #7b8494;
}

.reference {
    font-family: monospace;
    font-size: 12px;
}

.description {
    color: #737d8e;
    font-size: 12px;
    max-width: 220px;
}

@media (max-width: 800px) {

    .stats {
        grid-template-columns:
            repeat(2, 1fr);
    }

}

@media (max-width: 500px) {

    .stats {
        grid-template-columns: 1fr;
    }

    .header {
        padding: 16px;
    }

    .container {
        padding: 0 10px;
    }

}

</style>

</head>

<body>

<header class="header">

    <h1>Transactions</h1>

    <a
        class="back"
        href="index.php"
    >
        ← Tableau de bord
    </a>

</header>

<main class="container">

<!-- STATISTIQUES -->

<section class="stats">

    <div class="stat">

        <div class="stat-label">
            Transactions
        </div>

        <div class="stat-value">
            <?= number_format(
                (int)$stats['total_transactions'],
                0,
                ',',
                ' '
            ) ?>
        </div>

    </div>

    <div class="stat">

        <div class="stat-label">
            Dépôts
        </div>

        <div class="stat-value">
            <?= money($stats['total_deposits']) ?>
        </div>

    </div>

    <div class="stat">

        <div class="stat-label">
            Retraits
        </div>

        <div class="stat-value">
            <?= money($stats['total_withdrawals']) ?>
        </div>

    </div>

    <div class="stat">

        <div class="stat-label">
            Investissements
        </div>

        <div class="stat-value">
            <?= money($stats['total_investments']) ?>
        </div>

    </div>

</section>

<!-- FILTRES -->

<form
    method="GET"
    class="filters"
>

    <input
        type="search"
        name="search"
        placeholder="Utilisateur, email, référence..."
        value="<?= htmlspecialchars($search) ?>"
    >

    <select name="type">

        <option value="">
            Tous les types
        </option>

        <?php foreach ($allowedTypes as $item): ?>

            <option
                value="<?= htmlspecialchars($item) ?>"
                <?= $type === $item ? 'selected' : '' ?>
            >
                <?= htmlspecialchars(typeLabel($item)) ?>
            </option>

        <?php endforeach; ?>

    </select>

    <select name="status">

        <option value="">
            Tous les statuts
        </option>

        <?php foreach ($allowedStatuses as $item): ?>

            <option
                value="<?= htmlspecialchars($item) ?>"
                <?= $status === $item ? 'selected' : '' ?>
            >
                <?= htmlspecialchars(statusLabel($item)) ?>
            </option>

        <?php endforeach; ?>

    </select>

    <button type="submit">
        Filtrer
    </button>

</form>

<!-- TABLE -->

<div class="card">

<?php if (!$transactions): ?>

    <div class="empty">
        Aucune transaction trouvée.
    </div>

<?php else: ?>

<div class="table-wrapper">

<table>

<thead>

<tr>

    <th>ID</th>
    <th>Utilisateur</th>
    <th>Type</th>
    <th>Montant</th>
    <th>Frais</th>
    <th>Statut</th>
    <th>Paiement</th>
    <th>Référence</th>
    <th>Description</th>
    <th>Date</th>

</tr>

</thead>

<tbody>

<?php foreach ($transactions as $transaction): ?>

<tr>

    <td>
        #<?= (int)$transaction['id'] ?>
    </td>

    <td>

        <div class="user">
            <?= htmlspecialchars(
                $transaction['username']
            ) ?>
        </div>

        <div class="email">
            <?= htmlspecialchars(
                $transaction['email']
            ) ?>
        </div>

    </td>

    <td>

        <span
            class="type <?= htmlspecialchars(
                typeClass($transaction['type'])
            ) ?>"
        >
            <?= htmlspecialchars(
                typeLabel($transaction['type'])
            ) ?>
        </span>

    </td>

    <td>

        <span class="amount">
            <?= money($transaction['amount']) ?>
        </span>

    </td>

    <td>

        <?= money($transaction['fee']) ?>

    </td>

    <td>

        <span
            class="status status-<?= htmlspecialchars(
                $transaction['status']
            ) ?>"
        >
            <?= htmlspecialchars(
                statusLabel($transaction['status'])
            ) ?>
        </span>

    </td>

    <td>

        <?= htmlspecialchars(
            $transaction['payment_method'] ?: '—'
        ) ?>

        <?php if (
            !empty($transaction['withdrawal_phone'])
        ): ?>

            <br>

            <small>
                <?= htmlspecialchars(
                    $transaction['withdrawal_phone']
                ) ?>
            </small>

        <?php endif; ?>

    </td>

    <td>

        <span class="reference">

            <?= htmlspecialchars(
                $transaction['reference'] ?: '—'
            ) ?>

        </span>

    </td>

    <td>

        <div class="description">

            <?= htmlspecialchars(
                $transaction['description'] ?: '—'
            ) ?>

        </div>

    </td>

    <td>

        <?= htmlspecialchars(
            date(
                'd/m/Y H:i',
                strtotime($transaction['created_at'])
            )
        ) ?>

    </td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

</div>

<?php endif; ?>

</div>

</main>

</body>

</html>
