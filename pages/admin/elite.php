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
| STATISTIQUES
|--------------------------------------------------------------------------
*/

$stats = [];

/* Membres Elite */

$stmt = $pdo->query("
    SELECT
        COUNT(*) AS total,
        SUM(status = 'active') AS active,
        SUM(status = 'pending') AS pending,
        SUM(status = 'expired') AS expired
    FROM elite_members
");

$stats['members'] = $stmt->fetch(PDO::FETCH_ASSOC);

/* Sessions */

$stmt = $pdo->query("
    SELECT
        COUNT(*) AS total,
        SUM(status = 'open') AS open,
        SUM(status = 'completed') AS completed
    FROM elite_game_sessions
");

$stats['sessions'] = $stmt->fetch(PDO::FETCH_ASSOC);

/* Participations */

$stmt = $pdo->query("
    SELECT COUNT(*) AS total
    FROM elite_game_entries
");

$stats['entries'] = $stmt->fetch(PDO::FETCH_ASSOC);

/* Participants uniques */

$stmt = $pdo->query("
    SELECT COUNT(DISTINCT user_id) AS total
    FROM elite_game_entries
");

$stats['unique_participants'] = $stmt->fetch(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| MEMBRES ELITE
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        em.id,
        em.user_id,
        em.status,
        em.amount,
        em.payment_method,
        em.transaction_id,
        em.reference,
        em.activated_at,
        em.expires_at,
        em.created_at,

        u.username,
        u.email,
        u.balance

    FROM elite_members em

    INNER JOIN users u
        ON u.id = em.user_id

    ORDER BY em.created_at DESC
");

$members = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| SESSIONS ELITE
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        s.id,
        s.scheduled_at,
        s.winning_color,
        s.winner_user_id,
        s.prize_amount,
        s.status,
        s.participants_count,
        s.eligible_count,
        s.created_at,
        s.completed_at,

        u.username AS winner_username

    FROM elite_game_sessions s

    LEFT JOIN users u
        ON u.id = s.winner_user_id

    ORDER BY s.scheduled_at DESC
    LIMIT 30
");

$sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| PARTICIPANTS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        e.id,
        e.session_id,
        e.user_id,
        e.color,
        e.created_at,

        u.username,
        u.email

    FROM elite_game_entries e

    INNER JOIN users u
        ON u.id = e.user_id

    ORDER BY e.created_at DESC

    LIMIT 100
");

$entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| DERNIÈRES MANCHES
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        r.id,
        r.round_key,
        r.scheduled_at,
        r.winning_color,
        r.winner_user_id,
        r.prize,
        r.status,
        r.drawn_at,

        u.username AS winner_username

    FROM elite_game_rounds r

    LEFT JOIN users u
        ON u.id = r.winner_user_id

    ORDER BY r.scheduled_at DESC

    LIMIT 30
");

$rounds = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

function memberStatus(string $status): string
{
    return match ($status) {
        'active'    => 'Actif',
        'pending'   => 'En attente',
        'expired'   => 'Expiré',
        'cancelled' => 'Annulé',
        default     => ucfirst($status)
    };
}

function gameStatus(string $status): string
{
    return match ($status) {
        'open'      => 'Ouverte',
        'drawing'   => 'Tirage',
        'completed' => 'Terminée',
        'no_winner' => 'Sans gagnant',
        default     => ucfirst($status)
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

<title>InvestPro — Elite</title>

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
    justify-content: space-between;
    align-items: center;
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

    margin-bottom: 22px;
}

.stat {
    background: #fff;

    border: 1px solid #e7eaf0;

    border-radius: 16px;

    padding: 18px;
}

.stat-label {
    color: #7b8495;
    font-size: 13px;

    margin-bottom: 7px;
}

.stat-value {
    font-size: 22px;
    font-weight: 800;
}

/* SECTION */

.section {
    margin-bottom: 24px;
}

.section-title {
    display: flex;
    align-items: center;
    justify-content: space-between;

    margin-bottom: 12px;
}

.section-title h2 {
    margin: 0;
    font-size: 18px;
}

/* CARD */

.card {
    background: #fff;

    border: 1px solid #e7eaf0;

    border-radius: 17px;

    overflow: hidden;
}

.table-wrapper {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;

    min-width: 950px;
}

th {
    padding: 13px 14px;

    background: #f8f9fc;

    text-align: left;

    font-size: 11px;

    text-transform: uppercase;

    color: #707a8d;
}

td {
    padding: 14px;

    border-top: 1px solid #eef0f4;

    vertical-align: middle;
}

.user {
    font-weight: 700;
}

.email {
    color: #858e9e;
    font-size: 12px;

    margin-top: 3px;
}

/* BADGES */

.badge {
    display: inline-block;

    padding: 6px 9px;

    border-radius: 999px;

    font-size: 11px;

    font-weight: 700;
}

.active,
.completed {
    background: #e8f8ef;
    color: #147a45;
}

.pending,
.open {
    background: #fff4d6;
    color: #946600;
}

.expired,
.cancelled,
.no_winner {
    background: #ffe9e9;
    color: #b42318;
}

.drawing {
    background: #eef2ff;
    color: #4f46e5;
}

/* COLOR */

.color {
    display: inline-flex;

    align-items: center;

    gap: 6px;

    font-weight: 700;
}

.dot {
    width: 9px;
    height: 9px;

    border-radius: 50%;

    display: inline-block;
}

.dot-red {
    background: #ef4444;
}

.dot-blue {
    background: #3b82f6;
}

.dot-green {
    background: #22c55e;
}

.dot-yellow {
    background: #eab308;
}

.empty {
    padding: 45px 20px;

    text-align: center;

    color: #7c8697;
}

/* MOBILE */

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

    <h1>Elite</h1>

    <a
        href="index.php"
        class="back"
    >
        ← Tableau de bord
    </a>

</header>

<main class="container">

<!-- STATISTIQUES -->

<section class="stats">

    <div class="stat">

        <div class="stat-label">
            Membres Elite
        </div>

        <div class="stat-value">
            <?= number_format(
                (int)$stats['members']['total']
            ) ?>
        </div>

    </div>

    <div class="stat">

        <div class="stat-label">
            Membres actifs
        </div>

        <div class="stat-value">
            <?= number_format(
                (int)$stats['members']['active']
            ) ?>
        </div>

    </div>

    <div class="stat">

        <div class="stat-label">
            Participants
        </div>

        <div class="stat-value">
            <?= number_format(
                (int)$stats['unique_participants']['total']
            ) ?>
        </div>

    </div>

    <div class="stat">

        <div class="stat-label">
            Participations
        </div>

        <div class="stat-value">
            <?= number_format(
                (int)$stats['entries']['total']
            ) ?>
        </div>

    </div>

</section>

<!-- MEMBRES -->

<section class="section">

<div class="section-title">

    <h2>
        Membres Elite
    </h2>

</div>

<div class="card">

<?php if (!$members): ?>

    <div class="empty">
        Aucun membre Elite.
    </div>

<?php else: ?>

<div class="table-wrapper">

<table>

<thead>

<tr>

<th>Utilisateur</th>
<th>Montant</th>
<th>Paiement</th>
<th>Référence</th>
<th>Statut</th>
<th>Activation</th>
<th>Expiration</th>

</tr>

</thead>

<tbody>

<?php foreach ($members as $member): ?>

<tr>

<td>

    <div class="user">
        <?= htmlspecialchars(
            $member['username']
        ) ?>
    </div>

    <div class="email">
        <?= htmlspecialchars(
            $member['email']
        ) ?>
    </div>

</td>

<td>

    <strong>
        <?= money($member['amount']) ?>
    </strong>

</td>

<td>

    <?= htmlspecialchars(
        $member['payment_method'] ?: '—'
    ) ?>

</td>

<td>

    <?= htmlspecialchars(
        $member['reference'] ?: '—'
    ) ?>

</td>

<td>

    <span
        class="badge <?= htmlspecialchars(
            $member['status']
        ) ?>"
    >
        <?= htmlspecialchars(
            memberStatus($member['status'])
        ) ?>
    </span>

</td>

<td>

    <?= $member['activated_at']
        ? htmlspecialchars(
            date(
                'd/m/Y H:i',
                strtotime($member['activated_at'])
            )
        )
        : '—'
    ?>

</td>

<td>

    <?= $member['expires_at']
        ? htmlspecialchars(
            date(
                'd/m/Y H:i',
                strtotime($member['expires_at'])
            )
        )
        : '—'
    ?>

</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

</div>

<?php endif; ?>

</div>

</section>

<!-- SESSIONS -->

<section class="section">

<div class="section-title">

    <h2>
        Sessions Elite
    </h2>

</div>

<div class="card">

<?php if (!$sessions): ?>

    <div class="empty">
        Aucune session.
    </div>

<?php else: ?>

<div class="table-wrapper">

<table>

<thead>

<tr>

<th>ID</th>
<th>Date</th>
<th>Participants</th>
<th>Éligibles</th>
<th>Prix</th>
<th>Couleur gagnante</th>
<th>Gagnant</th>
<th>Statut</th>

</tr>

</thead>

<tbody>

<?php foreach ($sessions as $session): ?>

<tr>

<td>
    #<?= (int)$session['id'] ?>
</td>

<td>

    <?= htmlspecialchars(
        date(
            'd/m/Y H:i',
            strtotime($session['scheduled_at'])
        )
    ) ?>

</td>

<td>
    <?= (int)$session['participants_count'] ?>
</td>

<td>
    <?= (int)$session['eligible_count'] ?>
</td>

<td>
    <strong>
        <?= money($session['prize_amount']) ?>
    </strong>
</td>

<td>

    <?= htmlspecialchars(
        $session['winning_color'] ?: '—'
    ) ?>

</td>

<td>

    <?= htmlspecialchars(
        $session['winner_username'] ?: '—'
    ) ?>

</td>

<td>

    <span
        class="badge <?= htmlspecialchars(
            $session['status']
        ) ?>"
    >
        <?= htmlspecialchars(
            gameStatus($session['status'])
        ) ?>
    </span>

</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

</div>

<?php endif; ?>

</div>

</section>

<!-- PARTICIPANTS -->

<section class="section">

<div class="section-title">

    <h2>
        Derniers participants
    </h2>

</div>

<div class="card">

<?php if (!$entries): ?>

    <div class="empty">
        Aucun participant.
    </div>

<?php else: ?>

<div class="table-wrapper">

<table>

<thead>

<tr>

<th>Utilisateur</th>
<th>Session</th>
<th>Choix</th>
<th>Date</th>

</tr>

</thead>

<tbody>

<?php foreach ($entries as $entry): ?>

<tr>

<td>

    <div class="user">
        <?= htmlspecialchars(
            $entry['username']
        ) ?>
    </div>

    <div class="email">
        <?= htmlspecialchars(
            $entry['email']
        ) ?>
    </div>

</td>

<td>

    #<?= (int)$entry['session_id'] ?>

</td>

<td>

    <span class="color">

        <span
            class="dot dot-<?= strtolower(
                preg_replace(
                    '/[^a-z]/i',
                    '',
                    $entry['color']
                )
            ) ?>"
        ></span>

        <?= htmlspecialchars(
            $entry['color']
        ) ?>

    </span>

</td>

<td>

    <?= htmlspecialchars(
        date(
            'd/m/Y H:i',
            strtotime($entry['created_at'])
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

</section>

<!-- MANCHES -->

<section class="section">

<div class="section-title">

    <h2>
        Dernières manches
    </h2>

</div>

<div class="card">

<?php if (!$rounds): ?>

    <div class="empty">
        Aucune manche enregistrée.
    </div>

<?php else: ?>

<div class="table-wrapper">

<table>

<thead>

<tr>

<th>Manche</th>
<th>Date prévue</th>
<th>Couleur gagnante</th>
<th>Gagnant</th>
<th>Prix</th>
<th>Statut</th>

</tr>

</thead>

<tbody>

<?php foreach ($rounds as $round): ?>

<tr>

<td>

    <strong>
        <?= htmlspecialchars(
            $round['round_key']
        ) ?>
    </strong>

</td>

<td>

    <?= htmlspecialchars(
        date(
            'd/m/Y H:i',
            strtotime($round['scheduled_at'])
        )
    ) ?>

</td>

<td>

    <?= htmlspecialchars(
        $round['winning_color'] ?: '—'
    ) ?>

</td>

<td>

    <?= htmlspecialchars(
        $round['winner_username'] ?: '—'
    ) ?>

</td>

<td>

    <strong>
        <?= money($round['prize']) ?>
    </strong>

</td>

<td>

    <span
        class="badge <?= htmlspecialchars(
            $round['status']
        ) ?>"
    >
        <?= htmlspecialchars(
            $round['status']
        ) ?>
    </span>

</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

</div>

<?php endif; ?>

</div>

</section>

</main>

</body>

</html>
