<?php

session_start();

require_once "../../config/database.php";

/*
|--------------------------------------------------------------------------
| AUTHENTIFICATION ADMIN
|--------------------------------------------------------------------------
*/

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
| VÉRIFICATION ADMIN
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        username,
        email,
        role,
        is_active
    FROM users
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([$adminId]);

$admin = $stmt->fetch(PDO::FETCH_ASSOC);

if (
    !$admin ||
    (int) $admin['is_active'] !== 1 ||
    !in_array(
        $admin['role'],
        ['admin', 'super_admin'],
        true
    )
) {
    session_unset();
    session_destroy();

    header("Location: login.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| CSRF
|--------------------------------------------------------------------------
*/

if (empty($_SESSION['admin_notification_csrf'])) {
    $_SESSION['admin_notification_csrf'] =
        bin2hex(random_bytes(32));
}

$csrfToken =
    $_SESSION['admin_notification_csrf'];

$success = '';
$error = '';

/*
|--------------------------------------------------------------------------
| FONCTION NOTIFICATION
|--------------------------------------------------------------------------
*/

function createNotification(
    PDO $pdo,
    int $userId,
    string $type,
    string $title,
    string $message,
    ?string $link = null
): void {

    $stmt = $pdo->prepare("
        INSERT INTO notifications (
            user_id,
            type,
            title,
            message,
            is_read,
            link
        )
        VALUES (?, ?, ?, ?, 0, ?)
    ");

    $stmt->execute([
        $userId,
        $type,
        $title,
        $message,
        $link
    ]);
}

/*
|--------------------------------------------------------------------------
| TRAITEMENT POST
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (
        empty($_POST['csrf_token']) ||
        !hash_equals(
            $_SESSION['admin_notification_csrf'] ?? '',
            (string) $_POST['csrf_token']
        )
    ) {

        $error =
            "Session expirée. Actualisez la page.";

    } else {

        $action =
            $_POST['action'] ?? '';

        /*
        |--------------------------------------------------------------------------
        | ENVOYER À UN UTILISATEUR
        |--------------------------------------------------------------------------
        */

        if ($action === 'send_user') {

            $userId =
                (int) ($_POST['user_id'] ?? 0);

            $type =
                $_POST['type'] ?? 'info';

            $title =
                trim($_POST['title'] ?? '');

            $message =
                trim($_POST['message'] ?? '');

            if (
                !in_array(
                    $type,
                    [
                        'info',
                        'success',
                        'warning',
                        'error'
                    ],
                    true
                )
            ) {
                $type = 'info';
            }

            if ($userId <= 0) {

                $error =
                    "Veuillez sélectionner un utilisateur.";

            } elseif ($title === '') {

                $error =
                    "Le titre est obligatoire.";

            } elseif ($message === '') {

                $error =
                    "Le message est obligatoire.";

            } else {

                $stmt = $pdo->prepare("
                    SELECT id
                    FROM users
                    WHERE id = ?
                    LIMIT 1
                ");

                $stmt->execute([$userId]);

                if (!$stmt->fetch()) {

                    $error =
                        "Utilisateur introuvable.";

                } else {

                    createNotification(
                        $pdo,
                        $userId,
                        $type,
                        $title,
                        $message,
                        null
                    );

                    $success =
                        "Notification envoyée à l'utilisateur.";
                }
            }
        }

        /*
        |--------------------------------------------------------------------------
        | ENVOYER À TOUS
        |--------------------------------------------------------------------------
        */

        elseif ($action === 'send_all') {

            $type =
                $_POST['type'] ?? 'info';

            $title =
                trim($_POST['title'] ?? '');

            $message =
                trim($_POST['message'] ?? '');

            if (
                !in_array(
                    $type,
                    [
                        'info',
                        'success',
                        'warning',
                        'error'
                    ],
                    true
                )
            ) {
                $type = 'info';
            }

            if ($title === '') {

                $error =
                    "Le titre est obligatoire.";

            } elseif ($message === '') {

                $error =
                    "Le message est obligatoire.";

            } else {

                /*
                |--------------------------------------------------------------------------
                | UTILISATEURS ACTIFS
                |--------------------------------------------------------------------------
                */

                $usersStmt = $pdo->query("
                    SELECT id
                    FROM users
                    WHERE is_active = 1
                    AND role = 'user'
                ");

                $users =
                    $usersStmt->fetchAll(
                        PDO::FETCH_COLUMN
                    );

                if (empty($users)) {

                    $error =
                        "Aucun utilisateur actif.";

                } else {

                    $pdo->beginTransaction();

                    try {

                        $stmt = $pdo->prepare("
                            INSERT INTO notifications (
                                user_id,
                                type,
                                title,
                                message,
                                is_read,
                                link
                            )
                            VALUES (?, ?, ?, ?, 0, NULL)
                        ");

                        foreach ($users as $userId) {

                            $stmt->execute([
                                (int) $userId,
                                $type,
                                $title,
                                $message
                            ]);
                        }

                        $pdo->commit();

                        $success =
                            "Notification envoyée à "
                            . count($users)
                            . " utilisateur(s).";

                    } catch (Throwable $e) {

                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }

                        $error =
                            "Impossible d'envoyer les notifications.";
                    }
                }
            }
        }

        /*
        |--------------------------------------------------------------------------
        | MARQUER UNE NOTIFICATION COMME LUE
        |--------------------------------------------------------------------------
        */

        elseif ($action === 'read') {

            $notificationId =
                (int) ($_POST['notification_id'] ?? 0);

            if ($notificationId > 0) {

                /*
                | L'admin reçoit ses propres notifications
                | avec user_id = admin_id.
                */

                $stmt = $pdo->prepare("
                    UPDATE notifications
                    SET is_read = 1
                    WHERE id = ?
                    AND user_id = ?
                    LIMIT 1
                ");

                $stmt->execute([
                    $notificationId,
                    $adminId
                ]);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | SUPPRIMER UNE NOTIFICATION ADMIN
        |--------------------------------------------------------------------------
        */

        elseif ($action === 'delete') {

            $notificationId =
                (int) ($_POST['notification_id'] ?? 0);

            if ($notificationId > 0) {

                $stmt = $pdo->prepare("
                    DELETE FROM notifications
                    WHERE id = ?
                    AND user_id = ?
                    LIMIT 1
                ");

                $stmt->execute([
                    $notificationId,
                    $adminId
                ]);
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| UTILISATEURS POUR LE SELECT
|--------------------------------------------------------------------------
*/

$usersStmt = $pdo->query("
    SELECT
        id,
        username,
        email
    FROM users
    WHERE role = 'user'
    ORDER BY username ASC
");

$users =
    $usersStmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| NOTIFICATIONS REÇUES PAR L'ADMIN
|--------------------------------------------------------------------------
*/

$receivedStmt = $pdo->prepare("
    SELECT
        n.id,
        n.type,
        n.title,
        n.message,
        n.is_read,
        n.link,
        n.created_at

    FROM notifications n

    WHERE n.user_id = ?

    ORDER BY
        n.created_at DESC,
        n.id DESC

    LIMIT 100
");

$receivedStmt->execute([$adminId]);

$received =
    $receivedStmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| COMPTEUR NON LUES
|--------------------------------------------------------------------------
*/

$unreadStmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM notifications
    WHERE user_id = ?
    AND is_read = 0
");

$unreadStmt->execute([$adminId]);

$unreadCount =
    (int) $unreadStmt->fetchColumn();

/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/

function e(?string $value): string
{
    return htmlspecialchars(
        $value ?? '',
        ENT_QUOTES,
        'UTF-8'
    );
}

function typeIcon(string $type): string
{
    return match ($type) {
        'success' => '✓',
        'warning' => '!',
        'error'   => '×',
        default   => 'i'
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

<title>InvestPro — Centre des notifications</title>

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

    background: #f5f7fb;
    color: #172033;
}

button,
input,
textarea,
select {
    font: inherit;
}

button {
    cursor: pointer;
}

.topbar {
    height: 68px;

    background: #fff;

    border-bottom:
        1px solid #e7eaf0;

    display: flex;
    align-items: center;
    justify-content: space-between;

    padding: 0 24px;

    position: sticky;
    top: 0;
    z-index: 50;
}

.logo {
    font-size: 21px;
    font-weight: 800;
}

.logo span {
    color: #5b5cf0;
}

.admin {
    display: flex;
    align-items: center;
    gap: 10px;
}

.avatar {
    width: 38px;
    height: 38px;

    border-radius: 50%;

    background: #5b5cf0;
    color: #fff;

    display: flex;
    align-items: center;
    justify-content: center;

    font-weight: 800;
}

.container {
    max-width: 1250px;
    margin: auto;

    padding: 28px 20px 60px;
}

.header {
    margin-bottom: 22px;
}

.header h1 {
    font-size: 27px;
    margin-bottom: 5px;
}

.header p {
    color: #7c8494;
    font-size: 14px;
}

.alert {
    padding: 13px 15px;
    border-radius: 10px;
    margin-bottom: 18px;
    font-size: 14px;
}

.alert.success {
    background: #eafaf1;
    color: #18794e;
}

.alert.error {
    background: #fff0f0;
    color: #b42318;
}

.layout {
    display: grid;

    grid-template-columns:
        minmax(0, 1fr)
        360px;

    gap: 20px;

    align-items: start;
}

.card {
    background: #fff;

    border:
        1px solid #e6e9ef;

    border-radius: 15px;

    padding: 20px;
}

.card-title {
    font-size: 17px;
    font-weight: 800;
    margin-bottom: 4px;
}

.card-description {
    font-size: 12px;
    color: #858c9b;
    margin-bottom: 18px;
}

/*
|--------------------------------------------------------------------------
| FORM
|--------------------------------------------------------------------------
*/

.field {
    margin-bottom: 14px;
}

.field label {
    display: block;

    font-size: 12px;
    font-weight: 700;

    margin-bottom: 7px;
}

.field input,
.field select,
.field textarea {
    width: 100%;

    border:
        1px solid #dfe3eb;

    border-radius: 9px;

    padding: 11px 12px;

    outline: none;

    background: #fff;
}

.field textarea {
    min-height: 115px;
    resize: vertical;
}

.field input:focus,
.field select:focus,
.field textarea:focus {
    border-color: #5b5cf0;
}

.send-button {
    width: 100%;

    height: 44px;

    border: none;

    border-radius: 9px;

    background: #5b5cf0;
    color: #fff;

    font-weight: 800;
}

.send-button:hover {
    opacity: .92;
}

/*
|--------------------------------------------------------------------------
| SWITCH ENVOI
|--------------------------------------------------------------------------
*/

.send-tabs {
    display: flex;

    background: #f1f3f7;

    padding: 4px;

    border-radius: 9px;

    margin-bottom: 17px;
}

.send-tab {
    flex: 1;

    border: none;

    background: transparent;

    padding: 9px;

    border-radius: 7px;

    font-size: 12px;

    font-weight: 700;

    color: #727a8b;
}

.send-tab.active {
    background: #fff;
    color: #4e50d8;

    box-shadow:
        0 1px 3px rgba(0,0,0,.06);
}

.mode {
    display: none;
}

.mode.active {
    display: block;
}

/*
|--------------------------------------------------------------------------
| NOTIFICATIONS
|--------------------------------------------------------------------------
*/

.notification {
    display: grid;

    grid-template-columns: 40px 1fr auto;

    gap: 12px;

    padding: 15px 0;

    border-bottom:
        1px solid #edf0f4;
}

.notification:last-child {
    border-bottom: none;
}

.notification.unread {
    background: #fafaff;
    margin: 0 -10px;
    padding-left: 10px;
    padding-right: 10px;

    border-radius: 10px;
}

.notification-icon {
    width: 38px;
    height: 38px;

    border-radius: 50%;

    display: flex;
    align-items: center;
    justify-content: center;

    font-weight: 800;
}

.notification-icon.info {
    background: #edf3ff;
    color: #356ae6;
}

.notification-icon.success {
    background: #e9f9f0;
    color: #16834f;
}

.notification-icon.warning {
    background: #fff6df;
    color: #a86b00;
}

.notification-icon.error {
    background: #ffeded;
    color: #c52929;
}

.notification-title {
    font-size: 14px;
    font-weight: 800;
}

.notification-message {
    margin-top: 5px;

    font-size: 13px;

    line-height: 1.5;

    color: #697284;

    white-space: pre-wrap;
}

.notification-date {
    margin-top: 6px;

    color: #9aa1ae;

    font-size: 10px;
}

.notification-action {
    border: 1px solid #e1e4ea;

    background: #fff;

    width: 32px;
    height: 32px;

    border-radius: 8px;

    font-weight: 800;
}

.notification-action.delete {
    color: #c53030;
}

.empty {
    text-align: center;

    padding: 35px 15px;

    color: #8b92a1;

    font-size: 13px;
}

/*
|--------------------------------------------------------------------------
| BADGE
|--------------------------------------------------------------------------
*/

.badge {
    display: inline-block;

    margin-left: 5px;

    padding: 3px 7px;

    border-radius: 20px;

    font-size: 9px;

    font-weight: 800;

    background: #ececff;
    color: #5052db;
}

/*
|--------------------------------------------------------------------------
| RESPONSIVE
|--------------------------------------------------------------------------
*/

@media (max-width: 850px) {

    .layout {
        grid-template-columns: 1fr;
    }

}

@media (max-width: 600px) {

    .topbar {
        padding: 0 15px;
    }

    .admin-name {
        display: none;
    }

    .container {
        padding: 20px 13px 40px;
    }

    .card {
        padding: 16px;
    }

    .notification {
        grid-template-columns: 36px 1fr;
    }

    .notification-action {
        grid-column: 2;
    }

}

</style>

</head>

<body>

<header class="topbar">

    <a
        href="index.php"
        class="logo"
    >
        Invest<span>Pro</span>
    </a>

    <div class="admin">

        <div class="admin-name">
            <?= e($admin['username']) ?>
        </div>

        <div class="avatar">
            <?= strtoupper(
                mb_substr(
                    $admin['username'],
                    0,
                    1
                )
            ) ?>
        </div>

    </div>

</header>

<main class="container">

    <div class="header">

        <h1>
            Centre des notifications
        </h1>

        <p>
            Communication avec les utilisateurs
            et alertes importantes de la plateforme.
        </p>

    </div>

    <?php if ($success !== ''): ?>

        <div class="alert success">
            <?= e($success) ?>
        </div>

    <?php endif; ?>

    <?php if ($error !== ''): ?>

        <div class="alert error">
            <?= e($error) ?>
        </div>

    <?php endif; ?>

    <div class="layout">

        <!-- ==========================================================
             NOTIFICATIONS REÇUES PAR L'ADMIN
             ========================================================== -->

        <section class="card">

            <div class="card-title">
                Mes alertes

                <?php if ($unreadCount > 0): ?>

                    <span class="badge">
                        <?= $unreadCount ?> non lue(s)
                    </span>

                <?php endif; ?>

            </div>

            <div class="card-description">
                Alertes générées par la plateforme
                nécessitant éventuellement votre intervention.
            </div>

            <?php if (empty($received)): ?>

                <div class="empty">
                    🔔<br><br>
                    Aucune notification pour le moment.
                </div>

            <?php else: ?>

                <?php foreach ($received as $notification): ?>

                    <article
                        class="
                            notification
                            <?= (int) $notification['is_read'] === 0
                                ? 'unread'
                                : ''
                            ?>
                        "
                    >

                        <div
                            class="
                                notification-icon
                                <?= e($notification['type']) ?>
                            "
                        >
                            <?= e(
                                typeIcon(
                                    $notification['type']
                                )
                            ) ?>
                        </div>

                        <div>

                            <div class="notification-title">

                                <?= e(
                                    $notification['title']
                                ) ?>

                                <?php if (
                                    (int)
                                    $notification['is_read'] === 0
                                ): ?>

                                    <span class="badge">
                                        Nouveau
                                    </span>

                                <?php endif; ?>

                            </div>

                            <div class="notification-message">

                                <?= e(
                                    $notification['message']
                                ) ?>

                            </div>

                            <div class="notification-date">

                                <?= e(
                                    $notification['created_at']
                                ) ?>

                            </div>

                        </div>

                        <div>

                            <?php if (
                                (int)
                                $notification['is_read'] === 0
                            ): ?>

                                <form method="POST">

                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= e($csrfToken) ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="read"
                                    >

                                    <input
                                        type="hidden"
                                        name="notification_id"
                                        value="<?= (int)
                                            $notification['id']
                                        ?>"
                                    >

                                    <button
                                        class="notification-action"
                                        title="Marquer comme lue"
                                    >
                                        ✓
                                    </button>

                                </form>

                            <?php else: ?>

                                <form
                                    method="POST"
                                    onsubmit="
                                        return confirm(
                                            'Supprimer cette notification ?'
                                        );
                                    "
                                >

                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= e($csrfToken) ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="delete"
                                    >

                                    <input
                                        type="hidden"
                                        name="notification_id"
                                        value="<?= (int)
                                            $notification['id']
                                        ?>"
                                    >

                                    <button
                                        class="
                                            notification-action
                                            delete
                                        "
                                    >
                                        ×
                                    </button>

                                </form>

                            <?php endif; ?>

                        </div>

                    </article>

                <?php endforeach; ?>

            <?php endif; ?>

        </section>

        <!-- ==========================================================
             ENVOI
             ========================================================== -->

        <aside class="card">

            <div class="card-title">
                Nouvelle notification
            </div>

            <div class="card-description">
                Envoyer une information à un utilisateur
                ou à toute la communauté.
            </div>

            <div class="send-tabs">

                <button
                    type="button"
                    class="send-tab active"
                    data-mode="user"
                >
                    Un utilisateur
                </button>

                <button
                    type="button"
                    class="send-tab"
                    data-mode="all"
                >
                    Tous
                </button>

            </div>

            <!-- ======================================================
                 UTILISATEUR
                 ====================================================== -->

            <div
                class="mode active"
                id="mode-user"
            >

                <form method="POST">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= e($csrfToken) ?>"
                    >

                    <input
                        type="hidden"
                        name="action"
                        value="send_user"
                    >

                    <div class="field">

                        <label>
                            UTILISATEUR
                        </label>

                        <select
                            name="user_id"
                            required
                        >

                            <option value="">
                                Sélectionner...
                            </option>

                            <?php foreach ($users as $user): ?>

                                <option
                                    value="<?= (int) $user['id'] ?>"
                                >
                                    <?= e($user['username']) ?>
                                    —
                                    <?= e($user['email']) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                    <div class="field">

                        <label>
                            TYPE
                        </label>

                        <select name="type">

                            <option value="info">
                                Information
                            </option>

                            <option value="success">
                                Succès
                            </option>

                            <option value="warning">
                                Avertissement
                            </option>

                            <option value="error">
                                Important
                            </option>

                        </select>

                    </div>

                    <div class="field">

                        <label>
                            TITRE
                        </label>

                        <input
                            type="text"
                            name="title"
                            maxlength="255"
                            placeholder="Titre..."
                            required
                        >

                    </div>

                    <div class="field">

                        <label>
                            MESSAGE
                        </label>

                        <textarea
                            name="message"
                            maxlength="5000"
                            placeholder="Écrire votre message..."
                            required
                        ></textarea>

                    </div>

                    <button
                        type="submit"
                        class="send-button"
                    >
                        Envoyer la notification
                    </button>

                </form>

            </div>

            <!-- ======================================================
                 TOUS LES UTILISATEURS
                 ====================================================== -->

            <div
                class="mode"
                id="mode-all"
            >

                <form
                    method="POST"
                    onsubmit="
                        return confirm(
                            'Cette notification sera envoyée à tous les utilisateurs actifs. Continuer ?'
                        );
                    "
                >

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= e($csrfToken) ?>"
                    >

                    <input
                        type="hidden"
                        name="action"
                        value="send_all"
                    >

                    <div class="field">

                        <label>
                            TYPE
                        </label>

                        <select name="type">

                            <option value="info">
                                Information
                            </option>

                            <option value="success">
                                Succès
                            </option>

                            <option value="warning">
                                Avertissement
                            </option>

                            <option value="error">
                                Important
                            </option>

                        </select>

                    </div>

                    <div class="field">

                        <label>
                            TITRE
                        </label>

                        <input
                            type="text"
                            name="title"
                            maxlength="255"
                            placeholder="Annonce..."
                            required
                        >

                    </div>

                    <div class="field">

                        <label>
                            MESSAGE
                        </label>

                        <textarea
                            name="message"
                            maxlength="5000"
                            placeholder="Message destiné à tous..."
                            required
                        ></textarea>

                    </div>

                    <button
                        type="submit"
                        class="send-button"
                    >
                        Envoyer à tous
                    </button>

                </form>

            </div>

        </aside>

    </div>

</main>

<script>

const tabs =
    document.querySelectorAll('.send-tab');

const modes =
    document.querySelectorAll('.mode');

tabs.forEach(tab => {

    tab.addEventListener('click', () => {

        tabs.forEach(item => {
            item.classList.remove('active');
        });

        modes.forEach(mode => {
            mode.classList.remove('active');
        });

        tab.classList.add('active');

        const mode =
            document.getElementById(
                'mode-' + tab.dataset.mode
            );

        if (mode) {
            mode.classList.add('active');
        }

    });

});

</script>

</body>

</html>
