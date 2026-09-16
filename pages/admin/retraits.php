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
| VÉRIFICATION DU COMPTE ADMIN
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
| ACTION : APPROUVER / REFUSER
|--------------------------------------------------------------------------
*/

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $transactionId = (int)($_POST['transaction_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($transactionId <= 0) {

        $error = "Transaction invalide.";

    } elseif (!in_array($action, ['approve', 'reject'], true)) {

        $error = "Action invalide.";

    } else {

        try {

            $pdo->beginTransaction();

            /*
            |--------------------------------------------------------------
            | Récupérer le retrait
            |--------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                SELECT
                    t.id,
                    t.user_id,
                    t.amount,
                    t.fee,
                    t.status,
                    t.payment_method,
                    t.withdrawal_country,
                    t.withdrawal_phone,
                    t.reference
                FROM transactions t
                WHERE t.id = ?
                  AND t.type = 'withdraw'
                LIMIT 1
                FOR UPDATE
            ");

            $stmt->execute([$transactionId]);

            $withdrawal = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$withdrawal) {
                throw new Exception("Retrait introuvable.");
            }

            /*
            |--------------------------------------------------------------
            | Éviter une double validation
            |--------------------------------------------------------------
            */

            if ($withdrawal['status'] !== 'pending') {
                throw new Exception(
                    "Ce retrait a déjà été traité."
                );
            }

            /*
            |--------------------------------------------------------------
            | APPROUVER
            |--------------------------------------------------------------
            */

            if ($action === 'approve') {

                $update = $pdo->prepare("
                    UPDATE transactions
                    SET
                        status = 'completed',
                        completed_at = NOW(),
                        updated_at = NOW()
                    WHERE id = ?
                      AND status = 'pending'
                ");

                $update->execute([
                    $transactionId
                ]);

                if ($update->rowCount() !== 1) {
                    throw new Exception(
                        "Impossible d'approuver ce retrait."
                    );
                }

                $message =
                    "Le retrait #{$transactionId} a été approuvé.";

            }

            /*
            |--------------------------------------------------------------
            | REFUSER
            |--------------------------------------------------------------
            |
            | Le montant est recrédité au solde de l'utilisateur.
            |
            */

            else {

                /*
                | Récupérer le montant à recréditer
                */

                $amount = (float)$withdrawal['amount'];

                if ($amount <= 0) {
                    throw new Exception(
                        "Montant de retrait invalide."
                    );
                }

                /*
                | Recréditer le solde
                */

                $credit = $pdo->prepare("
                    UPDATE users
                    SET balance = balance + ?
                    WHERE id = ?
                ");

                $credit->execute([
                    $amount,
                    $withdrawal['user_id']
                ]);

                if ($credit->rowCount() !== 1) {
                    throw new Exception(
                        "Impossible de recréditer le compte."
                    );
                }

                /*
                | Enregistrer dans balances
                */

                $balanceLog = $pdo->prepare("
                    INSERT INTO balances
                    (
                        user_id,
                        amount,
                        type,
                        source,
                        reference_id,
                        description
                    )
                    VALUES
                    (
                        ?,
                        ?,
                        'credit',
                        'admin_adjustment',
                        ?,
                        ?
                    )
                ");

                $balanceLog->execute([
                    $withdrawal['user_id'],
                    $amount,
                    $transactionId,
                    "Remboursement du retrait refusé #{$transactionId}"
                ]);

                /*
                | Marquer le retrait comme refusé
                */

                $update = $pdo->prepare("
                    UPDATE transactions
                    SET
                        status = 'failed',
                        description = CONCAT(
                            COALESCE(description, ''),
                            ' | Retrait refusé par administration.'
                        ),
                        updated_at = NOW()
                    WHERE id = ?
                      AND status = 'pending'
                ");

                $update->execute([
                    $transactionId
                ]);

                if ($update->rowCount() !== 1) {
                    throw new Exception(
                        "Impossible de refuser ce retrait."
                    );
                }

                $message =
                    "Le retrait #{$transactionId} a été refusé et le montant recrédité.";
            }

            $pdo->commit();

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
| LISTE DES RETRAITS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        t.id,
        t.user_id,
        t.amount,
        t.fee,
        t.status,
        t.payment_method,
        t.withdrawal_country,
        t.withdrawal_phone,
        t.reference,
        t.description,
        t.created_at,

        u.username,
        u.email

    FROM transactions t

    INNER JOIN users u
        ON u.id = t.user_id

    WHERE t.type = 'withdraw'

    ORDER BY
        CASE
            WHEN t.status = 'pending' THEN 0
            ELSE 1
        END,
        t.created_at DESC
");

$withdrawals = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| FORMAT MONÉTAIRE
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

function statusLabel(string $status): string
{
    return match ($status) {
        'pending'   => 'En attente',
        'completed' => 'Approuvé',
        'failed'    => 'Refusé',
        'cancelled' => 'Annulé',
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

<title>InvestPro — Retraits</title>

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

.header {
    background: #ffffff;
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
    text-decoration: none;
    color: #4f46e5;
    font-weight: 600;
}

.container {
    max-width: 1250px;
    margin: 30px auto;
    padding: 0 18px;
}

.alert {
    padding: 14px 16px;
    border-radius: 12px;
    margin-bottom: 20px;
    font-weight: 600;
}

.success {
    background: #eafaf1;
    color: #147a45;
}

.error {
    background: #fff0f0;
    color: #b42318;
}

.card {
    background: #fff;
    border: 1px solid #e7eaf0;
    border-radius: 18px;
    overflow: hidden;
    box-shadow: 0 8px 30px rgba(20, 30, 55, .05);
}

.table-wrapper {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 1050px;
}

th {
    background: #f8f9fc;
    text-align: left;
    padding: 15px;
    font-size: 12px;
    text-transform: uppercase;
    color: #687386;
}

td {
    padding: 16px 15px;
    border-top: 1px solid #eef0f4;
    vertical-align: middle;
}

.user {
    font-weight: 700;
}

.email {
    color: #7a8495;
    font-size: 12px;
    margin-top: 3px;
}

.amount {
    font-weight: 800;
}

.info {
    line-height: 1.5;
}

.badge {
    display: inline-block;
    padding: 6px 10px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 700;
}

.pending {
    background: #fff4d6;
    color: #9a6700;
}

.completed {
    background: #e8f8ef;
    color: #147a45;
}

.failed,
.cancelled {
    background: #ffe9e9;
    color: #b42318;
}

.actions {
    display: flex;
    gap: 8px;
}

button {
    border: 0;
    border-radius: 9px;
    padding: 9px 13px;
    cursor: pointer;
    font-weight: 700;
}

.approve {
    background: #16a34a;
    color: white;
}

.reject {
    background: #dc2626;
    color: white;
}

.empty {
    text-align: center;
    padding: 60px 20px;
    color: #7a8495;
}

@media (max-width: 600px) {

    .header {
        padding: 16px;
    }

    .container {
        margin-top: 20px;
        padding: 0 10px;
    }

}

</style>

</head>

<body>

<header class="header">

    <h1>Retraits</h1>

    <a
        class="back"
        href="index.php"
    >
        ← Tableau de bord
    </a>

</header>

<main class="container">

<?php if ($message): ?>

    <div class="alert success">
        <?= htmlspecialchars($message) ?>
    </div>

<?php endif; ?>

<?php if ($error): ?>

    <div class="alert error">
        <?= htmlspecialchars($error) ?>
    </div>

<?php endif; ?>

<div class="card">

<?php if (!$withdrawals): ?>

    <div class="empty">
        Aucun retrait enregistré.
    </div>

<?php else: ?>

<div class="table-wrapper">

<table>

<thead>

<tr>

<th>Utilisateur</th>

<th>Montant</th>

<th>Pays</th>

<th>Moyen de paiement</th>

<th>Numéro</th>

<th>Référence</th>

<th>Statut</th>

<th>Date</th>

<th>Action</th>

</tr>

</thead>

<tbody>

<?php foreach ($withdrawals as $withdrawal): ?>

<tr>

<td>

    <div class="user">
        <?= htmlspecialchars($withdrawal['username']) ?>
    </div>

    <div class="email">
        <?= htmlspecialchars($withdrawal['email']) ?>
    </div>

</td>

<td>

    <div class="amount">
        <?= money($withdrawal['amount']) ?>
    </div>

    <?php if ((float)$withdrawal['fee'] > 0): ?>

        <small>
            Frais :
            <?= money($withdrawal['fee']) ?>
        </small>

    <?php endif; ?>

</td>

<td>

    <div class="info">

        <?= htmlspecialchars(
            $withdrawal['withdrawal_country'] ?: '—'
        ) ?>

    </div>

</td>

<td>

    <div class="info">

        <?= htmlspecialchars(
            $withdrawal['payment_method'] ?: '—'
        ) ?>

    </div>

</td>

<td>

    <strong>
        <?= htmlspecialchars(
            $withdrawal['withdrawal_phone'] ?: '—'
        ) ?>
    </strong>

</td>

<td>

    <?= htmlspecialchars(
        $withdrawal['reference'] ?: '—'
    ) ?>

</td>

<td>

    <span class="badge <?= htmlspecialchars($withdrawal['status']) ?>">

        <?= htmlspecialchars(
            statusLabel($withdrawal['status'])
        ) ?>

    </span>

</td>

<td>

    <?= htmlspecialchars(
        date(
            'd/m/Y H:i',
            strtotime($withdrawal['created_at'])
        )
    ) ?>

</td>

<td>

<?php if ($withdrawal['status'] === 'pending'): ?>

<div class="actions">

    <form method="POST">

        <input
            type="hidden"
            name="transaction_id"
            value="<?= (int)$withdrawal['id'] ?>"
        >

        <input
            type="hidden"
            name="action"
            value="approve"
        >

        <button
            class="approve"
            type="submit"
            onclick="return confirm('Approuver ce retrait ?');"
        >
            ✓ Approuver
        </button>

    </form>

    <form method="POST">

        <input
            type="hidden"
            name="transaction_id"
            value="<?= (int)$withdrawal['id'] ?>"
        >

        <input
            type="hidden"
            name="action"
            value="reject"
        >

        <button
            class="reject"
            type="submit"
            onclick="return confirm('Refuser ce retrait et recréditer le compte ?');"
        >
            ✕ Refuser
        </button>

    </form>

</div>

<?php else: ?>

    <span style="color:#8993a3;">
        Traité
    </span>

<?php endif; ?>

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
