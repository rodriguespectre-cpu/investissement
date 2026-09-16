<?php

session_start();

require_once "../../config/database.php";

if (
    !isset($_SESSION['admin_id']) ||
    !isset($_SESSION['admin_role']) ||
    !in_array(
        $_SESSION['admin_role'],
        ['admin', 'super_admin'],
        true
    )
) {
    header("Location: login.php");
    exit;
}

$adminId = (int) $_SESSION['admin_id'];


$stmt = $pdo->prepare("
    SELECT id, username, email, role, balance, bonus_balance, is_active, created_at
    FROM users
    WHERE id = ?
    LIMIT 1
");
$stmt->execute([$adminId]);

$admin = $stmt->fetch(PDO::FETCH_ASSOC);

if (
    !$admin ||
    !in_array(
        $admin['role'],
        ['admin', 'super_admin'],
        true
    )
) {
    http_response_code(403);
    exit("Accès interdit.");
}

/*
|--------------------------------------------------------------------------
| RECHERCHE
|--------------------------------------------------------------------------
*/

$search = trim($_GET['search'] ?? '');

if ($search !== '') {

    $stmt = $pdo->prepare("
        SELECT
            u.id,
            u.username,
            u.email,
            u.role,
            u.balance,
            u.bonus_balance,
            u.is_active,
            u.created_at,

            COUNT(DISTINCT i.id) AS investment_count,
            COALESCE(SUM(
                CASE
                    WHEN i.status IN ('pending','active','completed')
                    THEN i.amount
                    ELSE 0
                END
            ), 0) AS investment_total

        FROM users u

        LEFT JOIN investments i
            ON i.user_id = u.id

        WHERE
            u.username LIKE ?
            OR u.email LIKE ?

        GROUP BY
            u.id,
            u.username,
            u.email,
            u.role,
            u.balance,
            u.bonus_balance,
            u.is_active,
            u.created_at

        ORDER BY u.id DESC
    ");

    $like = "%{$search}%";
    $stmt->execute([$like, $like]);

} else {

    $stmt = $pdo->query("
        SELECT
            u.id,
            u.username,
            u.email,
            u.role,
            u.balance,
            u.bonus_balance,
            u.is_active,
            u.created_at,

            COUNT(DISTINCT i.id) AS investment_count,
            COALESCE(SUM(
                CASE
                    WHEN i.status IN ('pending','active','completed')
                    THEN i.amount
                    ELSE 0
                END
            ), 0) AS investment_total

        FROM users u

        LEFT JOIN investments i
            ON i.user_id = u.id

        GROUP BY
            u.id,
            u.username,
            u.email,
            u.role,
            u.balance,
            u.bonus_balance,
            u.is_active,
            u.created_at

        ORDER BY u.id DESC
    ");
}

$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="fr">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Utilisateurs — InvestPro Admin</title>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #f5f7fb;
    color: #172033;
}

.container {
    max-width: 1250px;
    margin: auto;
    padding: 25px;
}

.top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    margin-bottom: 25px;
}

h1 {
    margin: 0;
    font-size: 25px;
}

.subtitle {
    margin-top: 6px;
    color: #7b8497;
    font-size: 14px;
}

.search {
    display: flex;
    gap: 8px;
    margin-bottom: 20px;
}

.search input {
    flex: 1;
    padding: 13px 15px;
    border: 1px solid #dfe4ec;
    border-radius: 10px;
    outline: none;
    background: white;
}

.search button {
    border: 0;
    border-radius: 10px;
    padding: 0 20px;
    background: #111827;
    color: white;
    cursor: pointer;
}

.card {
    background: white;
    border: 1px solid #e6e9ef;
    border-radius: 16px;
    overflow: hidden;
}

.table-wrap {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 850px;
}

th {
    text-align: left;
    font-size: 12px;
    color: #7b8497;
    text-transform: uppercase;
    padding: 15px;
    background: #fafbfc;
}

td {
    padding: 15px;
    border-top: 1px solid #edf0f4;
    font-size: 14px;
}

.user {
    display: flex;
    align-items: center;
    gap: 10px;
}

.avatar {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    background: #111827;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 13px;
    font-weight: bold;
}

.username {
    font-weight: 600;
}

.email {
    color: #8a93a5;
    font-size: 12px;
    margin-top: 3px;
}

.amount {
    font-weight: 600;
}

.status {
    display: inline-flex;
    padding: 5px 9px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: bold;
}

.active {
    background: #e8f8ef;
    color: #16834a;
}

.inactive {
    background: #feecec;
    color: #c62828;
}

.action {
    display: inline-block;
    padding: 8px 12px;
    border-radius: 8px;
    background: #111827;
    color: white;
    text-decoration: none;
    font-size: 12px;
}

.empty {
    padding: 50px;
    text-align: center;
    color: #8992a3;
}

.back {
    text-decoration: none;
    color: #687286;
    font-size: 13px;
}

</style>

</head>

<body>

<div class="container">

    <div class="top">

        <div>
            <a href="index.php" class="back">← Tableau de bord</a>

            <h1>Utilisateurs</h1>

            <div class="subtitle">
                Gestion des comptes, soldes et investissements
            </div>
        </div>

    </div>

    <form class="search" method="GET">

        <input
            type="search"
            name="search"
            value="<?= htmlspecialchars($search) ?>"
            placeholder="Rechercher par nom ou adresse email..."
        >

        <button type="submit">
            Rechercher
        </button>

    </form>

    <div class="card">

        <div class="table-wrap">

            <table>

                <thead>
                    <tr>
                        <th>Utilisateur</th>
                        <th>Solde</th>
                        <th>Bonus</th>
                        <th>Investissements</th>
                        <th>Total investi</th>
                        <th>Statut</th>
                        <th></th>
                    </tr>
                </thead>

                <tbody>

                <?php if (!$users): ?>

                    <tr>
                        <td colspan="7" class="empty">
                            Aucun utilisateur trouvé.
                        </td>
                    </tr>

                <?php else: ?>

                    <?php foreach ($users as $user): ?>

                        <?php
                        $name = $user['username'] ?: 'Utilisateur';

                        $initial =
                            strtoupper(
                                mb_substr($name, 0, 1)
                            );
                        ?>

                        <tr>

                            <td>

                                <div class="user">

                                    <div class="avatar">
                                        <?= htmlspecialchars($initial) ?>
                                    </div>

                                    <div>

                                        <div class="username">
                                            <?= htmlspecialchars($name) ?>
                                        </div>

                                        <div class="email">
                                            <?= htmlspecialchars($user['email']) ?>
                                        </div>

                                    </div>

                                </div>

                            </td>

                            <td class="amount">
                                <?= number_format(
                                    (float)$user['balance'],
                                    2,
                                    ',',
                                    ' '
                                ) ?>
                            </td>

                            <td>
                                <?= number_format(
                                    (float)$user['bonus_balance'],
                                    2,
                                    ',',
                                    ' '
                                ) ?>
                            </td>

                            <td>
                                <?= (int)$user['investment_count'] ?>
                            </td>

                            <td class="amount">
                                <?= number_format(
                                    (float)$user['investment_total'],
                                    2,
                                    ',',
                                    ' '
                                ) ?>
                            </td>

                            <td>

                                <?php if ((int)$user['is_active'] === 1): ?>

                                    <span class="status active">
                                        Actif
                                    </span>

                                <?php else: ?>

                                    <span class="status inactive">
                                        Désactivé
                                    </span>

                                <?php endif; ?>

                            </td>

                            <td>

                                <a
                                    class="action"
                                    href="utilisateur.php?id=<?= (int)$user['id'] ?>"
                                >
                                    Gérer
                                </a>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>

</body>
</html>
