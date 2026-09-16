<?php
session_start();
require_once '../config/database.php';

/*
|--------------------------------------------------------------------------
| AUTH
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$currentUserId = (int) $_SESSION['user_id'];

/*
|--------------------------------------------------------------------------
| CSRF
|--------------------------------------------------------------------------
*/

if (empty($_SESSION['chat_csrf'])) {
    $_SESSION['chat_csrf'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['chat_csrf'];

/*
|--------------------------------------------------------------------------
| UPLOAD
|--------------------------------------------------------------------------
*/

$uploadDir = dirname(__DIR__) . '/uploads/chat/';

if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0755, true)) {
        $uploadDir = null;
    }
}

/*
|--------------------------------------------------------------------------
| JSON RESPONSE
|--------------------------------------------------------------------------
*/

function jsonResponse(array $data): never
{
    if (ob_get_length()) {
        ob_clean();
    }

    header('Content-Type: application/json; charset=utf-8');

    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES |
        JSON_INVALID_UTF8_SUBSTITUTE
    );

    exit;
}

/*
|--------------------------------------------------------------------------
| CSRF
|--------------------------------------------------------------------------
*/

function checkCsrf(): void
{
    $token = $_POST['csrf_token'] ?? '';

    if (
        empty($_SESSION['chat_csrf']) ||
        !hash_equals($_SESSION['chat_csrf'], (string)$token)
    ) {
        jsonResponse([
            'success' => false,
            'error' => 'Session de sécurité expirée.'
        ]);
    }
}

/*
|--------------------------------------------------------------------------
| INITIAL
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

/*
|--------------------------------------------------------------------------
| API
|--------------------------------------------------------------------------
*/

$action = $_GET['action'] ?? $_POST['action'] ?? '';

/*
|--------------------------------------------------------------------------
| USERS
|--------------------------------------------------------------------------
*/

if ($action === 'users') {

    $search = trim($_GET['search'] ?? '');

    if ($search !== '') {

        $stmt = $pdo->prepare("
            SELECT
                id,
                username
            FROM users
            WHERE id != ?
              AND is_active = 1
              AND username LIKE ?
            ORDER BY username ASC
            LIMIT 50
        ");

        $stmt->execute([
            $currentUserId,
            '%' . $search . '%'
        ]);

    } else {

        $stmt = $pdo->prepare("
            SELECT
                id,
                username
            FROM users
            WHERE id != ?
              AND is_active = 1
            ORDER BY username ASC
            LIMIT 50
        ");

        $stmt->execute([$currentUserId]);
    }

    $users = [];

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {

        $users[] = [
            'id' => (int)$row['id'],
            'username' => $row['username'],
            'initials' => initials($row['username'])
        ];
    }

    jsonResponse([
        'success' => true,
        'users' => $users
    ]);
}

/*
|--------------------------------------------------------------------------
| MESSAGES
|--------------------------------------------------------------------------
*/

if ($action === 'messages') {

    $otherUserId = (int)($_GET['user_id'] ?? 0);

    if ($otherUserId <= 0) {
        jsonResponse([
            'success' => false,
            'error' => 'Utilisateur invalide.'
        ]);
    }

    $stmt = $pdo->prepare("
        SELECT
            id,
            sender_id,
            receiver_id,
            message,
            image_path,
            created_at
        FROM chat_messages
        WHERE
            (
                sender_id = ?
                AND receiver_id = ?
            )
            OR
            (
                sender_id = ?
                AND receiver_id = ?
            )
        ORDER BY id ASC
        LIMIT 300
    ");

    $stmt->execute([
        $currentUserId,
        $otherUserId,
        $otherUserId,
        $currentUserId
    ]);

    $messages = [];

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {

        $messages[] = [
            'id' => (int)$row['id'],
            'sender_id' => (int)$row['sender_id'],
            'receiver_id' => (int)$row['receiver_id'],
            'message' => $row['message'] ?? '',
            'image_path' => $row['image_path'] ?? '',
            'created_at' => $row['created_at']
        ];
    }

    jsonResponse([
        'success' => true,
        'messages' => $messages
    ]);
}

/*
|--------------------------------------------------------------------------
| SEND MESSAGE
|--------------------------------------------------------------------------
*/

if ($action === 'send') {

    checkCsrf();

    $receiverId = (int)($_POST['receiver_id'] ?? 0);

    $message = trim(
        (string)($_POST['message'] ?? '')
    );

    if ($receiverId <= 0 || $receiverId === $currentUserId) {

        jsonResponse([
            'success' => false,
            'error' => 'Destinataire invalide.'
        ]);
    }

    /*
    | Vérifier le destinataire
    */

    $stmt = $pdo->prepare("
        SELECT id
        FROM users
        WHERE id = ?
          AND is_active = 1
        LIMIT 1
    ");

    $stmt->execute([$receiverId]);

    if (!$stmt->fetchColumn()) {

        jsonResponse([
            'success' => false,
            'error' => 'Utilisateur introuvable.'
        ]);
    }

    /*
    | Limite texte
    */

    if (mb_strlen($message) > 5000) {

        jsonResponse([
            'success' => false,
            'error' => 'Message trop long.'
        ]);
    }

    $imagePath = '';

    /*
    |--------------------------------------------------------------------------
    | IMAGE
    |--------------------------------------------------------------------------
    */

    if (
        isset($_FILES['image']) &&
        $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE
    ) {

        if ($_FILES['image']['error'] !== UPLOAD_ERR_OK) {

            jsonResponse([
                'success' => false,
                'error' => 'Erreur lors de l’envoi de la photo.'
            ]);
        }

        if ($_FILES['image']['size'] > 8 * 1024 * 1024) {

            jsonResponse([
                'success' => false,
                'error' => 'La photo ne doit pas dépasser 8 Mo.'
            ]);
        }

        if (!$uploadDir) {

            jsonResponse([
                'success' => false,
                'error' => 'Le dossier d’upload est indisponible.'
            ]);
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);

        $mime = $finfo->file(
            $_FILES['image']['tmp_name']
        );

        $allowed = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp'
        ];

        if (!isset($allowed[$mime])) {

            jsonResponse([
                'success' => false,
                'error' => 'Format de photo non autorisé.'
            ]);
        }

        $filename =
            bin2hex(random_bytes(20)) .
            '.' .
            $allowed[$mime];

        $destination =
            $uploadDir . $filename;

        if (
            !move_uploaded_file(
                $_FILES['image']['tmp_name'],
                $destination
            )
        ) {

            jsonResponse([
                'success' => false,
                'error' => 'Impossible de sauvegarder la photo.'
            ]);
        }

        /*
        | IMPORTANT :
        | chemin absolu depuis le site
        */

        $imagePath =
            '/uploads/chat/' . $filename;
    }

    /*
    |--------------------------------------------------------------------------
    | MESSAGE VIDE
    |--------------------------------------------------------------------------
    */

    if ($message === '' && $imagePath === '') {

        jsonResponse([
            'success' => false,
            'error' => 'Écrivez un message ou choisissez une photo.'
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | INSERT
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        INSERT INTO chat_messages
        (
            sender_id,
            receiver_id,
            message,
            image_path
        )
        VALUES (?, ?, ?, ?)
    ");

    $stmt->execute([
        $currentUserId,
        $receiverId,
        $message !== '' ? $message : null,
        $imagePath
    ]);

    $messageId = (int)$pdo->lastInsertId();

    jsonResponse([
        'success' => true,
        'message' => [
            'id' => $messageId,
            'sender_id' => $currentUserId,
            'receiver_id' => $receiverId,
            'message' => $message,
            'image_path' => $imagePath,
            'created_at' => date('Y-m-d H:i:s')
        ]
    ]);
}

/*
|--------------------------------------------------------------------------
| CURRENT USER
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT id, username
    FROM users
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([$currentUserId]);

$currentUser = $stmt->fetch(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="fr">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Messages - InvestPro</title>

<style>

* {
    box-sizing: border-box;
}

html,
body {
    margin: 0;
    padding: 0;
    width: 100%;
    height: 100%;
    font-family:
        -apple-system,
        BlinkMacSystemFont,
        "Segoe UI",
        sans-serif;
    background: #f4f6fb;
}

body {
    overflow: hidden;
}

/*
|--------------------------------------------------------------------------
| APP
|--------------------------------------------------------------------------
*/

.chat-app {
    display: flex;
    width: 100%;
    height: 100vh;
    background: #fff;
}

/*
|--------------------------------------------------------------------------
| SIDEBAR
|--------------------------------------------------------------------------
*/

.sidebar {
    width: 330px;
    min-width: 330px;
    border-right: 1px solid #e8eaf0;
    background: #fff;
    display: flex;
    flex-direction: column;
}

.sidebar-header {
    padding: 20px;
    border-bottom: 1px solid #eee;
}

.sidebar-header h2 {
    margin: 0 0 15px;
    font-size: 22px;
    color: #171923;
}

.search {
    width: 100%;
    padding: 12px 15px;
    border: 1px solid #e1e4ea;
    border-radius: 12px;
    outline: none;
    font-size: 14px;
    background: #f7f8fa;
}

.search:focus {
    border-color: #667eea;
    background: #fff;
}

.users {
    flex: 1;
    overflow-y: auto;
}

/*
|--------------------------------------------------------------------------
| USER
|--------------------------------------------------------------------------
*/

.user {
    display: flex;
    align-items: center;
    gap: 11px;
    padding: 11px 15px;
    cursor: pointer;
    transition: .15s;
}

.user:hover {
    background: #f6f7fb;
}

.user.active {
    background: #eef1ff;
}

.avatar {
    width: 42px;
    height: 42px;
    min-width: 42px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(
        135deg,
        #667eea,
        #764ba2
    );
    color: white;
    font-size: 13px;
    font-weight: 700;
}

.user-name {
    font-size: 14px;
    font-weight: 600;
    color: #252a35;
}

/*
|--------------------------------------------------------------------------
| CONVERSATION
|--------------------------------------------------------------------------
*/

.conversation {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
}

.chat-header {
    height: 70px;
    padding: 12px 20px;
    display: flex;
    align-items: center;
    gap: 12px;
    border-bottom: 1px solid #eee;
}

.chat-header .avatar {
    width: 40px;
    height: 40px;
    min-width: 40px;
}

.chat-header-name {
    font-size: 16px;
    font-weight: 700;
}

/*
|--------------------------------------------------------------------------
| EMPTY
|--------------------------------------------------------------------------
*/

.empty {
    flex: 1;
    display: flex;
    justify-content: center;
    align-items: center;
    color: #969ba7;
    text-align: center;
}

.empty-icon {
    font-size: 45px;
    margin-bottom: 10px;
}

/*
|--------------------------------------------------------------------------
| MESSAGES
|--------------------------------------------------------------------------
*/

.messages {
    flex: 1;
    overflow-y: auto;
    padding: 20px;
    background:
        linear-gradient(
            rgba(247,248,252,.94),
            rgba(247,248,252,.94)
        );
}

.message-row {
    display: flex;
    margin-bottom: 8px;
}

.message-row.me {
    justify-content: flex-end;
}

.bubble {
    max-width: min(70%, 520px);
    padding: 9px 12px;
    border-radius: 16px;
    background: white;
    box-shadow: 0 1px 3px rgba(0,0,0,.06);
    color: #222;
    font-size: 14px;
    line-height: 1.45;
    word-break: break-word;
}

.me .bubble {
    background: #667eea;
    color: white;
    border-bottom-right-radius: 5px;
}

.message-row:not(.me) .bubble {
    border-bottom-left-radius: 5px;
}

.message-time {
    display: block;
    margin-top: 4px;
    font-size: 10px;
    opacity: .55;
    text-align: right;
}

.chat-image {
    display: block;
    max-width: 280px;
    max-height: 320px;
    border-radius: 12px;
    object-fit: cover;
    margin-bottom: 5px;
    cursor: pointer;
}

/*
|--------------------------------------------------------------------------
| COMPOSER
|--------------------------------------------------------------------------
*/

.composer {
    padding: 12px 15px;
    border-top: 1px solid #eee;
    background: white;
}

.message-form {
    display: flex;
    align-items: flex-end;
    gap: 8px;
}

.attach {
    width: 42px;
    height: 42px;
    border: none;
    border-radius: 50%;
    background: #f0f2f6;
    cursor: pointer;
    font-size: 19px;
}

.attach:hover {
    background: #e7e9ef;
}

.message-input {
    flex: 1;
    resize: none;
    min-height: 42px;
    max-height: 120px;
    border: 1px solid #e1e4ea;
    border-radius: 21px;
    padding: 11px 16px;
    font-size: 14px;
    outline: none;
}

.message-input:focus {
    border-color: #667eea;
}

.send {
    width: 42px;
    height: 42px;
    border: none;
    border-radius: 50%;
    background: #667eea;
    color: white;
    font-size: 18px;
    cursor: pointer;
}

.send:disabled {
    opacity: .5;
}

/*
|--------------------------------------------------------------------------
| MOBILE
|--------------------------------------------------------------------------
*/

@media (max-width: 700px) {

    .sidebar {
        width: 100%;
        min-width: 100%;
    }

    .conversation {
        display: none;
    }

    .chat-app.chat-open .sidebar {
        display: none;
    }

    .chat-app.chat-open .conversation {
        display: flex;
    }

    .mobile-back {
        display: block !important;
    }

}

.mobile-back {
    display: none;
    border: none;
    background: none;
    font-size: 22px;
    cursor: pointer;
}

</style>

</head>

<body>

<div
    class="chat-app"
    id="chatApp"
>

    <!-- SIDEBAR -->

    <aside class="sidebar">

        <div class="sidebar-header">

            <h2>Messages</h2>

            <input
                type="search"
                class="search"
                id="search"
                placeholder="Rechercher un utilisateur..."
            >

        </div>

        <div
            class="users"
            id="users"
        ></div>

    </aside>


    <!-- CONVERSATION -->

    <section class="conversation">

        <header class="chat-header">

            <button
                class="mobile-back"
                id="backButton"
            >
                ←
            </button>

            <div
                class="avatar"
                id="headerAvatar"
            >
                ?
            </div>

            <div
                class="chat-header-name"
                id="headerName"
            >
                Sélectionnez une conversation
            </div>

        </header>


        <div
            class="empty"
            id="empty"
        >

            <div>

                <div class="empty-icon">
                    💬
                </div>

                <strong>
                    Vos messages
                </strong>

                <p>
                    Sélectionnez un utilisateur pour commencer.
                </p>

            </div>

        </div>


        <div
            class="messages"
            id="messages"
            style="display:none;"
        ></div>


        <div
            class="composer"
            id="composer"
            style="display:none;"
        >

            <form
                class="message-form"
                id="messageForm"
                enctype="multipart/form-data"
            >

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars($csrfToken) ?>"
                >

                <input
                    type="file"
                    id="imageInput"
                    name="image"
                    accept="image/jpeg,image/png,image/webp"
                    hidden
                >

                <button
                    type="button"
                    class="attach"
                    id="attach"
                    title="Photo"
                >
                    📷
                </button>

                <textarea
                    id="messageInput"
                    name="message"
                    class="message-input"
                    placeholder="Écrire un message..."
                    rows="1"
                    maxlength="5000"
                ></textarea>

                <button
                    type="submit"
                    class="send"
                    id="send"
                >
                    ➤
                </button>

            </form>

        </div>

    </section>

</div>


<script>

const csrfToken =
    <?= json_encode($csrfToken) ?>;

const currentUserId =
    <?= $currentUserId ?>;

let selectedUser = null;

const app =
    document.getElementById('chatApp');

const users =
    document.getElementById('users');

const search =
    document.getElementById('search');

const empty =
    document.getElementById('empty');

const messages =
    document.getElementById('messages');

const composer =
    document.getElementById('composer');

const headerName =
    document.getElementById('headerName');

const headerAvatar =
    document.getElementById('headerAvatar');

const form =
    document.getElementById('messageForm');

const input =
    document.getElementById('messageInput');

const imageInput =
    document.getElementById('imageInput');

const attach =
    document.getElementById('attach');

const send =
    document.getElementById('send');

const back =
    document.getElementById('backButton');


/*
|--------------------------------------------------------------------------
| ESCAPE
|--------------------------------------------------------------------------
*/

function escapeHtml(value) {

    const div =
        document.createElement('div');

    div.textContent =
        value ?? '';

    return div.innerHTML;
}


/*
|--------------------------------------------------------------------------
| LOAD USERS
|--------------------------------------------------------------------------
*/

async function loadUsers(query = '') {

    try {

        const response =
            await fetch(
                'chat.php?action=users&search=' +
                encodeURIComponent(query),
                {
                    cache: 'no-store'
                }
            );

        const data =
            await response.json();

        if (!data.success) {
            throw new Error(data.error);
        }

        users.innerHTML = '';

        data.users.forEach(user => {

            const element =
                document.createElement('div');

            element.className =
                'user' +
                (
                    selectedUser &&
                    selectedUser.id === user.id
                        ? ' active'
                        : ''
                );

            element.dataset.id =
                user.id;

            element.innerHTML = `
                <div class="avatar">
                    ${escapeHtml(user.initials)}
                </div>

                <div class="user-name">
                    ${escapeHtml(user.username)}
                </div>
            `;

            element.addEventListener(
                'click',
                () => selectUser(user)
            );

            users.appendChild(element);

        });

    } catch (error) {

        console.error(error);

        users.innerHTML = `
            <div style="
                padding:20px;
                color:#d9534f;
                font-size:13px;
            ">
                Impossible de charger les utilisateurs.
            </div>
        `;
    }
}


/*
|--------------------------------------------------------------------------
| SELECT USER
|--------------------------------------------------------------------------
*/

function selectUser(user) {

    selectedUser = user;

    app.classList.add('chat-open');

    empty.style.display =
        'none';

    messages.style.display =
        'block';

    composer.style.display =
        'block';

    headerName.textContent =
        user.username;

    headerAvatar.textContent =
        user.initials;

    loadMessages();

    loadUsers(search.value);

    input.focus();
}


/*
|--------------------------------------------------------------------------
| LOAD MESSAGES
|--------------------------------------------------------------------------
*/

async function loadMessages() {

    if (!selectedUser) {
        return;
    }

    try {

        const response =
            await fetch(
                'chat.php?action=messages&user_id=' +
                selectedUser.id,
                {
                    cache: 'no-store'
                }
            );

        const data =
            await response.json();

        if (!data.success) {
            throw new Error(data.error);
        }

        const wasBottom =
            messages.scrollHeight -
            messages.scrollTop -
            messages.clientHeight < 100;

        messages.innerHTML = '';

        data.messages.forEach(message => {

            const row =
                document.createElement('div');

            const mine =
                Number(message.sender_id) ===
                Number(currentUserId);

            row.className =
                'message-row' +
                (mine ? ' me' : '');

            let content = '';

            if (message.image_path) {

                content += `
                    <img
                        class="chat-image"
                        src="${escapeHtml(message.image_path)}"
                        alt="Photo"
                        onclick="window.open(
                            this.src,
                            '_blank'
                        )"
                    >
                `;
            }

            if (message.message) {

                content += `
                    <div>
                        ${escapeHtml(message.message)}
                    </div>
                `;
            }

            const time =
                message.created_at
                    ? new Date(
                        message.created_at.replace(' ', 'T')
                    ).toLocaleTimeString(
                        'fr-FR',
                        {
                            hour: '2-digit',
                            minute: '2-digit'
                        }
                    )
                    : '';

            row.innerHTML = `
                <div class="bubble">
                    ${content}
                    <span class="message-time">
                        ${time}
                    </span>
                </div>
            `;

            messages.appendChild(row);
        });

        if (wasBottom) {

            messages.scrollTop =
                messages.scrollHeight;
        }

    } catch (error) {

        console.error(
            'Erreur messages:',
            error
        );
    }
}


/*
|--------------------------------------------------------------------------
| SEND
|--------------------------------------------------------------------------
*/

form.addEventListener(
    'submit',
    async function(event) {

        event.preventDefault();

        if (!selectedUser) {
            return;
        }

        const text =
            input.value.trim();

        const hasImage =
            imageInput.files.length > 0;

        if (!text && !hasImage) {
            return;
        }

        send.disabled = true;

        const formData =
            new FormData(form);

        formData.append(
            'action',
            'send'
        );

        formData.append(
            'receiver_id',
            selectedUser.id
        );

        try {

            const response =
                await fetch(
                    'chat.php',
                    {
                        method: 'POST',
                        body: formData
                    }
                );

            const data =
                await response.json();

            if (!data.success) {

                alert(
                    data.error ||
                    'Impossible d’envoyer le message.'
                );

                return;
            }

            input.value = '';

            imageInput.value = '';

            input.style.height =
                '42px';

            await loadMessages();

            messages.scrollTop =
                messages.scrollHeight;

        } catch (error) {

            console.error(error);

            alert(
                'Erreur de communication avec le serveur.'
            );

        } finally {

            send.disabled = false;

            input.focus();
        }
    }
);


/*
|--------------------------------------------------------------------------
| PHOTO
|--------------------------------------------------------------------------
*/

attach.addEventListener(
    'click',
    () => imageInput.click()
);

imageInput.addEventListener(
    'change',
    () => {

        if (imageInput.files.length) {

            input.placeholder =
                'Photo sélectionnée — ajoutez un message...';

            input.focus();
        }
    }
);


/*
|--------------------------------------------------------------------------
| AUTO RESIZE
|--------------------------------------------------------------------------
*/

input.addEventListener(
    'input',
    function() {

        this.style.height =
            '42px';

        this.style.height =
            Math.min(
                this.scrollHeight,
                120
            ) + 'px';
    }
);


/*
|--------------------------------------------------------------------------
| ENTER
|--------------------------------------------------------------------------
*/

input.addEventListener(
    'keydown',
    function(event) {

        if (
            event.key === 'Enter' &&
            !event.shiftKey
        ) {

            event.preventDefault();

            form.requestSubmit();
        }
    }
);


/*
|--------------------------------------------------------------------------
| SEARCH
|--------------------------------------------------------------------------
*/

let searchTimer;

search.addEventListener(
    'input',
    function() {

        clearTimeout(searchTimer);

        searchTimer =
            setTimeout(
                () => loadUsers(this.value),
                250
            );
    }
);


/*
|--------------------------------------------------------------------------
| MOBILE BACK
|--------------------------------------------------------------------------
*/

back.addEventListener(
    'click',
    function() {

        app.classList.remove(
            'chat-open'
        );
    }
);


/*
|--------------------------------------------------------------------------
| INITIAL
|--------------------------------------------------------------------------
*/

loadUsers();


/*
|--------------------------------------------------------------------------
| AUTO REFRESH
|--------------------------------------------------------------------------
*/

setInterval(
    () => {

        if (selectedUser) {
            loadMessages();
        }

    },
    3000
);

</script>

</body>
</html>
