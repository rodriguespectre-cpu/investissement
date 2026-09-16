<?php

session_start();

require_once "../../config/database.php";

/*
|--------------------------------------------------------------------------
| AUTH ADMIN
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
| INFORMATIONS ADMIN
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
    !$admin ||
    !in_array($admin['role'], ['admin', 'super_admin'], true)
) {
    http_response_code(403);
    exit("Accès interdit.");
}

/*
|--------------------------------------------------------------------------
| CONVERSATION SÉLECTIONNÉE
|--------------------------------------------------------------------------
*/

$user1 = isset($_GET['user1']) ? (int) $_GET['user1'] : 0;
$user2 = isset($_GET['user2']) ? (int) $_GET['user2'] : 0;

/*
|--------------------------------------------------------------------------
| LISTE DES CONVERSATIONS
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        cm1.sender_id,
        cm1.receiver_id,
        cm1.message,
        cm1.image_path,
        cm1.created_at,

        u1.username AS sender_username,
        u1.email AS sender_email,

        u2.username AS receiver_username,
        u2.email AS receiver_email,

        (
            SELECT COUNT(*)
            FROM chat_messages cm2
            WHERE
                (
                    cm2.sender_id = cm1.sender_id
                    AND cm2.receiver_id = cm1.receiver_id
                )
                OR
                (
                    cm2.sender_id = cm1.receiver_id
                    AND cm2.receiver_id = cm1.sender_id
                )
        ) AS message_count

    FROM chat_messages cm1

    INNER JOIN users u1
        ON u1.id = cm1.sender_id

    INNER JOIN users u2
        ON u2.id = cm1.receiver_id

    WHERE cm1.id = (
        SELECT MAX(cm3.id)
        FROM chat_messages cm3
        WHERE
            (
                cm3.sender_id = cm1.sender_id
                AND cm3.receiver_id = cm1.receiver_id
            )
            OR
            (
                cm3.sender_id = cm1.receiver_id
                AND cm3.receiver_id = cm1.sender_id
            )
    )

    ORDER BY cm1.created_at DESC
";

$stmt = $pdo->query($sql);
$conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| MESSAGES D'UNE CONVERSATION
|--------------------------------------------------------------------------
*/

$messages = [];

$selectedUser1 = null;
$selectedUser2 = null;

if ($user1 > 0 && $user2 > 0) {

    $stmt = $pdo->prepare("
        SELECT
            cm.id,
            cm.sender_id,
            cm.receiver_id,
            cm.message,
            cm.image_path,
            cm.created_at,
            u.username,
            u.email
        FROM chat_messages cm

        INNER JOIN users u
            ON u.id = cm.sender_id

        WHERE
            (
                cm.sender_id = ?
                AND cm.receiver_id = ?
            )
            OR
            (
                cm.sender_id = ?
                AND cm.receiver_id = ?
            )

        ORDER BY cm.created_at ASC
    ");

    $stmt->execute([
        $user1,
        $user2,
        $user2,
        $user1
    ]);

    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    /*
    |--------------------------------------------------------------------------
    | UTILISATEURS
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT id, username, email
        FROM users
        WHERE id IN (?, ?)
    ");

    $stmt->execute([$user1, $user2]);

    $selectedUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($selectedUsers as $u) {

        if ((int)$u['id'] === $user1) {
            $selectedUser1 = $u;
        }

        if ((int)$u['id'] === $user2) {
            $selectedUser2 = $u;
        }
    }
}

/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/

function initials(string $name): string
{
    $name = trim($name);

    if ($name === '') {
        return '?';
    }

    $parts = preg_split('/\s+/', $name);

    if (count($parts) >= 2) {
        return strtoupper(
            mb_substr($parts[0], 0, 1) .
            mb_substr($parts[1], 0, 1)
        );
    }

    return strtoupper(mb_substr($name, 0, 2));
}

function e(?string $value): string
{
    return htmlspecialchars(
        $value ?? '',
        ENT_QUOTES,
        'UTF-8'
    );
}

?><!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><meta
name="viewport"
content="width=device-width, initial-scale=1.0"

«»

<title>InvestPro — Conversations</title><style>

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

a {
    text-decoration: none;
    color: inherit;
}

/*
|--------------------------------------------------------------------------
| HEADER
|--------------------------------------------------------------------------
*/

.header {
    height: 70px;
    background: #ffffff;
    border-bottom: 1px solid #e8ebf2;

    display: flex;
    align-items: center;
    justify-content: space-between;

    padding: 0 28px;

    position: sticky;
    top: 0;
    z-index: 20;
}

.logo {
    font-size: 21px;
    font-weight: 800;
    color: #172033;
}

.logo span {
    color: #6c5ce7;
}

.header-right {
    display: flex;
    align-items: center;
    gap: 14px;
}

.admin-badge {
    background: #f0edff;
    color: #604fc7;

    padding: 8px 13px;
    border-radius: 10px;

    font-size: 13px;
    font-weight: 700;
}

/*
|--------------------------------------------------------------------------
| LAYOUT
|--------------------------------------------------------------------------
*/

.container {
    max-width: 1250px;
    margin: auto;
    padding: 28px;
}

.page-title {
    margin-bottom: 22px;
}

.page-title h1 {
    font-size: 26px;
    font-weight: 800;
}

.page-title p {
    color: #7b8498;
    margin-top: 5px;
    font-size: 14px;
}

/*
|--------------------------------------------------------------------------
| CONTENT
|--------------------------------------------------------------------------
*/

.chat-layout {
    display: grid;
    grid-template-columns: 380px 1fr;
    gap: 20px;
}

.card {
    background: #ffffff;
    border: 1px solid #e8ebf2;
    border-radius: 18px;
    overflow: hidden;
}

/*
|--------------------------------------------------------------------------
| CONVERSATIONS
|--------------------------------------------------------------------------
*/

.card-header {
    padding: 18px 20px;
    border-bottom: 1px solid #edf0f5;
}

.card-header h2 {
    font-size: 16px;
}

.conversation-item {
    display: block;
    padding: 16px 18px;
    border-bottom: 1px solid #f0f2f6;
    transition: .2s;
}

.conversation-item:hover {
    background: #f8f9fd;
}

.people {
    display: flex;
    align-items: center;
    gap: 11px;
}

.avatar {
    width: 40px;
    height: 40px;

    border-radius: 50%;

    background: #eeeaff;
    color: #6654d8;

    display: flex;
    align-items: center;
    justify-content: center;

    font-size: 12px;
    font-weight: 800;

    flex-shrink: 0;
}

.names {
    min-width: 0;
    flex: 1;
}

.names strong {
    display: block;
    font-size: 14px;
}

.names small {
    color: #8a92a4;
    font-size: 11px;
}

.preview {
    margin-top: 8px;

    color: #777f91;
    font-size: 12px;

    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.count {
    background: #f0f2f7;
    color: #687084;

    padding: 4px 8px;
    border-radius: 8px;

    font-size: 11px;
    font-weight: 700;
}

/*
|--------------------------------------------------------------------------
| CHAT
|--------------------------------------------------------------------------
*/

.chat-card {
    min-height: 600px;
    display: flex;
    flex-direction: column;
}

.chat-header {
    padding: 18px 20px;
    border-bottom: 1px solid #edf0f5;

    display: flex;
    align-items: center;
    gap: 12px;
}

.chat-header .avatar {
    width: 44px;
    height: 44px;
}

.chat-header strong {
    font-size: 15px;
}

.chat-header small {
    display: block;
    color: #8991a3;
    margin-top: 3px;
}

.messages {
    flex: 1;
    padding: 22px;

    max-height: 570px;
    overflow-y: auto;
}

.message {
    display: flex;
    margin-bottom: 14px;
}

.message.mine {
    justify-content: flex-end;
}

.bubble {
    max-width: 70%;

    padding: 11px 14px;

    border-radius: 14px;

    background: #f0f2f7;

    font-size: 13px;
    line-height: 1.45;
}

.message.mine .bubble {
    background: #6c5ce7;
    color: white;
}

.time {
    margin-top: 5px;

    font-size: 10px;
    color: #9299aa;
}

.message.mine .time {
    color: #d9d4ff;
    text-align: right;
}

.chat-image {
    max-width: 260px;
    max-height: 300px;

    display: block;

    border-radius: 10px;
    margin-top: 5px;
}

.empty {
    height: 100%;

    min-height: 450px;

    display: flex;
    align-items: center;
    justify-content: center;

    text-align: center;

    color: #8a92a4;
    font-size: 14px;
}

.empty-icon {
    font-size: 38px;
    margin-bottom: 10px;
}

/*
|--------------------------------------------------------------------------
| RESPONSIVE
|--------------------------------------------------------------------------
*/

@media (max-width: 850px) {

    .container {
        padding: 16px;
    }

    .chat-layout {
        grid-template-columns: 1fr;
    }

    .conversations {
        max-height: 420px;
        overflow-y: auto;
    }

    .header {
        padding: 0 16px;
    }

}

</style></head><body><header class="header"><a href="index.php" class="logo">
    Invest<span>Pro</span>
</a>

<div class="header-right">

    <span class="admin-badge">
        <?= e($admin['role']) ?>
    </span>

</div>

</header><main class="container"><div class="page-title">

    <h1>Conversations</h1>

    <p>
        Consultez les conversations entre les utilisateurs.
    </p>

</div>

<div class="chat-layout">

    <!-- LISTE -->

    <section class="card conversations">

        <div class="card-header">

            <h2>
                Conversations
                (<?= count($conversations) ?>)
            </h2>

        </div>

        <?php if (!$conversations): ?>

            <div class="empty">

                <div>
                    <div class="empty-icon">💬</div>
                    Aucune conversation.
                </div>

            </div>

        <?php else: ?>

            <?php foreach ($conversations as $conversation): ?>

                <?php

                $senderId =
                    (int)$conversation['sender_id'];

                $receiverId =
                    (int)$conversation['receiver_id'];

                ?>

                <a
                    class="conversation-item"
                    href="?user1=<?= $senderId ?>&user2=<?= $receiverId ?>"
                >

                    <div class="people">

                        <div class="avatar">
                            <?= e(
                                initials(
                                    $conversation['sender_username']
                                )
                            ) ?>
                        </div>

                        <div class="names">

                            <strong>
                                <?= e(
                                    $conversation['sender_username']
                                ) ?>

                                ↔

                                <?= e(
                                    $conversation['receiver_username']
                                ) ?>
                            </strong>

                            <small>
                                <?= e(
                                    $conversation['sender_email']
                                ) ?>
                            </small>

                        </div>

                        <span class="count">
                            <?= (int)$conversation['message_count'] ?>
                        </span>

                    </div>

                    <div class="preview">

                        <?php if (
                            trim(
                                (string)$conversation['message']
                            ) !== ''
                        ): ?>

                            <?= e(
                                $conversation['message']
                            ) ?>

                        <?php elseif (
                            !empty(
                                $conversation['image_path']
                            )
                        ): ?>

                            📷 Photo

                        <?php else: ?>

                            Message

                        <?php endif; ?>

                    </div>

                </a>

            <?php endforeach; ?>

        <?php endif; ?>

    </section>


    <!-- CONVERSATION -->

    <section class="card chat-card">

        <?php if (
            $selectedUser1 &&
            $selectedUser2
        ): ?>

            <div class="chat-header">

                <div class="avatar">

                    <?= e(
                        initials(
                            $selectedUser1['username']
                        )
                    ) ?>

                </div>

                <div>

                    <strong>

                        <?= e(
                            $selectedUser1['username']
                        ) ?>

                        ↔

                        <?= e(
                            $selectedUser2['username']
                        ) ?>

                    </strong>

                    <small>
                        <?= count($messages) ?> message(s)
                    </small>

                </div>

            </div>


            <div class="messages">

                <?php foreach ($messages as $message): ?>

                    <?php
                    $isMine =
                        (int)$message['sender_id']
                        === $user1;
                    ?>

                    <div
                        class="message <?= $isMine ? 'mine' : '' ?>"
                    >

                        <div>

                            <div class="bubble">

                                <?php if (
                                    trim(
                                        (string)$message['message']
                                    ) !== ''
                                ): ?>

                                    <?= nl2br(
                                        e(
                                            $message['message']
                                        )
                                    ) ?>

                                <?php endif; ?>


                                <?php if (
                                    !empty(
                                        $message['image_path']
                                    )
                                ): ?>

                                    <img
                                        class="chat-image"
                                        src="<?= e(
                                            $message['image_path']
                                        ) ?>"
                                        alt="Photo"
                                        loading="lazy"
                                    >

                                <?php endif; ?>

                            </div>

                            <div class="time">

                                <?= e(
                                    $message['username']
                                ) ?>

                                ·

                                <?= date(
                                    'd/m/Y H:i',
                                    strtotime(
                                        $message['created_at']
                                    )
                                ) ?>

                            </div>

                        </div>

                    </div>

                <?php endforeach; ?>

            </div>

        <?php else: ?>

            <div class="empty">

                <div>

                    <div class="empty-icon">
                        💬
                    </div>

                    <strong>
                        Sélectionnez une conversation
                    </strong>

                    <p style="margin-top:6px;">
                        Choisissez une conversation à gauche
                        pour consulter les messages.
                    </p>

                </div>

            </div>

        <?php endif; ?>

    </section>

</div>

</main></body>
</html>
