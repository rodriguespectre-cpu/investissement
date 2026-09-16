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
| CONFIGURATION WHATSAPP
|--------------------------------------------------------------------------
|
| Remplace simplement les 6 numéros ci-dessous.
| Format international SANS le + et SANS espaces.
|
*/

$whatsappServices = [

    [
        'name' => 'Service client',
        'description' => 'Questions générales',
        'icon' => 'fa-headset',
        'number' => '237690000001',
        'message' => 'Bonjour InvestPro, j’ai besoin d’aide concernant mon compte.'
    ],

    [
        'name' => 'Dépôts',
        'description' => 'Problème avec un dépôt',
        'icon' => 'fa-wallet',
        'number' => '237690000002',
        'message' => 'Bonjour InvestPro, j’ai une question concernant un dépôt.'
    ],

    [
        'name' => 'Retraits',
        'description' => 'Question sur un retrait',
        'icon' => 'fa-money-bill-transfer',
        'number' => '237690000003',
        'message' => 'Bonjour InvestPro, j’ai une question concernant mon retrait.'
    ],

    [
        'name' => 'Investissements',
        'description' => 'Plans et investissements',
        'icon' => 'fa-chart-line',
        'number' => '237690000004',
        'message' => 'Bonjour InvestPro, j’ai une question concernant mon investissement.'
    ],

    [
        'name' => 'Compte',
        'description' => 'Compte et sécurité',
        'icon' => 'fa-user-shield',
        'number' => '237690000005',
        'message' => 'Bonjour InvestPro, j’ai besoin d’aide concernant mon compte.'
    ],

    [
        'name' => 'Assistance VIP',
        'description' => 'Assistance prioritaire',
        'icon' => 'fa-crown',
        'number' => '237690000006',
        'message' => 'Bonjour InvestPro, je souhaite contacter l’assistance VIP.'
    ]

];


/*
|--------------------------------------------------------------------------
| VARIABLES
|--------------------------------------------------------------------------
*/

$successMessage = '';
$errorMessage = '';


/*
|--------------------------------------------------------------------------
| ENVOI D'UN TICKET
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $priority = trim($_POST['priority'] ?? 'medium');


    /*
    |--------------------------------------------------------------------------
    | VALIDATION
    |--------------------------------------------------------------------------
    */

    if ($subject === '') {

        $errorMessage = "Veuillez indiquer le sujet de votre demande.";

    } elseif (mb_strlen($subject) < 3) {

        $errorMessage = "Le sujet est trop court.";

    } elseif ($message === '') {

        $errorMessage = "Veuillez écrire votre message.";

    } elseif (mb_strlen($message) < 10) {

        $errorMessage = "Votre message doit contenir au moins 10 caractères.";

    } elseif (!in_array($priority, ['low', 'medium', 'high'], true)) {

        $errorMessage = "Priorité invalide.";

    } else {

        try {

            /*
            |--------------------------------------------------------------------------
            | CRÉATION DU TICKET
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                INSERT INTO support_tickets (
                    user_id,
                    subject,
                    message,
                    status,
                    priority
                )
                VALUES (
                    ?,
                    ?,
                    ?,
                    'open',
                    ?
                )
            ");

            $stmt->execute([
                $userId,
                $subject,
                $message,
                $priority
            ]);


            $ticketId = (int) $pdo->lastInsertId();


            /*
            |--------------------------------------------------------------------------
            | ENREGISTREMENT DU MESSAGE DANS SUPPORT_REPLIES
            |--------------------------------------------------------------------------
            |
            | Le premier message de l'utilisateur est également conservé
            | dans l'historique du ticket.
            |
            */

            $replyStmt = $pdo->prepare("
                INSERT INTO support_replies (
                    ticket_id,
                    user_id,
                    message,
                    is_admin
                )
                VALUES (
                    ?,
                    ?,
                    ?,
                    0
                )
            ");

            $replyStmt->execute([
                $ticketId,
                $userId,
                $message
            ]);


            /*
            |--------------------------------------------------------------------------
            | NOTIFICATION POUR L'ADMIN
            |--------------------------------------------------------------------------
            |
            | Les admins verront qu'un nouveau ticket a été créé.
            |
            */

            $adminStmt = $pdo->query("
                SELECT id
                FROM users
                WHERE role = 'admin'
                AND is_active = 1
            ");

            $admins = $adminStmt->fetchAll(PDO::FETCH_ASSOC);


            if ($admins) {

                $notificationStmt = $pdo->prepare("
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
                        'info',
                        ?,
                        ?,
                        0,
                        ?
                    )
                ");


                foreach ($admins as $admin) {

                    $notificationStmt->execute([
                        (int) $admin['id'],
                        'Nouveau ticket support',
                        'Un utilisateur vient de créer une nouvelle demande : ' . $subject,
                        'support.php?ticket=' . $ticketId
                    ]);
                }
            }


            $successMessage =
                "Votre demande a bien été envoyée. "
                . "L'équipe InvestPro vous répondra dès que possible.";

        } catch (PDOException $e) {

            $errorMessage =
                "Impossible d'envoyer votre demande pour le moment. "
                . "Veuillez réessayer.";
        }
    }
}


/*
|--------------------------------------------------------------------------
| RÉCUPÉRER LES TICKETS DE L'UTILISATEUR
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        subject,
        message,
        status,
        priority,
        created_at,
        updated_at
    FROM support_tickets
    WHERE user_id = ?
    ORDER BY created_at DESC
");

$stmt->execute([$userId]);

$tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| STATISTIQUES
|--------------------------------------------------------------------------
*/

$totalTickets = count($tickets);

$openTickets = 0;
$resolvedTickets = 0;

foreach ($tickets as $ticket) {

    if (
        $ticket['status'] === 'open'
        || $ticket['status'] === 'in_progress'
    ) {
        $openTickets++;
    }

    if (
        $ticket['status'] === 'resolved'
        || $ticket['status'] === 'closed'
    ) {
        $resolvedTickets++;
    }
}


/*
|--------------------------------------------------------------------------
| PREMIÈRE LETTRE UTILISATEUR
|--------------------------------------------------------------------------
*/

$firstLetter =
    strtoupper(
        mb_substr(
            $user['username'] ?? 'U',
            0,
            1
        )
    );


/*
|--------------------------------------------------------------------------
| LABELS
|--------------------------------------------------------------------------
*/

$statusLabels = [

    'open' => 'Ouvert',

    'in_progress' => 'En traitement',

    'resolved' => 'Résolu',

    'closed' => 'Fermé'

];


$priorityLabels = [

    'low' => 'Faible',

    'medium' => 'Normale',

    'high' => 'Urgente'

];

?>
<!DOCTYPE html>

<html lang="fr">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Support — InvestPro</title>


<link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
>


<style>

/*
|--------------------------------------------------------------------------
| BASE
|--------------------------------------------------------------------------
*/

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

    background:
        #f4f7fb;

    color:
        #172033;
}


a {
    text-decoration: none;
}


/*
|--------------------------------------------------------------------------
| PAGE
|--------------------------------------------------------------------------
*/

.support-page {

    max-width: 1250px;

    margin: 0 auto;

    padding:
        30px 20px 100px;
}


/*
|--------------------------------------------------------------------------
| HEADER
|--------------------------------------------------------------------------
*/

.support-header {

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 20px;

    margin-bottom: 30px;
}


.header-left {

    display: flex;

    align-items: center;

    gap: 15px;
}


.back-button {

    width: 44px;

    height: 44px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 14px;

    background: white;

    color: #172033;

    box-shadow:
        0 5px 20px rgba(20, 35, 60, .07);

    transition: .2s;
}


.back-button:hover {

    transform: translateX(-3px);

}


.eyebrow {

    display: block;

    color: #6b7280;

    font-size: 12px;

    font-weight: 800;

    letter-spacing: 1.5px;

    margin-bottom: 5px;
}


.support-header h1 {

    margin: 0;

    font-size: 30px;

    font-weight: 850;

}


.header-description {

    color: #718096;

    margin-top: 6px;

}


/*
|--------------------------------------------------------------------------
| HERO
|--------------------------------------------------------------------------
*/

.support-hero {

    background:
        linear-gradient(
            135deg,
            #111827,
            #1e3a5f
        );

    border-radius: 25px;

    padding: 30px;

    color: white;

    margin-bottom: 25px;

    position: relative;

    overflow: hidden;
}


.support-hero::after {

    content: "";

    position: absolute;

    width: 220px;

    height: 220px;

    border-radius: 50%;

    background:
        rgba(255,255,255,.06);

    right: -70px;

    top: -90px;
}


.hero-content {

    position: relative;

    z-index: 2;

    max-width: 700px;
}


.hero-icon {

    width: 55px;

    height: 55px;

    border-radius: 17px;

    display: flex;

    align-items: center;

    justify-content: center;

    background:
        rgba(255,255,255,.12);

    font-size: 22px;

    margin-bottom: 18px;
}


.support-hero h2 {

    margin: 0 0 10px;

    font-size: 25px;
}


.support-hero p {

    margin: 0;

    line-height: 1.7;

    color: #d8e1ee;
}


/*
|--------------------------------------------------------------------------
| STATS
|--------------------------------------------------------------------------
*/

.support-stats {

    display: grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap: 16px;

    margin-bottom: 25px;
}


.stat {

    background: white;

    border-radius: 18px;

    padding: 20px;

    box-shadow:
        0 8px 30px rgba(20,35,60,.05);

    display: flex;

    align-items: center;

    gap: 14px;
}


.stat-icon {

    width: 45px;

    height: 45px;

    border-radius: 13px;

    display: flex;

    align-items: center;

    justify-content: center;

    background: #eef4ff;

    color: #2563eb;
}


.stat strong {

    display: block;

    font-size: 22px;

}


.stat span {

    color: #7b8495;

    font-size: 13px;
}


/*
|--------------------------------------------------------------------------
| GRID
|--------------------------------------------------------------------------
*/

.support-grid {

    display: grid;

    grid-template-columns:
        minmax(0, 1.25fr)
        minmax(320px, .75fr);

    gap: 25px;

    align-items: start;
}


.card {

    background: white;

    border-radius: 22px;

    padding: 25px;

    box-shadow:
        0 8px 35px rgba(20,35,60,.06);

    margin-bottom: 25px;
}


.card-header {

    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 15px;

    margin-bottom: 22px;
}


.card-header h2 {

    margin: 0;

    font-size: 20px;
}


.card-header p {

    color: #788294;

    margin: 5px 0 0;

    font-size: 14px;
}


/*
|--------------------------------------------------------------------------
| ALERTS
|--------------------------------------------------------------------------
*/

.alert {

    padding: 15px 17px;

    border-radius: 14px;

    margin-bottom: 20px;

    display: flex;

    align-items: flex-start;

    gap: 11px;

    font-size: 14px;
}


.alert-success {

    background: #ecfdf3;

    color: #087443;

    border: 1px solid #bbf7d0;
}


.alert-error {

    background: #fff1f2;

    color: #be123c;

    border: 1px solid #fecdd3;
}


/*
|--------------------------------------------------------------------------
| FORM
|--------------------------------------------------------------------------
*/

.form-group {

    margin-bottom: 18px;
}


.form-group label {

    display: block;

    margin-bottom: 8px;

    font-size: 13px;

    font-weight: 750;

    color: #344054;
}


.form-control {

    width: 100%;

    border:
        1px solid #dce2eb;

    background: #fbfcfe;

    border-radius: 13px;

    padding: 14px 15px;

    font:
        inherit;

    color: #172033;

    outline: none;

    transition: .2s;
}


.form-control:focus {

    border-color: #2563eb;

    background: white;

    box-shadow:
        0 0 0 4px rgba(37,99,235,.08);
}


textarea.form-control {

    min-height: 145px;

    resize: vertical;

    line-height: 1.6;
}


.form-row {

    display: grid;

    grid-template-columns:
        1fr 1fr;

    gap: 15px;
}


.submit-button {

    width: 100%;

    border: 0;

    padding: 15px;

    border-radius: 14px;

    background:
        linear-gradient(
            135deg,
            #2563eb,
            #1d4ed8
        );

    color: white;

    font-weight: 800;

    font-size: 15px;

    cursor: pointer;

    transition: .2s;

    box-shadow:
        0 8px 20px rgba(37,99,235,.22);
}


.submit-button:hover {

    transform: translateY(-2px);

    box-shadow:
        0 12px 25px rgba(37,99,235,.28);
}


/*
|--------------------------------------------------------------------------
| TICKETS
|--------------------------------------------------------------------------
*/

.ticket-list {

    display: flex;

    flex-direction: column;

    gap: 12px;
}


.ticket {

    border:
        1px solid #e8ecf2;

    border-radius: 16px;

    padding: 17px;

    transition: .2s;
}


.ticket:hover {

    border-color: #cbd5e1;

    transform: translateY(-1px);
}


.ticket-top {

    display: flex;

    justify-content: space-between;

    align-items: flex-start;

    gap: 15px;
}


.ticket-title {

    font-weight: 800;

    color: #172033;

    margin-bottom: 7px;
}


.ticket-id {

    font-size: 12px;

    color: #8a94a6;
}


.ticket-message {

    color: #697386;

    font-size: 14px;

    line-height: 1.55;

    margin:
        12px 0;
}


.ticket-footer {

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 10px;

    color: #8a94a6;

    font-size: 12px;
}


.ticket-badges {

    display: flex;

    flex-wrap: wrap;

    gap: 6px;
}


.badge {

    display: inline-flex;

    align-items: center;

    padding:
        5px 9px;

    border-radius: 30px;

    font-size: 11px;

    font-weight: 800;
}


.badge-open {

    background: #eff6ff;

    color: #2563eb;
}


.badge-progress {

    background: #fff7ed;

    color: #c2410c;
}


.badge-resolved {

    background: #ecfdf3;

    color: #047857;
}


.badge-closed {

    background: #f3f4f6;

    color: #6b7280;
}


.badge-priority {

    background: #f5f3ff;

    color: #7c3aed;
}


/*
|--------------------------------------------------------------------------
| EMPTY
|--------------------------------------------------------------------------
*/

.empty {

    text-align: center;

    padding: 35px 15px;

    color: #8791a1;
}


.empty i {

    font-size: 30px;

    margin-bottom: 12px;
}


/*
|--------------------------------------------------------------------------
| WHATSAPP
|--------------------------------------------------------------------------
*/

.whatsapp-card {

    border:
        1px solid #e7ebf0;
}


.whatsapp-intro {

    color: #6f7888;

    font-size: 14px;

    line-height: 1.6;

    margin-bottom: 18px;
}


.whatsapp-services {

    display: flex;

    flex-direction: column;

    gap: 10px;
}


.whatsapp-service {

    width: 100%;

    display: flex;

    align-items: center;

    gap: 12px;

    border:
        1px solid #e8edf3;

    background: #fafbfc;

    border-radius: 15px;

    padding: 13px;

    cursor: pointer;

    text-align: left;

    transition: .2s;
}


.whatsapp-service:hover {

    border-color: #25d366;

    background: #f1fff6;

    transform: translateX(2px);
}


.wa-icon {

    width: 42px;

    height: 42px;

    border-radius: 12px;

    background: #25d366;

    color: white;

    display: flex;

    align-items: center;

    justify-content: center;

    flex-shrink: 0;

    font-size: 19px;
}


.wa-text strong {

    display: block;

    color: #172033;

    font-size: 14px;
}


.wa-text span {

    display: block;

    color: #8490a0;

    font-size: 12px;

    margin-top: 3px;
}


.wa-arrow {

    margin-left: auto;

    color: #9aa3b1;
}


/*
|--------------------------------------------------------------------------
| INFORMATION
|--------------------------------------------------------------------------
*/

.info-box {

    background:
        #f7f9fc;

    border-radius: 16px;

    padding: 17px;

    color: #657084;

    font-size: 13px;

    line-height: 1.7;
}


.info-box strong {

    color: #172033;
}


/*
|--------------------------------------------------------------------------
| FLOATING WHATSAPP
|--------------------------------------------------------------------------
*/

.whatsapp-floating {

    position: fixed;

    right: 22px;

    bottom: 22px;

    width: 58px;

    height: 58px;

    border-radius: 50%;

    background: #25d366;

    color: white;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 25px;

    box-shadow:
        0 10px 30px rgba(37,211,102,.35);

    z-index: 999;

    cursor: pointer;

    border: 0;

    transition: .2s;
}


.whatsapp-floating:hover {

    transform:
        translateY(-3px)
        scale(1.03);
}


/*
|--------------------------------------------------------------------------
| WHATSAPP MODAL
|--------------------------------------------------------------------------
*/

.whatsapp-modal {

    position: fixed;

    inset: 0;

    background:
        rgba(15,23,42,.55);

    backdrop-filter: blur(4px);

    display: none;

    align-items: flex-end;

    justify-content: flex-end;

    padding: 25px;

    z-index: 1000;
}


.whatsapp-modal.show {

    display: flex;
}


.whatsapp-panel {

    width: min(390px, 100%);

    max-height: 85vh;

    overflow-y: auto;

    background: white;

    border-radius: 23px;

    padding: 22px;

    box-shadow:
        0 25px 80px rgba(0,0,0,.2);

    animation:
        modalIn .2s ease;
}


@keyframes modalIn {

    from {

        opacity: 0;

        transform:
            translateY(20px);
    }

    to {

        opacity: 1;

        transform:
            translateY(0);
    }
}


.whatsapp-panel-header {

    display: flex;

    justify-content: space-between;

    align-items: center;

    margin-bottom: 20px;
}


.whatsapp-panel-header strong {

    font-size: 18px;
}


.close-whatsapp {

    width: 36px;

    height: 36px;

    border: 0;

    border-radius: 10px;

    background: #f1f3f6;

    cursor: pointer;

    color: #475467;
}


@media (max-width: 850px) {

    .support-grid {

        grid-template-columns: 1fr;
    }

}


@media (max-width: 650px) {

    .support-page {

        padding:
            20px 14px 100px;
    }


    .support-header {

        align-items: flex-start;
    }


    .support-header h1 {

        font-size: 25px;
    }


    .support-stats {

        grid-template-columns: 1fr;
    }


    .form-row {

        grid-template-columns: 1fr;
    }


    .card {

        padding: 19px;
    }


    .support-hero {

        padding: 23px;
    }


    .ticket-top {

        flex-direction: column;
    }


    .ticket-footer {

        flex-direction: column;

        align-items: flex-start;
    }


    .whatsapp-modal {

        padding: 12px;
    }


    .whatsapp-panel {

        border-radius: 20px;
    }

}

</style>

</head>


<body>


<main class="support-page">


<!--
|--------------------------------------------------------------------------
| HEADER
|--------------------------------------------------------------------------
-->

<header class="support-header">

    <div class="header-left">

        <a
            href="index.php"
            class="back-button"
            aria-label="Retour"
        >

            <i class="fas fa-arrow-left"></i>

        </a>


        <div>

            <span class="eyebrow">
                ASSISTANCE INVESTPRO
            </span>

            <h1>
                Centre de support
            </h1>

            <div class="header-description">
                Nous sommes là pour vous accompagner.
            </div>

        </div>

    </div>

</header>


<!--
|--------------------------------------------------------------------------
| HERO
|--------------------------------------------------------------------------
-->

<section class="support-hero">

    <div class="hero-content">

        <div class="hero-icon">

            <i class="fas fa-headset"></i>

        </div>


        <h2>
            Comment pouvons-nous vous aider ?
        </h2>


        <p>

            Envoyez votre demande à notre équipe.
            Votre message sera traité par notre administration
            et vous recevrez la réponse directement dans
            vos notifications InvestPro.

        </p>

    </div>

</section>


<!--
|--------------------------------------------------------------------------
| ALERTS
|--------------------------------------------------------------------------
-->

<?php if ($successMessage): ?>

    <div class="alert alert-success">

        <i class="fas fa-circle-check"></i>

        <div>
            <?= htmlspecialchars($successMessage) ?>
        </div>

    </div>

<?php endif; ?>


<?php if ($errorMessage): ?>

    <div class="alert alert-error">

        <i class="fas fa-circle-exclamation"></i>

        <div>
            <?= htmlspecialchars($errorMessage) ?>
        </div>

    </div>

<?php endif; ?>


<!--
|--------------------------------------------------------------------------
| STATS
|--------------------------------------------------------------------------
-->

<section class="support-stats">

    <div class="stat">

        <div class="stat-icon">

            <i class="fas fa-ticket"></i>

        </div>

        <div>

            <strong>
                <?= $totalTickets ?>
            </strong>

            <span>
                Demandes envoyées
            </span>

        </div>

    </div>


    <div class="stat">

        <div class="stat-icon">

            <i class="fas fa-clock"></i>

        </div>

        <div>

            <strong>
                <?= $openTickets ?>
            </strong>

            <span>
                Demandes en cours
            </span>

        </div>

    </div>


    <div class="stat">

        <div class="stat-icon">

            <i class="fas fa-circle-check"></i>

        </div>

        <div>

            <strong>
                <?= $resolvedTickets ?>
            </strong>

            <span>
                Demandes résolues
            </span>

        </div>

    </div>

</section>


<!--
|--------------------------------------------------------------------------
| CONTENT GRID
|--------------------------------------------------------------------------
-->

<div class="support-grid">


<!--
|--------------------------------------------------------------------------
| LEFT COLUMN
|--------------------------------------------------------------------------
-->

<div>


    <!-- FORMULAIRE -->

    <section class="card">

        <div class="card-header">

            <div>

                <h2>
                    Envoyer une demande
                </h2>

                <p>
                    Expliquez-nous clairement votre problème.
                </p>

            </div>

            <i
                class="fas fa-paper-plane"
                style="color:#2563eb;font-size:20px;"
            ></i>

        </div>


        <form
            method="POST"
            action=""
        >


            <div class="form-group">

                <label for="subject">
                    Sujet
                </label>

                <input
                    type="text"
                    id="subject"
                    name="subject"
                    class="form-control"
                    placeholder="Ex : Problème avec mon retrait"
                    maxlength="255"
                    required
                >

            </div>


            <div class="form-row">

                <div class="form-group">

                    <label for="priority">
                        Priorité
                    </label>

                    <select
                        id="priority"
                        name="priority"
                        class="form-control"
                    >

                        <option value="low">
                            Faible
                        </option>

                        <option
                            value="medium"
                            selected
                        >
                            Normale
                        </option>

                        <option value="high">
                            Urgente
                        </option>

                    </select>

                </div>


                <div class="form-group">

                    <label>
                        Réponse
                    </label>

                    <div
                        class="form-control"
                        style="
                            display:flex;
                            align-items:center;
                            color:#667085;
                            background:#f8fafc;
                        "
                    >

                        <i
                            class="fas fa-bell"
                            style="margin-right:8px;color:#2563eb;"
                        ></i>

                        Par notification

                    </div>

                </div>

            </div>


            <div class="form-group">

                <label for="message">
                    Votre message
                </label>

                <textarea
                    id="message"
                    name="message"
                    class="form-control"
                    placeholder="Décrivez votre demande..."
                    maxlength="5000"
                    required
                ></textarea>

            </div>


            <button
                type="submit"
                class="submit-button"
            >

                <i class="fas fa-paper-plane"></i>

                Envoyer ma demande

            </button>


        </form>

    </section>


    <!-- HISTORIQUE -->

    <section class="card">

        <div class="card-header">

            <div>

                <h2>
                    Mes demandes
                </h2>

                <p>
                    Historique de vos demandes au support.
                </p>

            </div>

        </div>


        <?php if (!empty($tickets)): ?>

            <div class="ticket-list">

                <?php foreach ($tickets as $ticket): ?>

                    <?php

                    $ticketStatus =
                        $ticket['status'];

                    $statusClass = match ($ticketStatus) {

                        'open' => 'badge-open',

                        'in_progress' => 'badge-progress',

                        'resolved' => 'badge-resolved',

                        'closed' => 'badge-closed',

                        default => 'badge-closed'

                    };

                    ?>

                    <article class="ticket">

                        <div class="ticket-top">

                            <div>

                                <div class="ticket-title">

                                    <?= htmlspecialchars(
                                        $ticket['subject']
                                    ) ?>

                                </div>

                                <div class="ticket-id">

                                    Ticket #
                                    <?= (int) $ticket['id'] ?>

                                </div>

                            </div>


                            <div class="ticket-badges">

                                <span
                                    class="badge <?= $statusClass ?>"
                                >

                                    <?= htmlspecialchars(
                                        $statusLabels[$ticketStatus]
                                        ?? $ticketStatus
                                    ) ?>

                                </span>


                                <span class="badge badge-priority">

                                    <?= htmlspecialchars(
                                        $priorityLabels[
                                            $ticket['priority']
                                        ]
                                        ?? $ticket['priority']
                                    ) ?>

                                </span>

                            </div>

                        </div>


                        <div class="ticket-message">

                            <?= nl2br(
                                htmlspecialchars(
                                    $ticket['message']
                                )
                            ) ?>

                        </div>


                        <div class="ticket-footer">

                            <span>

                                <i class="far fa-calendar"></i>

                                <?= date(
                                    'd/m/Y à H:i',
                                    strtotime(
                                        $ticket['created_at']
                                    )
                                ) ?>

                            </span>


                            <span>

                                <i class="fas fa-bell"></i>

                                Les réponses arrivent
                                dans vos notifications

                            </span>

                        </div>

                    </article>

                <?php endforeach; ?>

            </div>

        <?php else: ?>

            <div class="empty">

                <i class="far fa-comment-dots"></i>

                <p>
                    Vous n'avez encore envoyé aucune demande.
                </p>

            </div>

        <?php endif; ?>

    </section>


</div>


<!--
|--------------------------------------------------------------------------
| RIGHT COLUMN
|--------------------------------------------------------------------------
-->

<div>


    <!-- WHATSAPP -->

    <section class="card whatsapp-card">

        <div class="card-header">

            <div>

                <h2>
                    WhatsApp
                </h2>

                <p>
                    Contactez directement notre équipe.
                </p>

            </div>

            <i
                class="fab fa-whatsapp"
                style="
                    color:#25d366;
                    font-size:25px;
                "
            ></i>

        </div>


        <p class="whatsapp-intro">

            Sélectionnez le service correspondant
            à votre demande. InvestPro vous redirigera
            automatiquement vers le conseiller approprié.

        </p>


        <div class="whatsapp-services">

            <?php foreach ($whatsappServices as $service): ?>

                <button
                    type="button"
                    class="whatsapp-service"
                    data-number="<?= htmlspecialchars(
                        $service['number']
                    ) ?>"
                    data-message="<?= htmlspecialchars(
                        $service['message']
                    ) ?>"
                >

                    <div class="wa-icon">

                        <i class="fab fa-whatsapp"></i>

                    </div>


                    <div class="wa-text">

                        <strong>

                            <?= htmlspecialchars(
                                $service['name']
                            ) ?>

                        </strong>

                        <span>

                            <?= htmlspecialchars(
                                $service['description']
                            ) ?>

                        </span>

                    </div>


                    <i class="fas fa-chevron-right wa-arrow"></i>

                </button>

            <?php endforeach; ?>

        </div>

    </section>


    <!-- INFO -->

    <section class="card">

        <div class="card-header">

            <div>

                <h2>
                    Informations
                </h2>

            </div>

        </div>


        <div class="info-box">

            <strong>
                <i class="fas fa-circle-info"></i>
                Temps de réponse
            </strong>

            <br><br>

            Notre équipe traite les demandes
            dans les meilleurs délais.

            <br><br>

            Lorsqu'un administrateur répond à votre ticket,
            une notification vous est automatiquement envoyée
            dans votre espace InvestPro.

        </div>

    </section>


</div>


</div>


</main>


<!--
|--------------------------------------------------------------------------
| BOUTON WHATSAPP FLOTTANT
|--------------------------------------------------------------------------
-->

<button
    type="button"
    class="whatsapp-floating"
    id="openWhatsapp"
    aria-label="Contacter InvestPro sur WhatsApp"
>

    <i class="fab fa-whatsapp"></i>

</button>


<!--
|--------------------------------------------------------------------------
| MODAL WHATSAPP
|--------------------------------------------------------------------------
-->

<div
    class="whatsapp-modal"
    id="whatsappModal"
>

    <div class="whatsapp-panel">

        <div class="whatsapp-panel-header">

            <strong>
                Choisissez un service
            </strong>

            <button
                type="button"
                class="close-whatsapp"
                id="closeWhatsapp"
            >

                <i class="fas fa-xmark"></i>

            </button>

        </div>


        <div class="whatsapp-services">

            <?php foreach ($whatsappServices as $service): ?>

                <button
                    type="button"
                    class="whatsapp-service"
                    data-number="<?= htmlspecialchars(
                        $service['number']
                    ) ?>"
                    data-message="<?= htmlspecialchars(
                        $service['message']
                    ) ?>"
                >

                    <div class="wa-icon">

                        <i class="fab fa-whatsapp"></i>

                    </div>


                    <div class="wa-text">

                        <strong>

                            <?= htmlspecialchars(
                                $service['name']
                            ) ?>

                        </strong>

                        <span>

                            <?= htmlspecialchars(
                                $service['description']
                            ) ?>

                        </span>

                    </div>


                    <i class="fas fa-chevron-right wa-arrow"></i>

                </button>

            <?php endforeach; ?>

        </div>

    </div>

</div>


<script>

/*
|--------------------------------------------------------------------------
| WHATSAPP
|--------------------------------------------------------------------------
*/

const whatsappModal =
    document.getElementById('whatsappModal');

const openWhatsapp =
    document.getElementById('openWhatsapp');

const closeWhatsapp =
    document.getElementById('closeWhatsapp');


if (openWhatsapp) {

    openWhatsapp.addEventListener(
        'click',
        function () {

            whatsappModal.classList.add('show');

        }
    );

}


if (closeWhatsapp) {

    closeWhatsapp.addEventListener(
        'click',
        function () {

            whatsappModal.classList.remove('show');

        }
    );

}


/*
|--------------------------------------------------------------------------
| FERMER EN CLIQUANT À L'EXTÉRIEUR
|--------------------------------------------------------------------------
*/

if (whatsappModal) {

    whatsappModal.addEventListener(
        'click',
        function (event) {

            if (event.target === whatsappModal) {

                whatsappModal.classList.remove('show');

            }

        }
    );

}


/*
|--------------------------------------------------------------------------
| REDIRECTION WHATSAPP
|--------------------------------------------------------------------------
*/

document
    .querySelectorAll('.whatsapp-service')
    .forEach(
        function (button) {

            button.addEventListener(
                'click',
                function () {

                    const number =
                        this.dataset.number;

                    const message =
                        this.dataset.message;


                    if (!number) {
                        return;
                    }


                    const url =
                        'https://wa.me/'
                        + number
                        + '?text='
                        + encodeURIComponent(message);


                    window.open(
                        url,
                        '_blank',
                        'noopener,noreferrer'
                    );

                }
            );

        }
    );


/*
|--------------------------------------------------------------------------
| ESCAPE
|--------------------------------------------------------------------------
*/

document.addEventListener(
    'keydown',
    function (event) {

        if (
            event.key === 'Escape'
            && whatsappModal
        ) {

            whatsappModal.classList.remove('show');

        }

    }
);

</script>


</body>

</html>
