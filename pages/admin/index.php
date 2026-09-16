<?php

session_start();

require_once '../../config/database.php';

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
    header('Location: login.php');
    exit;
}

$adminId = (int) $_SESSION['admin_id'];
$adminRole = $_SESSION['admin_role'];
$adminUsername = $_SESSION['admin_username'] ?? 'Admin';

/*
|--------------------------------------------------------------------------
| STATISTIQUES
|--------------------------------------------------------------------------
*/

try {

    // Utilisateurs
    $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM users
        WHERE role = 'user'
    ");

    $totalUsers = (int) $stmt->fetchColumn();


    // Solde total des utilisateurs
    $stmt = $pdo->query("
        SELECT COALESCE(SUM(balance + bonus_balance), 0)
        FROM users
        WHERE role = 'user'
    ");

    $totalBalance = (float) $stmt->fetchColumn();


    // Total investi
    $stmt = $pdo->query("
        SELECT COALESCE(SUM(amount), 0)
        FROM investments
        WHERE status IN ('pending', 'active', 'completed')
    ");

    $totalInvested = (float) $stmt->fetchColumn();


    // Retraits en attente
    $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM transactions
        WHERE type = 'withdraw'
        AND status = 'pending'
    ");

    $pendingWithdrawals = (int) $stmt->fetchColumn();


    // Nombre de retraits
    $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM transactions
        WHERE type = 'withdraw'
    ");

    $totalWithdrawals = (int) $stmt->fetchColumn();


    // Participants Elite
    $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM elite_game_entries
    ");

    $eliteParticipants = (int) $stmt->fetchColumn();


    // Conversations
    $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM chat_messages
    ");

    $chatMessages = (int) $stmt->fetchColumn();


    // Support
    $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM support_tickets
        WHERE status IN ('open', 'in_progress')
    ");

    $openSupport = (int) $stmt->fetchColumn();


    // Administrateurs
    $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM users
        WHERE role IN ('admin', 'super_admin')
    ");

    $totalAdmins = (int) $stmt->fetchColumn();


    // Transactions récentes
    $stmt = $pdo->query("
        SELECT
            t.id,
            t.type,
            t.amount,
            t.status,
            t.created_at,
            u.username
        FROM transactions t
        INNER JOIN users u
            ON u.id = t.user_id
        ORDER BY t.created_at DESC
        LIMIT 6
    ");

    $recentTransactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {

    $totalUsers = 0;
    $totalBalance = 0;
    $totalInvested = 0;
    $pendingWithdrawals = 0;
    $totalWithdrawals = 0;
    $eliteParticipants = 0;
    $chatMessages = 0;
    $openSupport = 0;
    $totalAdmins = 0;
    $recentTransactions = [];
}


/*
|--------------------------------------------------------------------------
| FORMAT MONNAIE
|--------------------------------------------------------------------------
*/

function money(float $amount): string
{
    return number_format(
        $amount,
        2,
        ',',
        ' '
    ) . ' XAF';
}


function e(string $value): string
{
    return htmlspecialchars(
        $value,
        ENT_QUOTES,
        'UTF-8'
    );
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

<title>InvestPro — Administration</title>

<style>

* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

body {
    font-family:
        Inter,
        -apple-system,
        BlinkMacSystemFont,
        "Segoe UI",
        sans-serif;

    background: #f5f7fa;
    color: #172033;
}


/* =========================================================
   SIDEBAR
========================================================= */

.sidebar {

    position: fixed;

    top: 0;
    left: 0;

    width: 250px;
    height: 100vh;

    background: #111827;
    color: white;

    z-index: 1000;

    padding: 22px 15px;

    transition: .25s;
}

.logo {

    display: flex;
    align-items: center;

    gap: 10px;

    padding: 5px 10px 25px;

    font-size: 20px;
    font-weight: 800;
}

.logo-icon {

    width: 38px;
    height: 38px;

    border-radius: 11px;

    background: white;
    color: #111827;

    display: flex;
    align-items: center;
    justify-content: center;

    font-size: 14px;
    font-weight: 900;
}

.logo small {

    display: block;

    font-size: 10px;

    color: #9ca3af;

    margin-top: 2px;

    font-weight: 500;
}

.menu-title {

    color: #6b7280;

    font-size: 10px;

    text-transform: uppercase;

    letter-spacing: 1px;

    padding: 10px 12px;

    margin-top: 4px;
}

.menu a {

    display: flex;

    align-items: center;

    gap: 12px;

    color: #cbd5e1;

    text-decoration: none;

    padding: 11px 12px;

    border-radius: 10px;

    font-size: 13px;

    margin: 2px 0;

    transition: .2s;
}

.menu a:hover,
.menu a.active {

    background: #1f2937;

    color: white;
}

.menu-icon {

    width: 20px;

    text-align: center;

    font-size: 15px;
}

.badge {

    margin-left: auto;

    min-width: 21px;

    height: 21px;

    padding: 0 6px;

    border-radius: 20px;

    background: #ef4444;

    color: white;

    font-size: 10px;

    display: flex;

    align-items: center;

    justify-content: center;
}

.sidebar-bottom {

    position: absolute;

    bottom: 20px;

    left: 15px;
    right: 15px;

    border-top: 1px solid #263142;

    padding-top: 15px;
}

.admin-mini {

    display: flex;

    align-items: center;

    gap: 10px;

    padding: 8px;
}

.admin-avatar {

    width: 34px;
    height: 34px;

    border-radius: 50%;

    background: #374151;

    display: flex;

    align-items: center;
    justify-content: center;

    font-size: 12px;
    font-weight: 700;
}

.admin-info {

    min-width: 0;
}

.admin-info strong {

    display: block;

    font-size: 12px;

    white-space: nowrap;

    overflow: hidden;

    text-overflow: ellipsis;
}

.admin-info span {

    color: #9ca3af;

    font-size: 10px;
}


/* =========================================================
   MAIN
========================================================= */

.main {

    margin-left: 250px;

    min-height: 100vh;

    transition: .25s;
}

.topbar {

    height: 70px;

    background: white;

    border-bottom: 1px solid #e8ebef;

    display: flex;

    align-items: center;

    justify-content: space-between;

    padding: 0 28px;

    position: sticky;

    top: 0;

    z-index: 500;
}

.topbar-left {

    display: flex;

    align-items: center;

    gap: 15px;
}

.menu-toggle {

    display: none;

    border: 0;

    background: transparent;

    font-size: 25px;

    cursor: pointer;

    color: #172033;
}

.page-title h1 {

    font-size: 20px;

    font-weight: 750;
}

.page-title p {

    color: #8a93a3;

    font-size: 12px;

    margin-top: 2px;
}

.role {

    padding: 7px 10px;

    border-radius: 20px;

    background: #f1f5f9;

    color: #475569;

    font-size: 11px;

    font-weight: 700;
}

.content {

    padding: 25px 28px 40px;
}


/* =========================================================
   STATS
========================================================= */

.stats {

    display: grid;

    grid-template-columns:
        repeat(4, minmax(0, 1fr));

    gap: 15px;

    margin-bottom: 22px;
}

.stat {

    background: white;

    border: 1px solid #e8ebef;

    border-radius: 15px;

    padding: 18px;

    min-width: 0;
}

.stat-top {

    display: flex;

    justify-content: space-between;

    align-items: center;

    margin-bottom: 13px;
}

.stat-label {

    color: #7b8495;

    font-size: 11px;

    font-weight: 600;
}

.stat-icon {

    width: 31px;
    height: 31px;

    border-radius: 9px;

    background: #f3f4f6;

    display: flex;

    align-items: center;
    justify-content: center;

    font-size: 14px;
}

.stat-value {

    font-size: 20px;

    font-weight: 800;

    color: #111827;

    white-space: nowrap;

    overflow: hidden;

    text-overflow: ellipsis;
}

.stat-sub {

    color: #9aa1ad;

    font-size: 10px;

    margin-top: 5px;
}


/* =========================================================
   QUICK MENU
========================================================= */

.section-title {

    display: flex;

    justify-content: space-between;

    align-items: center;

    margin-bottom: 12px;
}

.section-title h2 {

    font-size: 15px;

    font-weight: 750;
}

.section-title a {

    color: #64748b;

    font-size: 11px;

    text-decoration: none;
}

.quick-grid {

    display: grid;

    grid-template-columns:
        repeat(4, minmax(0, 1fr));

    gap: 12px;

    margin-bottom: 25px;
}

.quick {

    background: white;

    border: 1px solid #e8ebef;

    border-radius: 13px;

    padding: 16px;

    text-decoration: none;

    color: #172033;

    transition: .2s;
}

.quick:hover {

    border-color: #cbd5e1;

    transform: translateY(-1px);
}

.quick-icon {

    font-size: 19px;

    margin-bottom: 10px;
}

.quick strong {

    display: block;

    font-size: 12px;
}

.quick span {

    display: block;

    color: #8a93a3;

    font-size: 10px;

    margin-top: 4px;
}


/* =========================================================
   BOTTOM GRID
========================================================= */

.dashboard-grid {

    display: grid;

    grid-template-columns:
        minmax(0, 2fr)
        minmax(260px, 1fr);

    gap: 15px;
}

.panel {

    background: white;

    border: 1px solid #e8ebef;

    border-radius: 15px;

    overflow: hidden;
}

.panel-head {

    padding: 16px 18px;

    border-bottom: 1px solid #edf0f3;

    display: flex;

    align-items: center;

    justify-content: space-between;
}

.panel-head h2 {

    font-size: 13px;

    font-weight: 750;
}

.panel-head a {

    font-size: 10px;

    color: #64748b;

    text-decoration: none;
}

.transaction {

    display: flex;

    align-items: center;

    justify-content: space-between;

    padding: 13px 18px;

    border-bottom: 1px solid #f0f2f5;
}

.transaction:last-child {
    border-bottom: 0;
}

.transaction-left {

    display: flex;

    align-items: center;

    gap: 10px;

    min-width: 0;
}

.transaction-icon {

    width: 31px;
    height: 31px;

    border-radius: 9px;

    background: #f3f4f6;

    display: flex;

    align-items: center;
    justify-content: center;

    font-size: 12px;
}

.transaction-user {

    min-width: 0;
}

.transaction-user strong {

    display: block;

    font-size: 11px;

    white-space: nowrap;

    overflow: hidden;

    text-overflow: ellipsis;
}

.transaction-user span {

    display: block;

    color: #9aa1ad;

    font-size: 9px;

    margin-top: 2px;
}

.transaction-right {

    text-align: right;

    margin-left: 10px;
}

.amount {

    font-size: 11px;

    font-weight: 750;
}

.status {

    display: inline-block;

    margin-top: 3px;

    padding: 3px 7px;

    border-radius: 10px;

    font-size: 8px;

    background: #f1f5f9;

    color: #64748b;
}


/* =========================================================
   OVERVIEW
========================================================= */

.overview-item {

    display: flex;

    align-items: center;

    justify-content: space-between;

    padding: 14px 18px;

    border-bottom: 1px solid #f0f2f5;
}

.overview-item:last-child {
    border-bottom: 0;
}

.overview-label {

    display: flex;

    align-items: center;

    gap: 9px;

    font-size: 11px;

    color: #64748b;
}

.overview-value {

    font-size: 12px;

    font-weight: 750;
}

.alert {

    color: #dc2626;
}


/* =========================================================
   OVERLAY
========================================================= */

.overlay {

    display: none;

    position: fixed;

    inset: 0;

    background: rgba(0,0,0,.35);

    z-index: 900;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 1050px) {

    .stats {

        grid-template-columns:
            repeat(2, minmax(0, 1fr));
    }

    .quick-grid {

        grid-template-columns:
            repeat(2, minmax(0, 1fr));
    }

    .dashboard-grid {

        grid-template-columns: 1fr;
    }
}

@media (max-width: 760px) {

    .sidebar {

        left: -260px;
    }

    .sidebar.open {

        left: 0;
    }

    .main {

        margin-left: 0;
    }

    .menu-toggle {

        display: block;
    }

    .overlay.open {

        display: block;
    }

    .topbar {

        padding: 0 17px;
    }

    .content {

        padding: 18px 15px 30px;
    }

    .stats {

        grid-template-columns:
            repeat(2, minmax(0, 1fr));

        gap: 10px;
    }

    .stat {

        padding: 14px;
    }

    .stat-value {

        font-size: 17px;
    }

    .role {

        display: none;
    }
}

@media (max-width: 450px) {

    .quick-grid {

        grid-template-columns: 1fr 1fr;
    }

    .quick {

        padding: 13px;
    }

    .quick-icon {

        font-size: 17px;
    }

    .quick strong {

        font-size: 11px;
    }
}

</style>

</head>

<body>


<!-- ======================================================
     SIDEBAR
====================================================== -->

<aside
    class="sidebar"
    id="sidebar"
>

    <div class="logo">

        <div class="logo-icon">
            IP
        </div>

        <div>
            InvestPro
            <small>Administration</small>
        </div>

    </div>


    <div class="menu-title">
        Principal
    </div>

    <nav class="menu">

        <a
            href="index.php"
            class="active"
        >
            <span class="menu-icon">▦</span>
            Tableau de bord
        </a>

        <a href="utlisateurs.php">
            <span class="menu-icon">👥</span>
            Utilisateurs
        </a>

        <a href="transactions.php">
            <span class="menu-icon">↔</span>
            Transactions
        </a>

        <a href="retraits.php">

            <span class="menu-icon">💸</span>

            Retraits

            <?php if ($pendingWithdrawals > 0): ?>

                <span class="badge">
                    <?= $pendingWithdrawals ?>
                </span>

            <?php endif; ?>

        </a>

    </nav>


    <div class="menu-title">
        Services
    </div>

    <nav class="menu">

        <a href="elite.php">
            <span class="menu-icon">👑</span>
            Elite

            <?php if ($eliteParticipants > 0): ?>

                <span class="badge">
                    <?= $eliteParticipants ?>
                </span>

            <?php endif; ?>

        </a>

        <a href="conservation.php">
            <span class="menu-icon">💬</span>
            Conversations
        </a>

        <a href="support.php">
            <span class="menu-icon">🎫</span>
            Support

            <?php if ($openSupport > 0): ?>

                <span class="badge">
                    <?= $openSupport ?>
                </span>

            <?php endif; ?>

        </a>

        <a href="notifications.php">
            <span class="menu-icon">🔔</span>
            Notifications
        </a>

    </nav>


    <div class="menu-title">
        Administration
    </div>

    <nav class="menu">

        <a href="administrateurs.php">
            <span class="menu-icon">🛡</span>
            Administrateurs
        </a>

    </nav>


    <div class="sidebar-bottom">

        <div class="admin-mini">

            <div class="admin-avatar">
                <?= e(
                    strtoupper(
                        substr($adminUsername, 0, 1)
                    )
                ) ?>
            </div>

            <div class="admin-info">

                <strong>
                    <?= e($adminUsername) ?>
                </strong>

                <span>
                    <?= $adminRole === 'super_admin'
                        ? 'Super administrateur'
                        : 'Administrateur'
                    ?>
                </span>

            </div>

        </div>

        <div class="menu">

            <a href="logout.php">
                <span class="menu-icon">↪</span>
                Déconnexion
            </a>

        </div>

    </div>

</aside>


<div
    class="overlay"
    id="overlay"
></div>


<!-- ======================================================
     MAIN
====================================================== -->

<div class="main">


    <header class="topbar">

        <div class="topbar-left">

            <button
                class="menu-toggle"
                id="menuToggle"
                type="button"
                aria-label="Menu"
            >
                ☰
            </button>

            <div class="page-title">

                <h1>
                    Tableau de bord
                </h1>

                <p>
                    Vue générale de votre plateforme
                </p>

            </div>

        </div>


        <div class="role">

            <?= $adminRole === 'super_admin'
                ? 'SUPER ADMIN'
                : 'ADMIN'
            ?>

        </div>

    </header>


    <main class="content">


        <!-- ==================================================
             STATISTIQUES
        =================================================== -->

        <section class="stats">


            <div class="stat">

                <div class="stat-top">

                    <span class="stat-label">
                        Solde utilisateurs
                    </span>

                    <span class="stat-icon">
                        💰
                    </span>

                </div>

                <div class="stat-value">
                    <?= money($totalBalance) ?>
                </div>

                <div class="stat-sub">
                    Balance + bonus
                </div>

            </div>


            <div class="stat">

                <div class="stat-top">

                    <span class="stat-label">
                        Total investi
                    </span>

                    <span class="stat-icon">
                        📈
                    </span>

                </div>

                <div class="stat-value">
                    <?= money($totalInvested) ?>
                </div>

                <div class="stat-sub">
                    Tous les investissements
                </div>

            </div>


            <div class="stat">

                <div class="stat-top">

                    <span class="stat-label">
                        Utilisateurs
                    </span>

                    <span class="stat-icon">
                        👥
                    </span>

                </div>

                <div class="stat-value">
                    <?= $totalUsers ?>
                </div>

                <div class="stat-sub">
                    Comptes utilisateurs
                </div>

            </div>


            <div class="stat">

                <div class="stat-top">

                    <span class="stat-label">
                        Retraits en attente
                    </span>

                    <span class="stat-icon">
                        💸
                    </span>

                </div>

                <div class="stat-value alert">
                    <?= $pendingWithdrawals ?>
                </div>

                <div class="stat-sub">
                    Action requise
                </div>

            </div>


        </section>


        <!-- ==================================================
             ACCÈS RAPIDES
        =================================================== -->

        <div class="section-title">

            <h2>
                Accès rapides
            </h2>

        </div>


        <section class="quick-grid">


            <a
                href="utlisateurs.php"
                class="quick"
            >

                <div class="quick-icon">
                    👥
                </div>

                <strong>
                    Utilisateurs
                </strong>

                <span>
                    Profils, soldes et investissements
                </span>

            </a>


            <a
                href="retraits.php"
                class="quick"
            >

                <div class="quick-icon">
                    💸
                </div>

                <strong>
                    Retraits
                </strong>

                <span>
                    <?= $pendingWithdrawals ?>
                    en attente
                </span>

            </a>


            <a
                href="elite.php"
                class="quick"
            >

                <div class="quick-icon">
                    👑
                </div>

                <strong>
                    Elite
                </strong>

                <span>
                    <?= $eliteParticipants ?>
                    participants
                </span>

            </a>


            <a
                href="support.php"
                class="quick"
            >

                <div class="quick-icon">
                    🎫
                </div>

                <strong>
                    Support
                </strong>

                <span>
                    <?= $openSupport ?>
                    demandes ouvertes
                </span>

            </a>


        </section>


        <!-- ==================================================
             TABLEAUX
        =================================================== -->

        <section class="dashboard-grid">


            <!-- TRANSACTIONS -->

            <div class="panel">

                <div class="panel-head">

                    <h2>
                        Transactions récentes
                    </h2>

                    <a href="transactions.php">
                        Voir tout →
                    </a>

                </div>


                <?php if (empty($recentTransactions)): ?>

                    <div style="
                        padding:30px;
                        text-align:center;
                        color:#9aa1ad;
                        font-size:12px;
                    ">
                        Aucune transaction.
                    </div>

                <?php else: ?>


                    <?php foreach (
                        $recentTransactions
                        as $transaction
                    ): ?>

                        <div class="transaction">

                            <div class="transaction-left">

                                <div class="transaction-icon">
                                    <?= $transaction['type'] === 'withdraw'
                                        ? '↗'
                                        : '↙'
                                    ?>
                                </div>

                                <div class="transaction-user">

                                    <strong>
                                        <?= e(
                                            $transaction['username']
                                        ) ?>
                                    </strong>

                                    <span>
                                        <?= e(
                                            ucfirst(
                                                $transaction['type']
                                            )
                                        ) ?>

                                        ·

                                        <?= date(
                                            'd/m/Y H:i',
                                            strtotime(
                                                $transaction['created_at']
                                            )
                                        ) ?>
                                    </span>

                                </div>

                            </div>


                            <div class="transaction-right">

                                <div class="amount">

                                    <?= money(
                                        (float)
                                        $transaction['amount']
                                    ) ?>

                                </div>

                                <span class="status">
                                    <?= e(
                                        ucfirst(
                                            $transaction['status']
                                        )
                                    ) ?>
                                </span>

                            </div>

                        </div>

                    <?php endforeach; ?>

                <?php endif; ?>

            </div>


            <!-- VUE RAPIDE -->

            <div class="panel">

                <div class="panel-head">

                    <h2>
                        Vue rapide
                    </h2>

                </div>


                <div class="overview-item">

                    <div class="overview-label">
                        👥 Utilisateurs
                    </div>

                    <div class="overview-value">
                        <?= $totalUsers ?>
                    </div>

                </div>


                <div class="overview-item">

                    <div class="overview-label">
                        🛡 Administrateurs
                    </div>

                    <div class="overview-value">
                        <?= $totalAdmins ?>
                    </div>

                </div>


                <div class="overview-item">

                    <div class="overview-label">
                        💬 Messages
                    </div>

                    <div class="overview-value">
                        <?= $chatMessages ?>
                    </div>

                </div>


                <div class="overview-item">

                    <div class="overview-label">
                        👑 Elite
                    </div>

                    <div class="overview-value">
                        <?= $eliteParticipants ?>
                    </div>

                </div>


                <div class="overview-item">

                    <div class="overview-label">
                        🎫 Support ouvert
                    </div>

                    <div class="overview-value <?= $openSupport > 0
                        ? 'alert'
                        : ''
                    ?>">
                        <?= $openSupport ?>
                    </div>

                </div>


                <div class="overview-item">

                    <div class="overview-label">
                        💸 Retraits
                    </div>

                    <div class="overview-value <?= $pendingWithdrawals > 0
                        ? 'alert'
                        : ''
                    ?>">
                        <?= $pendingWithdrawals ?>
                    </div>

                </div>


            </div>


        </section>


    </main>

</div>


<script>

const sidebar =
    document.getElementById('sidebar');

const overlay =
    document.getElementById('overlay');

const menuToggle =
    document.getElementById('menuToggle');


function openMenu() {

    sidebar.classList.add('open');

    overlay.classList.add('open');

}


function closeMenu() {

    sidebar.classList.remove('open');

    overlay.classList.remove('open');

}


menuToggle.addEventListener(
    'click',
    openMenu
);


overlay.addEventListener(
    'click',
    closeMenu
);


document
    .querySelectorAll('.sidebar a')
    .forEach(function(link) {

        link.addEventListener(
            'click',
            function() {

                if (
                    window.innerWidth <= 760
                ) {
                    closeMenu();
                }

            }
        );

    });

</script>

</body>

</html>
