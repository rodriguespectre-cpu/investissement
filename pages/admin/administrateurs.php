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
| SUPER ADMIN
|--------------------------------------------------------------------------
|
| Le compte ID 1 est ton compte super admin.
|
*/

$isSuperAdmin = ($adminId === 1);

/*
|--------------------------------------------------------------------------
| TABLE DES DEMANDES DE CRÉDIT
|--------------------------------------------------------------------------
*/

$pdo->exec("
    CREATE TABLE IF NOT EXISTS admin_credit_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        admin_id INT NOT NULL,
        user_id INT NOT NULL,
        amount DECIMAL(15,2) NOT NULL,
        type ENUM('credit','debit') NOT NULL,
        reason VARCHAR(255) DEFAULT NULL,
        status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
        processed_by INT DEFAULT NULL,
        processed_at TIMESTAMP NULL DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

        INDEX(admin_id),
        INDEX(user_id),
        INDEX(status),
        INDEX(processed_by)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

/*
|--------------------------------------------------------------------------
| CSRF
|--------------------------------------------------------------------------
*/

if (empty($_SESSION['admin_manage_csrf'])) {
    $_SESSION['admin_manage_csrf'] = bin2hex(random_bytes(32));
}

$csrf = $_SESSION['admin_manage_csrf'];

$message = '';
$error = '';

/*
|--------------------------------------------------------------------------
| VÉRIFICATION CSRF
|--------------------------------------------------------------------------
*/

function checkCsrf(): void
{
    if (
        empty($_POST['csrf_token']) ||
        !hash_equals(
            $_SESSION['admin_manage_csrf'] ?? '',
            (string) $_POST['csrf_token']
        )
    ) {
        http_response_code(419);
        exit("Session expirée. Rechargez la page.");
    }
}

/*
|--------------------------------------------------------------------------
| CRÉATION / PROMOTION ADMIN
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['action'] ?? '') === 'create_admin'
) {

    if (!$isSuperAdmin) {
        $error = "Seul le super administrateur peut créer un administrateur.";
    } else {

        checkCsrf();

        $username = trim($_POST['username'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $password = $_POST['password'] ?? '';

        if ($username === '' || $email === '' || $password === '') {

            $error = "Tous les champs sont obligatoires.";

        } elseif (!preg_match('/^[a-zA-Z0-9_.-]{3,50}$/', $username)) {

            $error = "Nom d'utilisateur invalide.";

        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

            $error = "Adresse e-mail invalide.";

        } elseif (strlen($password) < 8) {

            $error = "Le mot de passe doit contenir au moins 8 caractères.";

        } else {

            /*
            |--------------------------------------------------------------------------
            | RECHERCHE PAR USERNAME
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                SELECT id, username, email, role, is_active
                FROM users
                WHERE username = ?
                LIMIT 1
            ");

            $stmt->execute([$username]);

            $usernameUser = $stmt->fetch(PDO::FETCH_ASSOC);

            /*
            |--------------------------------------------------------------------------
            | RECHERCHE PAR EMAIL
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                SELECT id, username, email, role, is_active
                FROM users
                WHERE email = ?
                LIMIT 1
            ");

            $stmt->execute([$email]);

            $emailUser = $stmt->fetch(PDO::FETCH_ASSOC);

            /*
            |--------------------------------------------------------------------------
            | CAS 1 : USERNAME UTILISÉ PAR UN AUTRE COMPTE
            |--------------------------------------------------------------------------
            */

            if (
                $usernameUser &&
                (!$emailUser || $usernameUser['id'] != $emailUser['id'])
            ) {

                $error =
                    "Le nom d'utilisateur « " .
                    htmlspecialchars($username, ENT_QUOTES, 'UTF-8') .
                    " » est déjà utilisé.";

            /*
            |--------------------------------------------------------------------------
            | CAS 2 : EMAIL UTILISÉ PAR UN AUTRE COMPTE
            |--------------------------------------------------------------------------
            */

            } elseif (
                $emailUser &&
                (!$usernameUser || $emailUser['id'] != $usernameUser['id'])
            ) {

                $error =
                    "L'adresse e-mail « " .
                    htmlspecialchars($email, ENT_QUOTES, 'UTF-8') .
                    " » est déjà utilisée.";

            } else {

                /*
                |--------------------------------------------------------------------------
                | CAS 3 : LE COMPTE EXISTE DÉJÀ
                |--------------------------------------------------------------------------
                */

                $existingUser = $usernameUser ?: $emailUser;

                if ($existingUser) {

                    /*
                    | Si c'est déjà un admin
                    */

                    if ($existingUser['role'] === 'admin') {

                        $error =
                            "Ce compte est déjà administrateur.";

                    } else {

                        /*
                        |--------------------------------------------------------------------------
                        | PROMOTION D'UN UTILISATEUR EXISTANT
                        |--------------------------------------------------------------------------
                        */

                        $passwordHash = password_hash(
                            $password,
                            PASSWORD_DEFAULT
                        );

                        $stmt = $pdo->prepare("
                            UPDATE users
                            SET
                                role = 'admin',
                                password = ?,
                                is_active = 1,
                                updated_at = NOW()
                            WHERE id = ?
                        ");

                        $stmt->execute([
                            $passwordHash,
                            $existingUser['id']
                        ]);

                        $message =
                            "Le compte « " .
                            htmlspecialchars(
                                $existingUser['username'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) .
                            " a été promu administrateur.";
                    }

                } else {

                    /*
                    |--------------------------------------------------------------------------
                    | CAS 4 : NOUVEAU COMPTE
                    |--------------------------------------------------------------------------
                    */

                    $passwordHash = password_hash(
                        $password,
                        PASSWORD_DEFAULT
                    );

                    $stmt = $pdo->prepare("
                        INSERT INTO users (
                            username,
                            email,
                            password,
                            role,
                            balance,
                            bonus_balance,
                            is_active,
                            email_verified,
                            created_at,
                            updated_at
                        )
                        VALUES (
                            ?, ?, ?, 'admin',
                            0.00, 0.00, 1, 1,
                            NOW(), NOW()
                        )
                    ");

                    $stmt->execute([
                        $username,
                        $email,
                        $passwordHash
                    ]);

                    $message =
                        "Administrateur « " .
                        htmlspecialchars(
                            $username,
                            ENT_QUOTES,
                            'UTF-8'
                        ) .
                        " créé avec succès.";
                }
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| RÉVOQUER ADMINISTRATEUR
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['action'] ?? '') === 'revoke_admin'
) {

    if (!$isSuperAdmin) {

        $error =
            "Seul le super administrateur peut révoquer un administrateur.";

    } else {

        checkCsrf();

        $targetId = (int) ($_POST['user_id'] ?? 0);

        if ($targetId <= 0) {

            $error = "Administrateur invalide.";

        } elseif ($targetId === $adminId) {

            $error =
                "Tu ne peux pas révoquer ton propre compte super admin.";

        } else {

            $stmt = $pdo->prepare("
                SELECT id, username, role
                FROM users
                WHERE id = ?
                LIMIT 1
            ");

            $stmt->execute([$targetId]);

            $target = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$target) {

                $error = "Administrateur introuvable.";

            } elseif ($target['role'] !== 'admin') {

                $error = "Ce compte n'est pas administrateur.";

            } else {

                $stmt = $pdo->prepare("
                    UPDATE users
                    SET
                        role = 'user',
                        updated_at = NOW()
                    WHERE id = ?
                ");

                $stmt->execute([$targetId]);

                $message =
                    "Les droits administrateur de « " .
                    htmlspecialchars(
                        $target['username'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) .
                    " » ont été révoqués.";
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| APPROUVER / REFUSER DEMANDE DE CRÉDIT
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    in_array(
        $_POST['action'] ?? '',
        ['approve_credit_request', 'reject_credit_request'],
        true
    )
) {

    if (!$isSuperAdmin) {

        $error =
            "Seul le super administrateur peut traiter les demandes.";

    } else {

        checkCsrf();

        $requestId = (int) ($_POST['request_id'] ?? 0);

        if ($requestId <= 0) {

            $error = "Demande invalide.";

        } else {

            try {

                $pdo->beginTransaction();

                $stmt = $pdo->prepare("
                    SELECT *
                    FROM admin_credit_requests
                    WHERE id = ?
                    AND status = 'pending'
                    FOR UPDATE
                ");

                $stmt->execute([$requestId]);

                $request = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$request) {

                    throw new Exception(
                        "Cette demande n'est plus disponible."
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | REFUS
                |--------------------------------------------------------------------------
                */

                if (
                    $_POST['action'] ===
                    'reject_credit_request'
                ) {

                    $stmt = $pdo->prepare("
                        UPDATE admin_credit_requests
                        SET
                            status = 'rejected',
                            processed_by = ?,
                            processed_at = NOW()
                        WHERE id = ?
                    ");

                    $stmt->execute([
                        $adminId,
                        $requestId
                    ]);

                    /*
                    | Notification de l'admin
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
                        VALUES (
                            ?,
                            'warning',
                            'Demande de crédit refusée',
                            ?,
                            0,
                            'admin/administrateur.php'
                        )
                    ");

                    $stmt->execute([
                        $request['admin_id'],
                        "La demande de " .
                        number_format(
                            (float) $request['amount'],
                            2,
                            ',',
                            ' '
                        ) .
                        " a été refusée par le super administrateur."
                    ]);

                    $pdo->commit();

                    $message = "Demande refusée.";

                } else {

                    /*
                    |--------------------------------------------------------------------------
                    | APPROBATION
                    |--------------------------------------------------------------------------
                    */

                    $amount = (float) $request['amount'];

                    if ($amount <= 0) {
                        throw new Exception(
                            "Montant de demande invalide."
                        );
                    }

                    /*
                    | CREDIT
                    */

                    if ($request['type'] === 'credit') {

                        $stmt = $pdo->prepare("
                            UPDATE users
                            SET balance = balance + ?
                            WHERE id = ?
                        ");

                        $stmt->execute([
                            $amount,
                            $request['user_id']
                        ]);

                        /*
                        | Transaction
                        */

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
                                completed_at,
                                created_at,
                                updated_at
                            )
                            VALUES (
                                ?,
                                'deposit',
                                ?,
                                0,
                                'completed',
                                'admin',
                                ?,
                                ?,
                                NOW(),
                                NOW(),
                                NOW()
                            )
                        ");

                        $stmt->execute([
                            $request['user_id'],
                            $amount,
                            'ADMIN-' . $requestId,
                            'Crédit approuvé par le super administrateur.'
                        ]);

                    } else {

                        /*
                        | DÉBIT
                        */

                        $stmt = $pdo->prepare("
                            UPDATE users
                            SET balance = balance - ?
                            WHERE id = ?
                            AND balance >= ?
                        ");

                        $stmt->execute([
                            $amount,
                            $request['user_id'],
                            $amount
                        ]);

                        if ($stmt->rowCount() !== 1) {
                            throw new Exception(
                                "Solde insuffisant pour effectuer le débit."
                            );
                        }

                        /*
                        | Transaction
                        */

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
                                completed_at,
                                created_at,
                                updated_at
                            )
                            VALUES (
                                ?,
                                'withdraw',
                                ?,
                                0,
                                'completed',
                                'admin',
                                ?,
                                ?,
                                NOW(),
                                NOW(),
                                NOW()
                            )
                        ");

                        $stmt->execute([
                            $request['user_id'],
                            $amount,
                            'ADMIN-' . $requestId,
                            'Débit approuvé par le super administrateur.'
                        ]);
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | DEMANDE TERMINÉE
                    |--------------------------------------------------------------------------
                    */

                    $stmt = $pdo->prepare("
                        UPDATE admin_credit_requests
                        SET
                            status = 'approved',
                            processed_by = ?,
                            processed_at = NOW()
                        WHERE id = ?
                    ");

                    $stmt->execute([
                        $adminId,
                        $requestId
                    ]);

                    /*
                    |--------------------------------------------------------------------------
                    | NOTIFICATION UTILISATEUR
                    |--------------------------------------------------------------------------
                    */

                    $operation =
                        $request['type'] === 'credit'
                        ? 'crédité'
                        : 'débité';

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
                            'Opération approuvée',
                            ?,
                            0,
                            'dashboard/index.php'
                        )
                    ");

                    $stmt->execute([
                        $request['user_id'],
                        "Votre compte a été " .
                        $operation .
                        " de " .
                        number_format(
                            $amount,
                            2,
                            ',',
                            ' '
                        ) .
                        " par l'administration."
                    ]);

                    /*
                    |--------------------------------------------------------------------------
                    | NOTIFICATION ADMIN
                    |--------------------------------------------------------------------------
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
                        VALUES (
                            ?,
                            'success',
                            'Demande approuvée',
                            ?,
                            0,
                            'admin/administrateur.php'
                        )
                    ");

                    $stmt->execute([
                        $request['admin_id'],
                        "Votre demande a été approuvée par le super administrateur."
                    ]);

                    $pdo->commit();

                    $message = "Demande approuvée avec succès.";
                }

            } catch (Throwable $e) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $error = $e->getMessage();
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| ADMINISTRATEURS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        id,
        username,
        email,
        role,
        balance,
        bonus_balance,
        is_active,
        last_login,
        created_at
    FROM users
    WHERE role = 'admin'
    ORDER BY id DESC
");

$administrators = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| DEMANDES EN ATTENTE
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        r.*,
        a.username AS admin_username,
        a.email AS admin_email,
        u.username AS user_username,
        u.email AS user_email,
        u.balance AS user_balance
    FROM admin_credit_requests r

    INNER JOIN users a
        ON a.id = r.admin_id

    INNER JOIN users u
        ON u.id = r.user_id

    WHERE r.status = 'pending'

    ORDER BY r.created_at DESC
");

$creditRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);

?><!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><meta
name="viewport"
content="width=device-width, initial-scale=1.0"

«»

<title>InvestPro — Administrateurs</title><style>

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

.container {
    width: min(1200px, 94%);
    margin: 30px auto;
}

.header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    margin-bottom: 25px;
}

.header h1 {
    margin: 0;
    font-size: 26px;
}

.header p {
    margin: 5px 0 0;
    color: #7a8497;
}

.card {
    background: #fff;
    border-radius: 18px;
    padding: 22px;
    margin-bottom: 22px;
    box-shadow: 0 8px 30px rgba(20, 30, 50, .06);
    border: 1px solid #edf0f5;
}

.card h2 {
    margin-top: 0;
    font-size: 19px;
}

.alert {
    padding: 14px 16px;
    border-radius: 12px;
    margin-bottom: 20px;
}

.success {
    background: #e9f9ef;
    color: #18713b;
}

.error {
    background: #fff0f0;
    color: #b42318;
}

.form-grid {
    display: grid;
    grid-template-columns:
        repeat(3, 1fr);
    gap: 14px;
}

.field {
    display: flex;
    flex-direction: column;
    gap: 7px;
}

.field label {
    font-size: 13px;
    font-weight: 600;
}

.field input {
    width: 100%;
    padding: 12px 13px;
    border: 1px solid #dce1ea;
    border-radius: 10px;
    outline: none;
}

.field input:focus {
    border-color: #5b6cff;
}

button {
    border: 0;
    border-radius: 10px;
    padding: 11px 15px;
    cursor: pointer;
    font-weight: 600;
}

.btn-primary {
    background: #172033;
    color: white;
}

.btn-danger {
    background: #fff0f0;
    color: #c62828;
}

.btn-success {
    background: #e9f9ef;
    color: #18713b;
}

.table-wrap {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
}

th,
td {
    text-align: left;
    padding: 13px 10px;
    border-bottom: 1px solid #edf0f5;
    white-space: nowrap;
}

th {
    font-size: 12px;
    color: #7a8497;
    text-transform: uppercase;
}

.badge {
    display: inline-flex;
    padding: 5px 9px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 700;
}

.badge-admin {
    background: #eef0ff;
    color: #4654c9;
}

.badge-pending {
    background: #fff6df;
    color: #986c00;
}

.actions {
    display: flex;
    gap: 8px;
}

.request-type {
    font-weight: 700;
}

.credit {
    color: #159447;
}

.debit {
    color: #c62828;
}

@media (max-width: 800px) {

    .form-grid {
        grid-template-columns: 1fr;
    }

    .header {
        align-items: flex-start;
        flex-direction: column;
    }

}

</style></head><body><div class="container"><div class="header">

    <div>
        <h1>Administrateurs</h1>
        <p>
            Gestion des administrateurs et validation des opérations sensibles.
        </p>
    </div>

</div>

<?php if ($message !== ''): ?>

    <div class="alert success">
        <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
    </div>

<?php endif; ?>

<?php if ($error !== ''): ?>

    <div class="alert error">
        <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
    </div>

<?php endif; ?>


<?php if ($isSuperAdmin): ?>

<!--
========================================================================
CRÉER ADMINISTRATEUR
========================================================================
-->

<div class="card">

    <h2>Créer ou promouvoir un administrateur</h2>

    <form method="POST">

        <input
            type="hidden"
            name="csrf_token"
            value="<?= htmlspecialchars($csrf) ?>"
        >

        <input
            type="hidden"
            name="action"
            value="create_admin"
        >

        <div class="form-grid">

            <div class="field">

                <label>
                    Nom d'utilisateur
                </label>

                <input
                    type="text"
                    name="username"
                    required
                    minlength="3"
                    maxlength="50"
                    placeholder="Ex : RodrigueAdmin"
                >

            </div>

            <div class="field">

                <label>
                    Adresse e-mail
                </label>

                <input
                    type="email"
                    name="email"
                    required
                    placeholder="admin@investpro.com"
                >

            </div>

            <div class="field">

                <label>
                    Mot de passe
                </label>

                <input
                    type="password"
                    name="password"
                    required
                    minlength="8"
                    placeholder="Minimum 8 caractères"
                >

            </div>

        </div>

        <br>

        <button
            type="submit"
            class="btn-primary"
        >
            Créer / promouvoir
        </button>

    </form>

</div>

<?php endif; ?>


<!--
========================================================================
ADMINISTRATEURS
========================================================================
-->

<div class="card">

    <h2>Liste des administrateurs</h2>

    <div class="table-wrap">

        <table>

            <thead>

                <tr>

                    <th>ID</th>
                    <th>Administrateur</th>
                    <th>Email</th>
                    <th>Solde</th>
                    <th>Statut</th>
                    <th>Dernière connexion</th>
                    <th>Action</th>

                </tr>

            </thead>

            <tbody>

            <?php if (!$administrators): ?>

                <tr>
                    <td colspan="7">
                        Aucun administrateur.
                    </td>
                </tr>

            <?php else: ?>

                <?php foreach ($administrators as $admin): ?>

                    <tr>

                        <td>
                            #<?= (int) $admin['id'] ?>
                        </td>

                        <td>

                            <strong>
                                <?= htmlspecialchars(
                                    $admin['username'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </strong>

                            <br>

                            <span class="badge badge-admin">
                                ADMIN
                            </span>

                        </td>

                        <td>
                            <?= htmlspecialchars(
                                $admin['email'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </td>

                        <td>
                            <?= number_format(
                                (float) $admin['balance'],
                                2,
                                ',',
                                ' '
                            ) ?>
                        </td>

                        <td>
                            <?= (int) $admin['is_active'] === 1
                                ? 'Actif'
                                : 'Désactivé' ?>
                        </td>

                        <td>
                            <?= $admin['last_login']
                                ? htmlspecialchars(
                                    $admin['last_login'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                )
                                : 'Jamais' ?>
                        </td>

                        <td>

                            <?php if (
                                $isSuperAdmin &&
                                (int) $admin['id'] !== $adminId
                            ): ?>

                                <form method="POST">

                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= htmlspecialchars($csrf) ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="revoke_admin"
                                    >

                                    <input
                                        type="hidden"
                                        name="user_id"
                                        value="<?= (int) $admin['id'] ?>"
                                    >

                                    <button
                                        type="submit"
                                        class="btn-danger"
                                        onclick="return confirm(
                                            'Révoquer cet administrateur ?'
                                        )"
                                    >
                                        Révoquer
                                    </button>

                                </form>

                            <?php else: ?>

                                <span>
                                    Protégé
                                </span>

                            <?php endif; ?>

                        </td>

                    </tr>

                <?php endforeach; ?>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>


<?php if ($isSuperAdmin): ?>

<!--
========================================================================
DEMANDES DE CRÉDIT
========================================================================
-->

<div class="card">

    <h2>Demandes d'opérations des administrateurs</h2>

    <div class="table-wrap">

        <table>

            <thead>

                <tr>

                    <th>Admin</th>
                    <th>Utilisateur</th>
                    <th>Opération</th>
                    <th>Montant</th>
                    <th>Solde actuel</th>
                    <th>Motif</th>
                    <th>Action</th>

                </tr>

            </thead>

            <tbody>

            <?php if (!$creditRequests): ?>

                <tr>

                    <td colspan="7">
                        Aucune demande en attente.
                    </td>

                </tr>

            <?php else: ?>

                <?php foreach ($creditRequests as $request): ?>

                    <tr>

                        <td>

                            <strong>
                                <?= htmlspecialchars(
                                    $request['admin_username'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </strong>

                            <br>

                            <small>
                                <?= htmlspecialchars(
                                    $request['admin_email'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </small>

                        </td>

                        <td>

                            <?= htmlspecialchars(
                                $request['user_username'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                            <br>

                            <small>
                                <?= htmlspecialchars(
                                    $request['user_email'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </small>

                        </td>

                        <td>

                            <span class="request-type
                                <?= $request['type'] === 'credit'
                                    ? 'credit'
                                    : 'debit' ?>">

                                <?= $request['type'] === 'credit'
                                    ? 'CRÉDIT'
                                    : 'DÉBIT' ?>

                            </span>

                        </td>

                        <td>

                            <strong>
                                <?= number_format(
                                    (float) $request['amount'],
                                    2,
                                    ',',
                                    ' '
                                ) ?>
                            </strong>

                        </td>

                        <td>

                            <?= number_format(
                                (float) $request['user_balance'],
                                2,
                                ',',
                                ' '
                            ) ?>

                        </td>

                        <td>

                            <?= htmlspecialchars(
                                $request['reason'] ?? '',
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </td>

                        <td>

                            <div class="actions">

                                <form method="POST">

                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= htmlspecialchars($csrf) ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="request_id"
                                        value="<?= (int) $request['id'] ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="approve_credit_request"
                                    >

                                    <button
                                        type="submit"
                                        class="btn-success"
                                        onclick="return confirm(
                                            'Approuver cette opération ?'
                                        )"
                                    >
                                        Approuver
                                    </button>

                                </form>


                                <form method="POST">

                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= htmlspecialchars($csrf) ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="request_id"
                                        value="<?= (int) $request['id'] ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="reject_credit_request"
                                    >

                                    <button
                                        type="submit"
                                        class="btn-danger"
                                        onclick="return confirm(
                                            'Refuser cette opération ?'
                                        )"
                                    >
                                        Refuser
                                    </button>

                                </form>

                            </div>

                        </td>

                    </tr>

                <?php endforeach; ?>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>

<?php endif; ?>

</div></body></html>
