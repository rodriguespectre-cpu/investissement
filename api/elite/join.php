<?php

session_start();

require_once '../../config/database.php';

header(
    'Content-Type: application/json; charset=utf-8'
);


function responseJson(
    bool $success,
    string $message,
    array $data = [],
    int $status = 200
): void {

    http_response_code($status);

    echo json_encode(
        array_merge(
            [
                'success' => $success,
                'message' => $message
            ],
            $data
        ),
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| AUTH
|--------------------------------------------------------------------------
*/

if (
    !function_exists('isLoggedIn')
    ||
    !isLoggedIn()
) {

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


$userId =
    (int) $user['id'];


/*
|--------------------------------------------------------------------------
| MÉTHODE
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD']
    !== 'POST'
) {

    responseJson(
        false,
        'Méthode non autorisée.',
        [],
        405
    );

}


/*
|--------------------------------------------------------------------------
| DONNÉES
|--------------------------------------------------------------------------
*/

$roundId =
    (int) (
        $_POST['round_id']
        ?? 0
    );


$colorId =
    trim(
        $_POST['color_id']
        ?? ''
    );


$colorName =
    trim(
        $_POST['color_name']
        ?? ''
    );


if (
    $roundId <= 0
    ||
    $colorId === ''
    ||
    $colorName === ''
) {

    responseJson(
        false,
        'Données de participation invalides.',
        [],
        422
    );

}


/*
|--------------------------------------------------------------------------
| COULEURS AUTORISÉES
|--------------------------------------------------------------------------
*/

$allowedColors = [

    'red' => 'Rouge',
    'blue' => 'Bleu',
    'green' => 'Vert',
    'yellow' => 'Jaune',
    'purple' => 'Violet',
    'orange' => 'Orange'

];


if (
    !isset(
        $allowedColors[$colorId]
    )
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
| LE NOM VIENT DU SERVEUR
|--------------------------------------------------------------------------
*/

$colorName =
    $allowedColors[$colorId];


/*
|--------------------------------------------------------------------------
| RÉCUPÉRER LA SÉANCE
|--------------------------------------------------------------------------
*/

try {

    $stmt = $pdo->prepare("
        SELECT *
        FROM elite_game_rounds
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([
        $roundId
    ]);

    $round =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );


    if (!$round) {

        responseJson(
            false,
            'Cette séance n’existe pas.',
            [],
            404
        );

    }


} catch (Throwable $e) {

    responseJson(
        false,
        'Impossible de vérifier la séance.',
        [],
        500
    );

}


/*
|--------------------------------------------------------------------------
| VÉRIFICATION HEURE
|--------------------------------------------------------------------------
*/

$now =
    new DateTimeImmutable(
        'now',
        new DateTimeZone('UTC')
    );


$scheduledAt =
    new DateTimeImmutable(
        $round['scheduled_at'],
        new DateTimeZone('UTC')
    );


/*
|--------------------------------------------------------------------------
| LES PARTICIPATIONS SONT FERMÉES
| À L'HEURE DE LA SÉANCE
|--------------------------------------------------------------------------
*/

if ($now >= $scheduledAt) {

    responseJson(
        false,
        'Les participations pour cette séance sont maintenant fermées.',
        [],
        422
    );

}


/*
|--------------------------------------------------------------------------
| VÉRIFIER STATUT
|--------------------------------------------------------------------------
*/

if (
    $round['status']
    !== 'scheduled'
) {

    responseJson(
        false,
        'Cette séance n’accepte plus de participation.',
        [],
        422
    );

}


/*
|--------------------------------------------------------------------------
| INSERTION ATOMIQUE
|--------------------------------------------------------------------------
*/

try {

    $stmt = $pdo->prepare("
        INSERT INTO elite_game_entries
        (
            round_id,
            user_id,
            color_id,
            color_name
        )
        VALUES
        (
            ?,
            ?,
            ?,
            ?
        )
    ");

    $stmt->execute([

        $roundId,

        $userId,

        $colorId,

        $colorName

    ]);


} catch (PDOException $e) {

    /*
    |--------------------------------------------------------------------------
    | DUPLICATE
    |--------------------------------------------------------------------------
    */

    if (
        (int) $e->errorInfo[1]
        === 1062
    ) {

        responseJson(
            false,
            'Vous avez déjà participé à cette séance.',
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


/*
|--------------------------------------------------------------------------
| NOTIFICATION
|--------------------------------------------------------------------------
*/

try {

    $stmt = $pdo->prepare("
        INSERT INTO notifications
        (
            user_id,
            type,
            title,
            message,
            link
        )
        VALUES
        (
            ?,
            'info',
            ?,
            ?,
            ?
        )
    ");


    $stmt->execute([

        $userId,

        'Elite Money',

        'Votre participation à la séance Elite Money a été enregistrée. Couleur choisie : '
        . $colorName
        . '.',

        'elite-game.php'

    ]);

} catch (Throwable $e) {

    /*
    |--------------------------------------------------------------------------
    | Une erreur de notification ne doit pas
    | annuler la participation.
    |--------------------------------------------------------------------------
    */

}


responseJson(
    true,
    'Participation enregistrée avec succès.',
    [
        'round_id' => $roundId,
        'color_id' => $colorId,
        'color_name' => $colorName
    ]
);
