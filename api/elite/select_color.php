<?php

session_start();

require_once '../../config/database.php';

header('Content-Type: application/json; charset=utf-8');

function responseJson(
    bool $success,
    string $message,
    array $data = [],
    int $status = 200
): void {

    http_response_code($status);

    echo json_encode(
        array_merge([
            'success' => $success,
            'message' => $message,
        ], $data),
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| AUTHENTIFICATION
|--------------------------------------------------------------------------
*/

if (!function_exists('isLoggedIn') || !isLoggedIn()) {

    responseJson(
        false,
        'Vous devez être connecté.',
        [],
        401
    );
}


$user = getCurrentUser();

if (!$user) {

    responseJson(
        false,
        'Session utilisateur invalide.',
        [],
        401
    );
}

$userId = (int) $user['id'];


/*
|--------------------------------------------------------------------------
| MÉTHODE
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    responseJson(
        false,
        'Méthode non autorisée.',
        [],
        405
    );
}


/*
|--------------------------------------------------------------------------
| CONFIGURATION
|--------------------------------------------------------------------------
*/

$config = require '../../config/elite_game.php';

$colors = $config['colors'] ?? [];

$allowedColors = [];

foreach ($colors as $color) {

    if (isset($color['id'])) {
        $allowedColors[] = $color['id'];
    }
}


$color = trim(
    $_POST['color']
    ?? ''
);


if (
    $color === ''
    ||
    !in_array($color, $allowedColors, true)
) {

    responseJson(
        false,
        'Couleur invalide.',
        [],
        422
    );
}


/*
|--------------------------------------------------------------------------
| HEURE DU JEU
|--------------------------------------------------------------------------
*/

$timezone = new DateTimeZone(
    $config['timezone'] ?? 'UTC'
);

$now = new DateTimeImmutable(
    'now',
    $timezone
);


/*
|--------------------------------------------------------------------------
| TROUVER LA SÉANCE
|--------------------------------------------------------------------------
*/

$sessionStmt = $pdo->prepare("
    SELECT *
    FROM elite_game_sessions
    WHERE status = 'open'
    AND scheduled_at > ?
    ORDER BY scheduled_at ASC
    LIMIT 1
");

$sessionStmt->execute([
    $now->format('Y-m-d H:i:s')
]);

$gameSession = $sessionStmt->fetch(
    PDO::FETCH_ASSOC
);


/*
|--------------------------------------------------------------------------
| CRÉER LA PROCHAINE SÉANCE SI NÉCESSAIRE
|--------------------------------------------------------------------------
*/

if (!$gameSession) {

    $next = $now;

    $daysUntilWednesday =
        (
            3 -
            (int)$next->format('N') +
            7
        ) % 7;

    if (
        $daysUntilWednesday === 0
        &&
        (
            (int)$next->format('H') > 19
            ||
            (
                (int)$next->format('H') === 19
                &&
                (int)$next->format('i') >= 0
            )
        )
    ) {

        $daysUntilWednesday = 7;
    }

    $next = $next
        ->modify("+{$daysUntilWednesday} days")
        ->setTime(19, 0, 0);

    $sessionKey =
        $next->format('Ymd_His');


    $insertSession = $pdo->prepare("
        INSERT IGNORE INTO elite_game_sessions
        (
            session_key,
            scheduled_at,
            prize,
            status
        )
        VALUES (?, ?, ?, 'open')
    ");

    $insertSession->execute([
        $sessionKey,
        $next->format('Y-m-d H:i:s'),
        $config['prize'] ?? 200000,
    ]);


    $sessionStmt = $pdo->prepare("
        SELECT *
        FROM elite_game_sessions
        WHERE session_key = ?
        LIMIT 1
    ");

    $sessionStmt->execute([
        $sessionKey
    ]);

    $gameSession =
        $sessionStmt->fetch(PDO::FETCH_ASSOC);
}


if (!$gameSession) {

    responseJson(
        false,
        'Impossible de préparer la séance.',
        [],
        500
    );
}


$sessionId =
    (int)$gameSession['id'];


/*
|--------------------------------------------------------------------------
| VÉRIFIER SI L'UTILISATEUR A DÉJÀ JOUÉ
|--------------------------------------------------------------------------
*/

$existing = $pdo->prepare("
    SELECT id, color
    FROM elite_game_entries
    WHERE session_id = ?
    AND user_id = ?
    LIMIT 1
");

$existing->execute([
    $sessionId,
    $userId
]);

$alreadyPlayed =
    $existing->fetch(PDO::FETCH_ASSOC);


if ($alreadyPlayed) {

    responseJson(
        false,
        'Vous avez déjà choisi une couleur pour cette séance.',
        [
            'color' =>
                $alreadyPlayed['color']
        ],
        409
    );
}


/*
|--------------------------------------------------------------------------
| ENREGISTRER LE CHOIX
|--------------------------------------------------------------------------
*/

try {

    $stmt = $pdo->prepare("
        INSERT INTO elite_game_entries
        (
            session_id,
            user_id,
            color
        )
        VALUES (?, ?, ?)
    ");

    $stmt->execute([
        $sessionId,
        $userId,
        $color
    ]);

} catch (PDOException $e) {

    /*
     * La contrainte UNIQUE protège également
     * contre les doubles clics simultanés.
     */

    if ((int)$e->errorInfo[1] === 1062) {

        responseJson(
            false,
            'Votre participation est déjà enregistrée.',
            [],
            409
        );
    }

    responseJson(
        false,
        'Impossible d’enregistrer votre participation.',
        [],
        500
    );
}


responseJson(
    true,
    'Votre couleur a bien été enregistrée.',
    [
        'session_id' => $sessionId,
        'color' => $color,
        'scheduled_at' =>
            $gameSession['scheduled_at'],
    ]
);
