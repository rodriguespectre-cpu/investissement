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
    !in_array($_SESSION['admin_role'], ['admin', 'super_admin'], true)
) {
    header("Location: login.php");
    exit;
}

$adminId = (int) $_SESSION['admin_id'];

/*
|--------------------------------------------------------------------------
| CSRF
|--------------------------------------------------------------------------
*/

if (empty($_SESSION['admin_support_csrf'])) {
    $_SESSION['admin_support_csrf'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['admin_support_csrf'];

/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/

function redirectSupport(string $url = 'support.php'): never
{
    header("Location: " . $url);
    exit;
}

function e(?string $value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}

function verifyCsrf(): void
{
    if (
        empty($_POST['csrf_token']) ||
        !hash_equals(
            $_SESSION['admin_support_csrf'] ?? '',
            (string) $_POST['csrf_token']
        )
    ) {
        http_response_code(419);
        exit("Session expirée. Veuillez actualiser la page.");
    }
}

/*
|--------------------------------------------------------------------------
| ACTIONS
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verifyCsrf();

    $action = $_POST['action'] ?? '';

    /*
    |--------------------------------------------------------------------------
    | RÉPONDRE À UN TICKET
    |--------------------------------------------------------------------------
    */

    if ($action === 'reply') {

        $ticketId = (int) ($_POST['ticket_id'] ?? 0);
        $message = trim($_POST['message'] ?? '');

        if ($ticketId <= 0 || $message === '') {
            exit("Message invalide.");
        }

        if (mb_strlen($message) > 10000) {
            exit("Le message est trop long.");
        }

        /*
        | Récupérer le ticket + utilisateur
        */

        $stmt = $pdo->prepare("
            SELECT
                st.id,
                st.user_id,
                st.subject,
                st.status,
                u.username,
                u.email
            FROM support_tickets st
            INNER JOIN users u
                ON u.id = st.user_id
            WHERE st.id = ?
            LIMIT 1
        ");

        $stmt->execute([$ticketId]);

        $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$ticket) {
            exit("Ticket introuvable.");
        }

        /*
        |--------------------------------------------------------------------------
        | TRANSACTION
        |--------------------------------------------------------------------------
        */

        try {

            $pdo->beginTransaction();

            /*
            | Enregistrer la réponse admin
            */

            $reply = $pdo->prepare("
                INSERT INTO support_replies
                (
                    ticket_id,
                    user_id,
                    message,
                    is_admin
                )
                VALUES
                (
                    ?,
                    ?,
                    ?,
                    1
                )
            ");

            $reply->execute([
                $ticketId,
                $ticket['user_id'],
                $message
            ]);

            /*
            |--------------------------------------------------------------------------
            | Mettre le ticket en "in_progress"
            |--------------------------------------------------------------------------
            */

            $updateTicket = $pdo->prepare("
                UPDATE support_tickets
                SET
                    status = 'in_progress',
                    assigned_to = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");

            $updateTicket->execute([
                $adminId,
                $ticketId
            ]);

            /*
            |--------------------------------------------------------------------------
            | NOTIFICATION UTILISATEUR
            |--------------------------------------------------------------------------
            */

            $notificationTitle = "Réponse du support";

            $notificationMessage =
                "Le support a répondu à votre demande « " .
                $ticket['subject'] .
                " ».";

            /*
            | Lien vers le support utilisateur.
            |
            | Adapte cette URL si ta page utilisateur
            | porte un autre nom.
            */

            $notificationLink =
                "support.php?ticket=" . $ticketId;

            $notification = $pdo->prepare("
                INSERT INTO notifications
                (
                    user_id,
                    type,
                    title,
                    message,
                    is_read,
                    link
                )
                VALUES
                (
                    ?,
                    'info',
                    ?,
                    ?,
                    0,
                    ?
                )
            ");

            $notification->execute([
                $ticket['user_id'],
                $notificationTitle,
                $notificationMessage,
                $notificationLink
            ]);

            $pdo->commit();

            redirectSupport(
                "support.php?ticket=" . $ticketId .
                "&success=reply"
            );

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            http_response_code(500);

            exit(
                "Impossible d'envoyer la réponse : " .
                e($e->getMessage())
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | MODIFIER LE STATUT
    |--------------------------------------------------------------------------
    */

    if ($action === 'status') {

        $ticketId = (int) ($_POST['ticket_id'] ?? 0);
        $status = $_POST['status'] ?? '';

        $allowedStatuses = [
            'open',
            'in_progress',
            'resolved',
            'closed'
        ];

        if (
            $ticketId <= 0 ||
            !in_array($status, $allowedStatuses, true)
        ) {
            exit("Statut invalide.");
        }

        $stmt = $pdo->prepare("
            UPDATE support_tickets
            SET
                status = ?,
                assigned_to = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");

        $stmt->execute([
            $status,
            $adminId,
            $ticketId
        ]);

        redirectSupport(
            "support.php?ticket=" . $ticketId .
            "&success=status"
        );
    }

    /*
    |--------------------------------------------------------------------------
    | MODIFIER LA PRIORITÉ
    |--------------------------------------------------------------------------
    */

    if ($action === 'priority') {

        $ticketId = (int) ($_POST['ticket_id'] ?? 0);
        $priority = $_POST['priority'] ?? '';

        $allowedPriorities = [
            'low',
            'medium',
            'high'
        ];

        if (
            $ticketId <= 0 ||
            !in_array($priority, $allowedPriorities, true)
        ) {
            exit("Priorité invalide.");
        }

        $stmt = $pdo->prepare("
            UPDATE support_tickets
            SET
                priority = ?,
                assigned_to = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");

        $stmt->execute([
            $priority,
            $adminId,
            $ticketId
        ]);

        redirectSupport(
            "support.php?ticket=" . $ticketId .
            "&success=priority"
        );
    }
}

/*
|--------------------------------------------------------------------------
| TICKET SÉLECTIONNÉ
|--------------------------------------------------------------------------
*/

$selectedTicketId = (int) ($_GET['ticket'] ?? 0);

$selectedTicket = null;
$replies = [];

/*
|--------------------------------------------------------------------------
| RÉCUPÉRER LE TICKET
|--------------------------------------------------------------------------
*/

if ($selectedTicketId > 0) {

    $stmt = $pdo->prepare("
        SELECT
            st.id,
            st.user_id,
            st.subject,
            st.message,
            st.status,
            st.priority,
            st.assigned_to,
            st.created_at,
            st.updated_at,

            u.username,
            u.email,
            u.phone,
            u.country_code,
            u.balance,
            u.bonus_balance

        FROM support_tickets st

        INNER JOIN users u
            ON u.id = st.user_id

        WHERE st.id = ?

        LIMIT 1
    ");

    $stmt->execute([
        $selectedTicketId
    ]);

    $selectedTicket = $stmt->fetch(PDO::FETCH_ASSOC);

    /*
    |--------------------------------------------------------------------------
    | RÉPONSES
    |--------------------------------------------------------------------------
    */

    if ($selectedTicket) {

        $stmt = $pdo->prepare("
            SELECT
                sr.id,
                sr.ticket_id,
                sr.user_id,
                sr.message,
                sr.is_admin,
                sr.created_at,

                u.username,
                u.email

            FROM support_replies sr

            INNER JOIN users u
                ON u.id = sr.user_id

            WHERE sr.ticket_id = ?

            ORDER BY sr.created_at ASC, sr.id ASC
        ");

        $stmt->execute([
            $selectedTicketId
        ]);

        $replies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

/*
|--------------------------------------------------------------------------
| LISTE DES TICKETS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        st.id,
        st.user_id,
        st.subject,
        st.message,
        st.status,
        st.priority,
        st.assigned_to,
        st.created_at,
        st.updated_at,

        u.username,
        u.email

    FROM support_tickets st

    INNER JOIN users u
        ON u.id = st.user_id

    ORDER BY
        CASE st.status
            WHEN 'open' THEN 1
            WHEN 'in_progress' THEN 2
            WHEN 'resolved' THEN 3
            WHEN 'closed' THEN 4
            ELSE 5
        END,

        CASE st.priority
            WHEN 'high' THEN 1
            WHEN 'medium' THEN 2
            WHEN 'low' THEN 3
            ELSE 4
        END,

        st.updated_at DESC
");

$tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| COMPTEURS
|--------------------------------------------------------------------------
*/

$stats = [
    'open' => 0,
    'in_progress' => 0,
    'resolved' => 0,
    'closed' => 0
];

foreach ($tickets as $ticket) {

    if (isset($stats[$ticket['status']])) {
        $stats[$ticket['status']]++;
    }
}

$success = $_GET['success'] ?? '';

?>
<!DOCTYPE html>
<html lang="fr">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>InvestPro — Support</title>

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

a {
    text-decoration: none;
    color: inherit;
}

button,
textarea,
select {
    font: inherit;
}

/*
|--------------------------------------------------------------------------
| HEADER
|--------------------------------------------------------------------------
*/

.header {
    height: 70px;
    background: #ffffff;
    border-bottom: 1px solid #e8ebf1;

    display: flex;
    align-items: center;
    justify-content: space-between;

    padding: 0 28px;

    position: sticky;
    top: 0;
    z-index: 20;
}

.logo {
    font-size: 20px;
    font-weight: 800;
}

.logo span {
    color: #536dfe;
}

.header-right {
    display: flex;
    align-items: center;
    gap: 12px;
}

.admin-badge {
    background: #eef1ff;
    color: #4355c9;
    padding: 7px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 700;
}

/*
|--------------------------------------------------------------------------
| PAGE
|--------------------------------------------------------------------------
*/

.container {
    max-width: 1450px;
    margin: auto;
    padding: 28px;
}

.page-title {
    margin-bottom: 22px;
}

.page-title h1 {
    margin: 0;
    font-size: 28px;
}

.page-title p {
    margin: 6px 0 0;
    color: #7c8496;
}

/*
|--------------------------------------------------------------------------
| STATS
|--------------------------------------------------------------------------
*/

.stats {
    display: grid;
    grid-template-columns:
        repeat(4, minmax(0, 1fr));

    gap: 15px;

    margin-bottom: 22px;
}

.stat {
    background: #fff;
    border: 1px solid #e8ebf1;
    border-radius: 16px;
    padding: 18px;
}

.stat-label {
    font-size: 13px;
    color: #858da0;
}

.stat-value {
    margin-top: 6px;
    font-size: 25px;
    font-weight: 800;
}

/*
|--------------------------------------------------------------------------
| LAYOUT
|--------------------------------------------------------------------------
*/

.support-layout {
    display: grid;
    grid-template-columns: 380px minmax(0, 1fr);
    gap: 18px;

    min-height: 650px;
}

/*
|--------------------------------------------------------------------------
| TICKETS
|--------------------------------------------------------------------------
*/

.tickets-panel,
.conversation-panel {
    background: #fff;
    border: 1px solid #e8ebf1;
    border-radius: 18px;
    overflow: hidden;
}

.panel-header {
    padding: 18px;
    border-bottom: 1px solid #edf0f5;
}

.panel-header h2 {
    margin: 0;
    font-size: 17px;
}

.ticket-list {
    max-height: 680px;
    overflow-y: auto;
}

.ticket {
    display: block;
    padding: 16px;
    border-bottom: 1px solid #f0f2f6;
    transition: .15s;
}

.ticket:hover {
    background: #f8f9fc;
}

.ticket.active {
    background: #f0f3ff;
    border-left: 3px solid #536dfe;
}

.ticket-top {
    display: flex;
    justify-content: space-between;
    gap: 10px;
}

.ticket-subject {
    font-weight: 700;
    font-size: 14px;
}

.ticket-user {
    margin-top: 5px;
    color: #687187;
    font-size: 12px;
}

.ticket-preview {
    margin-top: 8px;
    color: #858da0;
    font-size: 12px;

    overflow: hidden;
    white-space: nowrap;
    text-overflow: ellipsis;
}

.ticket-date {
    margin-top: 9px;
    color: #9ba2b1;
    font-size: 11px;
}

.badge {
    display: inline-flex;
    align-items: center;

    padding: 4px 8px;

    border-radius: 20px;

    font-size: 10px;
    font-weight: 700;

    white-space: nowrap;
}

.status-open {
    background: #fff3df;
    color: #b66b00;
}

.status-in_progress {
    background: #e9efff;
    color: #4059c9;
}

.status-resolved {
    background: #e6f8ef;
    color: #18844d;
}

.status-closed {
    background: #edf0f4;
    color: #697182;
}

.priority-high {
    background: #ffe8e8;
    color: #c43b3b;
}

.priority-medium {
    background: #fff3df;
    color: #b66b00;
}

.priority-low {
    background: #edf3ff;
    color: #5068c9;
}

/*
|--------------------------------------------------------------------------
| CONVERSATION
|--------------------------------------------------------------------------
*/

.conversation-header {
    padding: 20px;
    border-bottom: 1px solid #edf0f5;
}

.conversation-title {
    display: flex;
    justify-content: space-between;
    gap: 20px;
}

.conversation-title h2 {
    margin: 0;
    font-size: 19px;
}

.user-info {
    margin-top: 7px;
    color: #737c90;
    font-size: 13px;
}

.meta {
    display: flex;
    gap: 7px;
    flex-wrap: wrap;
    margin-top: 12px;
}

.messages {
    padding: 22px;
    max-height: 500px;
    overflow-y: auto;
    background: #fafbfe;
}

.message {
    max-width: 78%;
    margin-bottom: 14px;
}

.message.admin {
    margin-left: auto;
}

.message-bubble {
    padding: 13px 15px;
    border-radius: 15px;
    line-height: 1.5;
    font-size: 14px;
    white-space: pre-wrap;
    word-break: break-word;
}

.message.user .message-bubble {
    background: #ffffff;
    border: 1px solid #e6e9ef;
}

.message.admin .message-bubble {
    background: #536dfe;
    color: #fff;
}

.message-info {
    margin-top: 5px;
    color: #949baa;
    font-size: 10px;
}

.message.admin .message-info {
    text-align: right;
}

/*
|--------------------------------------------------------------------------
| ORIGINAL MESSAGE
|--------------------------------------------------------------------------
*/

.original-message {
    margin: 20px 22px 0;
    padding: 15px;
    background: #f1f4f9;
    border-radius: 12px;
}

.original-label {
    font-size: 11px;
    color: #7b8497;
    font-weight: 700;
    margin-bottom: 6px;
}

.original-text {
    font-size: 14px;
    white-space: pre-wrap;
}

/*
|--------------------------------------------------------------------------
| REPLY
|--------------------------------------------------------------------------
*/

.reply-box {
    padding: 18px;
    border-top: 1px solid #edf0f5;
}

.reply-box textarea {
    width: 100%;
    min-height: 100px;
    resize: vertical;

    border: 1px solid #dfe3eb;
    border-radius: 12px;

    padding: 13px;

    outline: none;
}

.reply-box textarea:focus {
    border-color: #536dfe;
}

.reply-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;

    margin-top: 10px;
    gap: 10px;
}

.btn {
    border: 0;
    border-radius: 10px;
    padding: 10px 15px;
    cursor: pointer;
    font-weight: 700;
}

.btn-primary {
    background: #536dfe;
    color: #fff;
}

.btn-primary:hover {
    background: #4355db;
}

.btn-light {
    background: #eef1f6;
    color: #394255;
}

/*
|--------------------------------------------------------------------------
| FORMS
|--------------------------------------------------------------------------
*/

.inline-form {
    display: inline-flex;
    align-items: center;
    gap: 7px;
}

select {
    border: 1px solid #dfe3eb;
    border-radius: 9px;
    padding: 7px 9px;
    background: #fff;
    font-size: 12px;
}

/*
|--------------------------------------------------------------------------
| EMPTY
|--------------------------------------------------------------------------
*/

.empty {
    min-height: 500px;

    display: flex;
    align-items: center;
    justify-content: center;

    text-align: center;
    color: #8b93a4;
    padding: 30px;
}

.empty-icon {
    font-size: 45px;
    margin-bottom: 12px;
}

/*
|--------------------------------------------------------------------------
| ALERT
|--------------------------------------------------------------------------
*/

.alert {
    margin-bottom: 18px;
    padding: 13px 16px;
    border-radius: 11px;
    background: #e7f8ef;
    color: #177c49;
    font-size: 13px;
}

/*
|--------------------------------------------------------------------------
| RESPONSIVE
|--------------------------------------------------------------------------
*/

@media (max-width: 900px) {

    .support-layout {
        grid-template-columns: 1fr;
    }

    .ticket-list {
        max-height: 400px;
    }

    .stats {
        grid-template-columns:
            repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 600px) {

    .container {
        padding: 15px;
    }

    .header {
        padding: 0 15px;
    }

    .stats {
        grid-template-columns: 1fr 1fr;
    }

    .conversation-title {
        flex-direction: column;
    }

    .message {
        max-width: 92%;
    }
}

</style>

</head>

<body>

<header class="header">

    <div class="logo">
        Invest<span>Pro</span>
    </div>

    <div class="header-right">

        <span class="admin-badge">
            <?= e($_SESSION['admin_role']) ?>
        </span>

        <strong>
            <?= e($_SESSION['admin_username'] ?? 'Admin') ?>
        </strong>

    </div>

</header>

<main class="container">

    <div class="page-title">

        <h1>Support</h1>

        <p>
            Gérez les demandes et répondez directement aux utilisateurs.
        </p>

    </div>

    <?php if ($success): ?>

        <div class="alert">

            <?php if ($success === 'reply'): ?>

                Réponse envoyée et notification utilisateur créée.

            <?php elseif ($success === 'status'): ?>

                Statut du ticket mis à jour.

            <?php elseif ($success === 'priority'): ?>

                Priorité du ticket mise à jour.

            <?php else: ?>

                Opération effectuée avec succès.

            <?php endif; ?>

        </div>

    <?php endif; ?>


    <section class="stats">

        <div class="stat">

            <div class="stat-label">
                Ouverts
            </div>

            <div class="stat-value">
                <?= $stats['open'] ?>
            </div>

        </div>


        <div class="stat">

            <div class="stat-label">
                En cours
            </div>

            <div class="stat-value">
                <?= $stats['in_progress'] ?>
            </div>

        </div>


        <div class="stat">

            <div class="stat-label">
                Résolus
            </div>

            <div class="stat-value">
                <?= $stats['resolved'] ?>
            </div>

        </div>


        <div class="stat">

            <div class="stat-label">
                Fermés
            </div>

            <div class="stat-value">
                <?= $stats['closed'] ?>
            </div>

        </div>

    </section>


    <section class="support-layout">


        <!-- =========================================================
             LISTE DES TICKETS
        ========================================================== -->

        <div class="tickets-panel">

            <div class="panel-header">

                <h2>
                    Demandes
                    (<?= count($tickets) ?>)
                </h2>

            </div>


            <div class="ticket-list">

                <?php if (!$tickets): ?>

                    <div class="empty">

                        <div>

                            <div class="empty-icon">
                                🎧
                            </div>

                            Aucun ticket de support.

                        </div>

                    </div>

                <?php else: ?>

                    <?php foreach ($tickets as $ticket): ?>

                        <a
                            href="?ticket=<?= (int) $ticket['id'] ?>"
                            class="ticket
                            <?= $selectedTicketId === (int) $ticket['id']
                                ? 'active'
                                : ''
                            ?>"
                        >

                            <div class="ticket-top">

                                <div class="ticket-subject">

                                    <?= e($ticket['subject']) ?>

                                </div>

                                <span
                                    class="badge status-<?= e($ticket['status']) ?>"
                                >
                                    <?= e($ticket['status']) ?>
                                </span>

                            </div>


                            <div class="ticket-user">

                                <?= e($ticket['username']) ?>

                                ·

                                <?= e($ticket['email']) ?>

                            </div>


                            <div class="ticket-preview">

                                <?= e($ticket['message']) ?>

                            </div>


                            <div class="ticket-date">

                                <?= e($ticket['updated_at']) ?>

                                ·

                                <span
                                    class="badge priority-<?= e($ticket['priority']) ?>"
                                >
                                    <?= e($ticket['priority']) ?>
                                </span>

                            </div>

                        </a>

                    <?php endforeach; ?>

                <?php endif; ?>

            </div>

        </div>


        <!-- =========================================================
             CONVERSATION
        ========================================================== -->

        <div class="conversation-panel">

            <?php if (!$selectedTicket): ?>

                <div class="empty">

                    <div>

                        <div class="empty-icon">
                            💬
                        </div>

                        <strong>
                            Sélectionnez une demande
                        </strong>

                        <p>
                            Choisissez un ticket dans la liste
                            pour afficher la conversation.
                        </p>

                    </div>

                </div>

            <?php else: ?>


                <div class="conversation-header">

                    <div class="conversation-title">

                        <div>

                            <h2>
                                <?= e($selectedTicket['subject']) ?>
                            </h2>

                            <div class="user-info">

                                👤
                                <?= e($selectedTicket['username']) ?>

                                ·

                                <?= e($selectedTicket['email']) ?>

                                <?php if (!empty($selectedTicket['phone'])): ?>

                                    ·

                                    <?= e($selectedTicket['phone']) ?>

                                <?php endif; ?>

                            </div>

                        </div>


                        <div>

                            <form
                                method="POST"
                                class="inline-form"
                            >

                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?= e($csrfToken) ?>"
                                >

                                <input
                                    type="hidden"
                                    name="action"
                                    value="status"
                                >

                                <input
                                    type="hidden"
                                    name="ticket_id"
                                    value="<?= (int) $selectedTicket['id'] ?>"
                                >

                                <select
                                    name="status"
                                    onchange="this.form.submit()"
                                >

                                    <?php

                                    $statuses = [
                                        'open' => 'Ouvert',
                                        'in_progress' => 'En cours',
                                        'resolved' => 'Résolu',
                                        'closed' => 'Fermé'
                                    ];

                                    foreach ($statuses as $value => $label):

                                    ?>

                                        <option
                                            value="<?= $value ?>"
                                            <?= $selectedTicket['status'] === $value
                                                ? 'selected'
                                                : ''
                                            ?>
                                        >
                                            <?= $label ?>
                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            </form>

                        </div>

                    </div>


                    <div class="meta">

                        <span
                            class="badge status-<?= e($selectedTicket['status']) ?>"
                        >
                            <?= e($selectedTicket['status']) ?>
                        </span>


                        <form
                            method="POST"
                            class="inline-form"
                        >

                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?= e($csrfToken) ?>"
                            >

                            <input
                                type="hidden"
                                name="action"
                                value="priority"
                            >

                            <input
                                type="hidden"
                                name="ticket_id"
                                value="<?= (int) $selectedTicket['id'] ?>"
                            >

                            <select
                                name="priority"
                                onchange="this.form.submit()"
                            >

                                <?php

                                $priorities = [
                                    'low' => 'Faible',
                                    'medium' => 'Moyenne',
                                    'high' => 'Haute'
                                ];

                                foreach ($priorities as $value => $label):

                                ?>

                                    <option
                                        value="<?= $value ?>"
                                        <?= $selectedTicket['priority'] === $value
                                            ? 'selected'
                                            : ''
                                        ?>
                                    >
                                        Priorité : <?= $label ?>
                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </form>

                    </div>

                </div>


                <!-- MESSAGE ORIGINAL -->

                <div class="original-message">

                    <div class="original-label">
                        DEMANDE INITIALE
                    </div>

                    <div class="original-text">
                        <?= e($selectedTicket['message']) ?>
                    </div>

                </div>


                <!-- CONVERSATION -->

                <div class="messages">

                    <?php if (!$replies): ?>

                        <div
                            style="
                                text-align:center;
                                color:#9299a8;
                                padding:30px;
                            "
                        >

                            Aucun échange pour le moment.

                        </div>

                    <?php else: ?>

                        <?php foreach ($replies as $reply): ?>

                            <div
                                class="message
                                <?= (int) $reply['is_admin'] === 1
                                    ? 'admin'
                                    : 'user'
                                ?>"
                            >

                                <div class="message-bubble">

                                    <?= e($reply['message']) ?>

                                </div>

                                <div class="message-info">

                                    <?= (int) $reply['is_admin'] === 1
                                        ? 'Vous'
                                        : e($reply['username'])
                                    ?>

                                    ·

                                    <?= e($reply['created_at']) ?>

                                </div>

                            </div>

                        <?php endforeach; ?>

                    <?php endif; ?>

                </div>


                <!-- RÉPONSE ADMIN -->

                <?php if ($selectedTicket['status'] !== 'closed'): ?>

                    <div class="reply-box">

                        <form method="POST">

                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?= e($csrfToken) ?>"
                            >

                            <input
                                type="hidden"
                                name="action"
                                value="reply"
                            >

                            <input
                                type="hidden"
                                name="ticket_id"
                                value="<?= (int) $selectedTicket['id'] ?>"
                            >

                            <textarea
                                name="message"
                                placeholder="Écrire une réponse à <?= e($selectedTicket['username']) ?>..."
                                maxlength="10000"
                                required
                            ></textarea>


                            <div class="reply-actions">

                                <span
                                    style="
                                        font-size:12px;
                                        color:#8991a2;
                                    "
                                >
                                    Une notification sera envoyée
                                    automatiquement à l'utilisateur.
                                </span>

                                <button
                                    type="submit"
                                    class="btn btn-primary"
                                >
                                    Envoyer la réponse
                                </button>

                            </div>

                        </form>

                    </div>

                <?php else: ?>

                    <div
                        style="
                            padding:20px;
                            text-align:center;
                            color:#858d9e;
                        "
                    >
                        Ce ticket est fermé.
                    </div>

                <?php endif; ?>


            <?php endif; ?>

        </div>

    </section>

</main>

<script>

/*
|--------------------------------------------------------------------------
| Scroll automatiquement vers le dernier message
|--------------------------------------------------------------------------
*/

const messages = document.querySelector('.messages');

if (messages) {
    messages.scrollTop = messages.scrollHeight;
}

</script>

</body>
</html>
