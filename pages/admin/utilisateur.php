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

/*
|--------------------------------------------------------------------------
| ADMIN
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT id, username, email, role
    FROM users
    WHERE id = ?
    LIMIT 1
");
$stmt->execute([$adminId]);

$admin = $stmt->fetch(PDO::FETCH_ASSOC);

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

/*
|--------------------------------------------------------------------------
| UTILISATEUR
|--------------------------------------------------------------------------
*/

$userId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$userId) {
    header("Location: utilisateurs.php");
    exit;
}

$stmt = $pdo->prepare("
    SELECT *
    FROM users
    WHERE id = ?
    LIMIT 1
");
$stmt->execute([$userId]);

$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    exit("Utilisateur introuvable.");
}

/*
|--------------------------------------------------------------------------
| CRÉDIT / DÉBIT
|--------------------------------------------------------------------------
*/

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    /*
    |----------------------------------------------------------------------
    | MODIFIER LE SOLDE
    |----------------------------------------------------------------------
    */

    if ($action === 'balance') {

        $amount = (float) ($_POST['amount'] ?? 0);
        $operation = $_POST['operation'] ?? '';

        if ($amount <= 0) {
            $error = "Montant invalide.";

        } elseif (!in_array($operation, ['credit', 'debit'], true)) {
            $error = "Opération invalide.";

        } else {

            try {

                $pdo->beginTransaction();

                if ($operation === 'credit') {

                    $stmt = $pdo->prepare("
                        UPDATE users
                        SET balance = balance + ?
                        WHERE id = ?
                    ");

                    $stmt->execute([$amount, $userId]);

                    $description =
                        "Crédit administrateur";

                } else {

                    $stmt = $pdo->prepare("
                        UPDATE users
                        SET balance =
                            CASE
                                WHEN balance >= ?
                                THEN balance - ?
                                ELSE 0
                            END
                        WHERE id = ?
                    ");

                    $stmt->execute([
                        $amount,
                        $amount,
                        $userId
                    ]);

                    $description =
                        "Débit administrateur";
                }

                /*
                |------------------------------------------------------------------
                | HISTORIQUE
                |------------------------------------------------------------------
                */

                $stmt = $pdo->prepare("
                    INSERT INTO transactions
                    (
                        user_id,
                        type,
                        amount,
                        status,
                        description,
                        completed_at
                    )
                    VALUES
                    (
                        ?,
                        'deposit',
                        ?,
                        'completed',
                        ?,
                        NOW()
                    )
                ");

                $stmt->execute([
                    $userId,
                    $amount,
                    $description
                ]);

                $pdo->commit();

                $success =
                    $operation === 'credit'
                        ? "Crédit ajouté avec succès."
                        : "Crédit retiré avec succès.";

            } catch (Throwable $e) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $error =
                    "Impossible de modifier le solde.";
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | NOTIFICATION
    |--------------------------------------------------------------------------
    */

   if ($action === 'notification') {

    $message = trim($_POST['message'] ?? '');

    if ($message === '') {
        $error = "Le message est vide.";
    } else {

        try {
            $stmt = $pdo->prepare("
                INSERT INTO notifications 
                (user_id, type, title, message) 
                VALUES (?, ?, ?, ?)
            ");

            $stmt->execute([
                $userId,
                'info',                    // type par défaut
                'Notification administrateur',  // titre
                $message
            ]);

            $success = "Notification envoyée.";

        } catch (Throwable $e) {
            $error = "Impossible d'envoyer la notification : " . $e->getMessage();
        }
    }
}
    /*
    |--------------------------------------------------------------------------
    | RECHARGER LES DONNÉES
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT *
        FROM users
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([$userId]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);
}

/*
|--------------------------------------------------------------------------
| INVESTISSEMENTS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        plan_name,
        amount,
        currency_code,
        profit_percent,
        duration_days,
        start_date,
        end_date,
        status,
        total_profit,
        return_amount
    FROM investments
    WHERE user_id = ?
    ORDER BY id DESC
");

$stmt->execute([$userId]);

$investments = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| TRANSACTIONS
|--------------------------------------------------------------------------
*/

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
        created_at,
        completed_at
    FROM transactions
    WHERE user_id = ?
    ORDER BY id DESC
    LIMIT 30
");

$stmt->execute([$userId]);

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
    ORDER BY id DESC
    LIMIT 10
");

$stmt->execute([$userId]);

$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($user['username']) ?> — InvestPro</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">

    <style>
        /* ============================================================
           VARIABLES
           ============================================================ */
        :root {
            --primary: #4f46e5;
            --primary-dark: #4338ca;
            --primary-light: #818cf8;
            --primary-bg: #eef2ff;
            --secondary: #0f172a;
            --secondary-light: #1e293b;
            --success: #10b981;
            --success-dark: #059669;
            --success-bg: #ecfdf5;
            --danger: #ef4444;
            --danger-dark: #dc2626;
            --danger-bg: #fef2f2;
            --warning: #f59e0b;
            --warning-bg: #fffbeb;
            --info: #3b82f6;
            --info-bg: #eff6ff;
            --gray-50: #f8fafc;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-300: #cbd5e1;
            --gray-400: #94a3b8;
            --gray-500: #64748b;
            --gray-600: #475569;
            --gray-700: #334155;
            --gray-800: #1e293b;
            --gray-900: #0f172a;
            --border-radius: 16px;
            --border-radius-sm: 10px;
            --border-radius-xs: 6px;
            --shadow: 0 1px 3px rgba(0, 0, 0, 0.06), 0 1px 2px rgba(0, 0, 0, 0.04);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.07), 0 2px 4px -1px rgba(0, 0, 0, 0.04);
            --shadow-lg: 0 10px 25px -3px rgba(0, 0, 0, 0.08), 0 4px 6px -2px rgba(0, 0, 0, 0.03);
            --shadow-xl: 0 20px 50px -8px rgba(0, 0, 0, 0.12);
            --font: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            --transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* ============================================================
           RESET & BASE
           ============================================================ */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: var(--font);
            background: var(--gray-100);
            color: var(--gray-900);
            line-height: 1.6;
            min-height: 100vh;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        /* ============================================================
           CONTAINER
           ============================================================ */
        .container {
            max-width: 1280px;
            margin: 0 auto;
            padding: 24px 32px 48px;
        }

        /* ============================================================
           BACK LINK
           ============================================================ */
        .back {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--gray-500);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            padding: 8px 16px 8px 12px;
            border-radius: var(--border-radius-sm);
            background: #fff;
            border: 1px solid var(--gray-200);
            transition: var(--transition);
            box-shadow: var(--shadow);
        }

        .back:hover {
            background: var(--gray-50);
            border-color: var(--gray-300);
            transform: translateX(-4px);
            box-shadow: var(--shadow-md);
        }

        .back i {
            font-size: 14px;
        }

        /* ============================================================
           HEADER
           ============================================================ */
        .header {
            margin: 20px 0 24px;
            padding: 24px 28px;
            background: #fff;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-md);
            border: 1px solid var(--gray-200);
        }

        .header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 800;
            color: var(--gray-900);
            letter-spacing: -0.5px;
        }

        .header .email {
            color: var(--gray-500);
            font-size: 15px;
            margin-top: 2px;
            font-weight: 400;
        }

        .header .email i {
            margin-right: 6px;
            font-size: 14px;
        }

        /* ============================================================
           ALERTES
           ============================================================ */
        .alert {
            padding: 14px 20px;
            border-radius: var(--border-radius-sm);
            margin-bottom: 20px;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 12px;
            border: 1px solid transparent;
            animation: slideDown 0.4s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-12px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .success {
            background: var(--success-bg);
            color: var(--success-dark);
            border-color: #a7f3d0;
        }

        .success i {
            color: var(--success);
            font-size: 18px;
        }

        .error {
            background: var(--danger-bg);
            color: var(--danger-dark);
            border-color: #fca5a5;
        }

        .error i {
            color: var(--danger);
            font-size: 18px;
        }

        /* ============================================================
           PROFIL
           ============================================================ */
        .profile {
            background: #fff;
            border: 1px solid var(--gray-200);
            border-radius: var(--border-radius);
            padding: 24px 28px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            box-shadow: var(--shadow-md);
            transition: var(--transition);
        }

        .profile:hover {
            box-shadow: var(--shadow-lg);
        }

        .identity {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .avatar {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 20px;
            flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
            transition: var(--transition);
        }

        .profile:hover .avatar {
            transform: scale(1.05);
        }

        .identity strong {
            font-size: 18px;
            font-weight: 700;
            color: var(--gray-900);
        }

        .identity .email {
            font-size: 14px;
            color: var(--gray-500);
            margin-top: 0;
        }

        .identity .email i {
            margin-right: 4px;
            font-size: 12px;
        }

        .balance {
            font-size: 28px;
            font-weight: 800;
            color: var(--gray-900);
            text-align: right;
            letter-spacing: -0.5px;
        }

        .balance small {
            display: block;
            font-size: 13px;
            font-weight: 500;
            color: var(--gray-500);
            letter-spacing: 0;
        }

        .balance i {
            color: var(--primary);
            margin-right: 6px;
            font-size: 24px;
        }

        /* ============================================================
           GRILLE
           ============================================================ */
        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-top: 20px;
        }

        .full {
            grid-column: 1 / -1;
        }

        /* ============================================================
           CARTES
           ============================================================ */
        .card {
            background: #fff;
            border: 1px solid var(--gray-200);
            border-radius: var(--border-radius);
            padding: 24px 28px;
            box-shadow: var(--shadow-md);
            transition: var(--transition);
        }

        .card:hover {
            box-shadow: var(--shadow-lg);
        }

        .card h2 {
            margin: 0 0 18px 0;
            font-size: 18px;
            font-weight: 700;
            color: var(--gray-900);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card h2 i {
            color: var(--primary);
            font-size: 20px;
        }

        /* ============================================================
           FORMULAIRES
           ============================================================ */
        input,
        textarea,
        select {
            width: 100%;
            border: 2px solid var(--gray-200);
            border-radius: var(--border-radius-sm);
            padding: 12px 16px;
            margin-bottom: 12px;
            font-family: var(--font);
            font-size: 14px;
            color: var(--gray-900);
            background: var(--gray-50);
            transition: var(--transition);
            outline: none;
        }

        input:focus,
        textarea:focus,
        select:focus {
            border-color: var(--primary);
            background: #fff;
            box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1);
        }

        input::placeholder,
        textarea::placeholder {
            color: var(--gray-400);
        }

        textarea {
            min-height: 100px;
            resize: vertical;
        }

        /* ============================================================
           BOUTONS
           ============================================================ */
        .buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        button {
            border: none;
            padding: 11px 20px;
            border-radius: var(--border-radius-sm);
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            font-family: var(--font);
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            justify-content: center;
        }

        button:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        button:active {
            transform: translateY(0);
        }

        .credit {
            background: linear-gradient(135deg, var(--success), var(--success-dark));
            color: #fff;
            flex: 1;
        }

        .credit:hover {
            box-shadow: 0 8px 24px rgba(16, 185, 129, 0.35);
        }

        .debit {
            background: linear-gradient(135deg, var(--danger), var(--danger-dark));
            color: #fff;
            flex: 1;
        }

        .debit:hover {
            box-shadow: 0 8px 24px rgba(239, 68, 68, 0.35);
        }

        .send {
            background: linear-gradient(135deg, var(--secondary), var(--secondary-light));
            color: #fff;
            width: 100%;
        }

        .send:hover {
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.3);
        }

        button i {
            font-size: 14px;
        }

        /* ============================================================
           TABLEAUX
           ============================================================ */
        .table-wrap {
            overflow-x: auto;
            margin: 0 -4px;
            padding: 0 4px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
            min-width: 600px;
        }

        thead th {
            text-align: left;
            padding: 12px 16px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--gray-500);
            background: var(--gray-50);
            border-bottom: 2px solid var(--gray-200);
        }

        thead th i {
            margin-right: 4px;
            font-size: 12px;
        }

        tbody td {
            padding: 12px 16px;
            border-bottom: 1px solid var(--gray-100);
            color: var(--gray-700);
            font-size: 13px;
        }

        tbody tr {
            transition: var(--transition);
        }

        tbody tr:hover td {
            background: var(--gray-50);
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        /* ============================================================
           BADGES DE STATUT
           ============================================================ */
        .status {
            display: inline-flex;
            align-items: center;
            padding: 3px 14px;
            border-radius: 50px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        .status.active,
        .status.completed,
        .status.terminé {
            background: var(--success-bg);
            color: var(--success-dark);
        }

        .status.pending,
        .status.en_attente {
            background: var(--warning-bg);
            color: var(--warning);
        }

        .status.cancelled,
        .status.annulé {
            background: var(--gray-100);
            color: var(--gray-500);
        }

        .status.failed,
        .status.échec {
            background: var(--danger-bg);
            color: var(--danger-dark);
        }

        /* ============================================================
           EMPTY STATE
           ============================================================ */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--gray-400);
        }

        .empty-state i {
            font-size: 36px;
            margin-bottom: 12px;
            display: block;
            color: var(--gray-300);
        }

        .empty-state strong {
            display: block;
            font-size: 16px;
            color: var(--gray-600);
            margin-bottom: 4px;
        }

        .empty-state p {
            font-size: 14px;
            color: var(--gray-400);
        }

        /* ============================================================
           RESPONSIVE - TABLETTE
           ============================================================ */
        @media (max-width: 1024px) {
            .container {
                padding: 20px 24px 40px;
            }

            .grid {
                grid-template-columns: 1fr;
            }

            .full {
                grid-column: 1;
            }
        }

        /* ============================================================
           RESPONSIVE - MOBILE
           ============================================================ */
        @media (max-width: 768px) {
            .container {
                padding: 16px;
            }

            .header {
                padding: 18px 20px;
            }

            .header h1 {
                font-size: 22px;
            }

            .profile {
                flex-direction: column;
                align-items: flex-start;
                padding: 20px;
            }

            .balance {
                text-align: left;
                font-size: 24px;
                width: 100%;
                padding-top: 12px;
                border-top: 1px solid var(--gray-200);
            }

            .balance i {
                font-size: 20px;
            }

            .card {
                padding: 18px 20px;
            }

            .card h2 {
                font-size: 16px;
            }

            .buttons {
                flex-direction: column;
            }

            button {
                width: 100%;
                justify-content: center;
            }

            table {
                font-size: 13px;
                min-width: 500px;
            }

            thead th,
            tbody td {
                padding: 10px 12px;
            }

            .back {
                font-size: 13px;
                padding: 6px 12px 6px 10px;
            }
        }

        /* ============================================================
           RESPONSIVE - PETIT MOBILE
           ============================================================ */
        @media (max-width: 480px) {
            .container {
                padding: 12px;
            }

            .header h1 {
                font-size: 20px;
            }

            .header .email {
                font-size: 13px;
            }

            .profile {
                padding: 16px;
            }

            .avatar {
                width: 44px;
                height: 44px;
                font-size: 16px;
            }

            .identity strong {
                font-size: 16px;
            }

            .balance {
                font-size: 20px;
            }

            .card {
                padding: 14px 16px;
            }

            input,
            textarea,
            select {
                padding: 10px 14px;
                font-size: 13px;
            }

            button {
                padding: 10px 16px;
                font-size: 13px;
            }

            table {
                font-size: 12px;
                min-width: 400px;
            }

            thead th,
            tbody td {
                padding: 8px 10px;
            }

            thead th {
                font-size: 10px;
            }

            .status {
                font-size: 10px;
                padding: 2px 10px;
            }
        }

        /* ============================================================
           SCROLLBAR PERSONNALISÉE
           ============================================================ */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }

        ::-webkit-scrollbar-track {
            background: var(--gray-100);
            border-radius: 3px;
        }

        ::-webkit-scrollbar-thumb {
            background: var(--gray-300);
            border-radius: 3px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--gray-400);
        }
    </style>

</head>

<body>

<div class="container">

    <a href="utilisateurs.php" class="back">
        <i class="fas fa-arrow-left"></i>
        Retour aux utilisateurs
    </a>

    <div class="header">
        <h1><i class="fas fa-user" style="color: var(--primary); margin-right: 10px;"></i><?= htmlspecialchars($user['username']) ?></h1>
        <div class="email">
            <i class="fas fa-envelope"></i>
            <?= htmlspecialchars($user['email']) ?>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert success">
            <i class="fas fa-check-circle"></i>
            <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert error">
            <i class="fas fa-exclamation-circle"></i>
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <div class="profile">
        <div class="identity">
            <div class="avatar">
                <?= strtoupper(mb_substr($user['username'], 0, 1)) ?>
            </div>
            <div>
                <strong><?= htmlspecialchars($user['username']) ?></strong>
                <div class="email">
                    <i class="fas fa-id-card"></i>
                    ID #<?= (int)$user['id'] ?>
                </div>
            </div>
        </div>
        <div class="balance">
            <i class="fas fa-wallet"></i>
            <?= number_format((float)$user['balance'], 2, ',', ' ') ?>
            <small>Solde disponible</small>
        </div>
    </div>

    <div class="grid">

        <!-- SOLDE -->
        <div class="card">
            <h2><i class="fas fa-coins"></i> Gérer le solde</h2>
            <form method="POST">
                <input type="hidden" name="action" value="balance">
                <input type="number" name="amount" step="0.01" min="0.01" placeholder="Montant" required>
                <div class="buttons">
                    <button type="submit" name="operation" value="credit" class="credit">
                        <i class="fas fa-plus"></i> Créditer
                    </button>
                    <button type="submit" name="operation" value="debit" class="debit">
                        <i class="fas fa-minus"></i> Retirer
                    </button>
                </div>
            </form>
        </div>

        <!-- NOTIFICATION -->
        <div class="card">
            <h2><i class="fas fa-bell"></i> Envoyer une notification</h2>
            <form method="POST">
                <input type="hidden" name="action" value="notification">
                <textarea name="message" placeholder="Écrire un message pour cet utilisateur..." maxlength="2000" required></textarea>
                <button type="submit" class="send">
                    <i class="fas fa-paper-plane"></i> Envoyer
                </button>
            </form>
        </div>

        <!-- INVESTISSEMENTS -->
        <div class="card full">
            <h2><i class="fas fa-chart-line"></i> Investissements</h2>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th><i class="fas fa-tag"></i> Plan</th>
                            <th><i class="fas fa-money-bill-wave"></i> Montant</th>
                            <th><i class="fas fa-arrow-up"></i> Profit</th>
                            <th><i class="fas fa-undo"></i> Retour</th>
                            <th><i class="fas fa-clock"></i> Durée</th>
                            <th><i class="fas fa-circle"></i> Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$investments): ?>
                            <tr>
                                <td colspan="6">
                                    <div class="empty-state">
                                        <i class="fas fa-chart-pie"></i>
                                        <strong>Aucun investissement</strong>
                                        <p>Cet utilisateur n'a pas encore d'investissement.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($investments as $investment): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($investment['plan_name']) ?></strong></td>
                                    <td><?= number_format((float)$investment['amount'], 2, ',', ' ') ?> <?= htmlspecialchars($investment['currency_code']) ?></td>
                                    <td><?= number_format((float)$investment['total_profit'], 2, ',', ' ') ?></td>
                                    <td><?= number_format((float)$investment['return_amount'], 2, ',', ' ') ?></td>
                                    <td><?= (int)$investment['duration_days'] ?> jours</td>
                                    <td><span class="status <?= strtolower(htmlspecialchars($investment['status'])) ?>"><?= htmlspecialchars($investment['status']) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- TRANSACTIONS -->
        <div class="card full">
            <h2><i class="fas fa-exchange-alt"></i> Transactions récentes</h2>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th><i class="fas fa-hashtag"></i> ID</th>
                            <th><i class="fas fa-tag"></i> Type</th>
                            <th><i class="fas fa-money-bill-wave"></i> Montant</th>
                            <th><i class="fas fa-circle"></i> Statut</th>
                            <th><i class="fas fa-qrcode"></i> Référence</th>
                            <th><i class="fas fa-calendar"></i> Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$transactions): ?>
                            <tr>
                                <td colspan="6">
                                    <div class="empty-state">
                                        <i class="fas fa-receipt"></i>
                                        <strong>Aucune transaction</strong>
                                        <p>Aucune transaction n'a été effectuée.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($transactions as $transaction): ?>
                                <tr>
                                    <td>#<?= (int)$transaction['id'] ?></td>
                                    <td><span class="status <?= $transaction['type'] === 'deposit' ? 'completed' : 'pending' ?>"><?= htmlspecialchars($transaction['type']) ?></span></td>
                                    <td><strong><?= number_format((float)$transaction['amount'], 2, ',', ' ') ?></strong></td>
                                    <td><span class="status <?= strtolower(htmlspecialchars($transaction['status'])) ?>"><?= htmlspecialchars($transaction['status']) ?></span></td>
                                    <td><?= htmlspecialchars($transaction['reference'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($transaction['created_at']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- NOTIFICATIONS -->
        <div class="card full">
            <h2><i class="fas fa-bell"></i> Notifications envoyées</h2>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th><i class="fas fa-envelope"></i> Message</th>
                            <th><i class="fas fa-calendar"></i> Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$notifications): ?>
                            <tr>
                                <td colspan="2">
                                    <div class="empty-state">
                                        <i class="fas fa-bell-slash"></i>
                                        <strong>Aucune notification</strong>
                                        <p>Aucune notification n'a été envoyée.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($notifications as $notification): ?>
                                <tr>
                                    <td><?= nl2br(htmlspecialchars($notification['message'] ?? $notification['content'] ?? '')) ?></td>
                                    <td><?= htmlspecialchars($notification['created_at'] ?? '') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

</div>

</body>

</html>
