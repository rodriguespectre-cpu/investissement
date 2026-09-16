<?php

/*
|--------------------------------------------------------------------------
| ELITE MONEY — MOTEUR DU TIRAGE
|--------------------------------------------------------------------------
|
| Exécution prévue chaque mercredi à 19:00 UTC.
|
*/

require_once __DIR__ . '/config/database.php';

$config = require __DIR__ . '/config/elite_game.php';

$timezone = new DateTimeZone(
    $config['timezone'] ?? 'UTC'
);

$now = new DateTimeImmutable(
    'now',
    $timezone
);


/*
|--------------------------------------------------------------------------
| VERROU GLOBAL
|--------------------------------------------------------------------------
|
| Empêche deux processus de lancer simultanément le même tirage.
|
*/

$lockStmt = $pdo->query("
    SELECT GET_LOCK('investpro_elite_game_draw', 1)
");

$lock = $lockStmt->fetchColumn();

if ((int)$lock !== 1) {

    exit(
        "Une autre opération de tirage est déjà en cours.\n"
    );
}


try {

    /*
     * Chercher une séance arrivée à échéance.
     */

    $stmt = $pdo->prepare("
        SELECT *
        FROM elite_game_sessions
        WHERE status = 'open'
        AND scheduled_at <= ?
        ORDER BY scheduled_at ASC
        LIMIT 1
        FOR UPDATE
    ");


    $pdo->beginTransaction();


    $stmt->execute([
        $now->format('Y-m-d H:i:s')
    ]);


    $session =
        $stmt->fetch(PDO::FETCH_ASSOC);


    if (!$session) {

        $pdo->commit();

        $pdo->query("
            SELECT RELEASE_LOCK(
                'investpro_elite_game_draw'
            )
        ");

        exit(
            "Aucune séance à tirer.\n"
        );
    }


    $sessionId =
        (int)$session['id'];

    $prize =
        (float)$session['prize'];


    /*
    |--------------------------------------------------------------------------
    | TIRAGE DE LA COULEUR
    |--------------------------------------------------------------------------
    */

    $colors = $config['colors'] ?? [];

    if (!$colors) {

        throw new RuntimeException(
            'Aucune couleur configurée.'
        );
    }


    $colorIds = [];

    foreach ($colors as $color) {

        if (isset($color['id'])) {
            $colorIds[] = $color['id'];
        }
    }


    $winningColor =
        $colorIds[
            random_int(
                0,
                count($colorIds) - 1
            )
        ];


    /*
    |--------------------------------------------------------------------------
    | CHERCHER LES JOUEURS DE LA COULEUR
    |--------------------------------------------------------------------------
    */

    $playersStmt = $pdo->prepare("
        SELECT user_id
        FROM elite_game_entries
        WHERE session_id = ?
        AND color = ?
    ");

    $playersStmt->execute([
        $sessionId,
        $winningColor
    ]);


    $players =
        $playersStmt->fetchAll(
            PDO::FETCH_COLUMN
        );


    /*
    |--------------------------------------------------------------------------
    | PERSONNE N'A CHOISI LA BONNE COULEUR
    |--------------------------------------------------------------------------
    */

    if (!$players) {

        $update = $pdo->prepare("
            UPDATE elite_game_sessions
            SET
                winning_color = ?,
                status = 'no_winner',
                drawn_at = ?
            WHERE id = ?
            AND status = 'open'
        ");

        $update->execute([
            $winningColor,
            $now->format('Y-m-d H:i:s'),
            $sessionId
        ]);


        /*
         * Préparer la prochaine séance.
         */

        createNextEliteSession(
            $pdo,
            $now,
            $config
        );


        $pdo->commit();


        echo
            "Séance #{$sessionId} : aucun gagnant.\n" .
            "Couleur gagnante : {$winningColor}\n";


        $pdo->query("
            SELECT RELEASE_LOCK(
                'investpro_elite_game_draw'
            )
        ");

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | TIRAGE DU GAGNANT
    |--------------------------------------------------------------------------
    */

    $winnerIndex =
        random_int(
            0,
            count($players) - 1
        );


    $winnerId =
        (int)$players[$winnerIndex];


    /*
    |--------------------------------------------------------------------------
    | RÉFÉRENCE UNIQUE
    |--------------------------------------------------------------------------
    */

    $reference =
        'ELITE-' .
        $session['session_key'] .
        '-' .
        strtoupper(
            bin2hex(
                random_bytes(4)
            )
        );


    /*
    |--------------------------------------------------------------------------
    | CRÉDIT DU GAGNANT
    |--------------------------------------------------------------------------
    */

    $credit = $pdo->prepare("
        UPDATE users
        SET balance = balance + ?
        WHERE id = ?
    ");

    $credit->execute([
        $prize,
        $winnerId
    ]);


    /*
    |--------------------------------------------------------------------------
    | BALANCE LOG
    |--------------------------------------------------------------------------
    */

    $balanceStmt = $pdo->prepare("
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
            'bonus',
            ?,
            ?
        )
    ");


    $balanceStmt->execute([

        $winnerId,

        $prize,

        $sessionId,

        'Gain Elite Money - séance ' .
        $session['session_key']

    ]);


    /*
    |--------------------------------------------------------------------------
    | TRANSACTION
    |--------------------------------------------------------------------------
    */

    $transactionStmt = $pdo->prepare("
        INSERT INTO transactions
        (
            user_id,
            type,
            amount,
            fee,
            status,
            payment_method,
            reference,
            description,
            completed_at
        )
        VALUES
        (
            ?,
            'bonus',
            ?,
            0.00,
            'completed',
            'Elite Money',
            ?,
            ?,
            ?
        )
    ");


    $transactionStmt->execute([

        $winnerId,

        $prize,

        $reference,

        'Gain Elite Money de ' .
        number_format(
            $prize,
            0,
            ',',
            ' '
        ) .
        ' XAF',

        $now->format('Y-m-d H:i:s')

    ]);


    /*
    |--------------------------------------------------------------------------
    | ID TRANSACTION
    |--------------------------------------------------------------------------
    */

    $transactionId =
        (int)$pdo->lastInsertId();


    /*
    |--------------------------------------------------------------------------
    | NOTIFICATION GAGNANT
    |--------------------------------------------------------------------------
    */

    $notification = $pdo->prepare("
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
            'success',
            ?,
            ?,
            ?
        )
    ");


    $notification->execute([

        $winnerId,

        '🎉 Félicitations ! Vous avez gagné !',

        'Vous êtes le grand gagnant de la séance Elite Money. ' .
        'Votre gain de ' .
        number_format(
            $prize,
            0,
            ',',
            ' '
        ) .
        ' FCFA a été crédité sur votre solde. Référence : ' .
        $reference,

        'elite.php'

    ]);


    /*
    |--------------------------------------------------------------------------
    | NOTIFICATIONS DES AUTRES PARTICIPANTS
    |--------------------------------------------------------------------------
    */

    $allPlayersStmt = $pdo->prepare("
        SELECT DISTINCT user_id
        FROM elite_game_entries
        WHERE session_id = ?
        AND user_id <> ?
    ");

    $allPlayersStmt->execute([
        $sessionId,
        $winnerId
    ]);


    $otherPlayers =
        $allPlayersStmt->fetchAll(
            PDO::FETCH_COLUMN
        );


    $loserNotification = $pdo->prepare("
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


    foreach ($otherPlayers as $playerId) {

        $loserNotification->execute([

            (int)$playerId,

            'Elite Money — Résultat',

            'Dommage ! Vous n’avez pas remporté cette séance. ' .
            'La prochaine séance aura lieu mercredi prochain à 19h GMT. Bonne chance !',

            'elite.php'

        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | TERMINER LA SÉANCE
    |--------------------------------------------------------------------------
    */

    $update = $pdo->prepare("
        UPDATE elite_game_sessions
        SET
            winning_color = ?,
            winner_user_id = ?,
            status = 'drawn',
            drawn_at = ?
        WHERE id = ?
        AND status = 'open'
    ");


    $update->execute([

        $winningColor,

        $winnerId,

        $now->format('Y-m-d H:i:s'),

        $sessionId

    ]);


    /*
    |--------------------------------------------------------------------------
    | PROCHAINE SÉANCE
    |--------------------------------------------------------------------------
    */

    createNextEliteSession(
        $pdo,
        $now,
        $config
    );


    $pdo->commit();


    echo
        "========================================\n" .
        "ELITE MONEY\n" .
        "========================================\n" .
        "Séance       : {$session['session_key']}\n" .
        "Couleur      : {$winningColor}\n" .
        "Participants : " . count($players) . "\n" .
        "Gagnant ID   : {$winnerId}\n" .
        "Gain         : {$prize} XAF\n" .
        "Référence    : {$reference}\n" .
        "========================================\n";


} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }


    error_log(
        '[ELITE MONEY] ' .
        $e->getMessage()
    );


    echo
        "ERREUR ELITE MONEY : " .
        $e->getMessage() .
        "\n";


} finally {

    $pdo->query("
        SELECT RELEASE_LOCK(
            'investpro_elite_game_draw'
        )
    ");
}


/*
|--------------------------------------------------------------------------
| PROCHAINE SÉANCE
|--------------------------------------------------------------------------
*/

function createNextEliteSession(
    PDO $pdo,
    DateTimeImmutable $now,
    array $config
): void {

    $timezone = new DateTimeZone(
        $config['timezone'] ?? 'UTC'
    );


    $next = $now
        ->setTimezone($timezone);


    $days =
        (
            3 -
            (int)$next->format('N') +
            7
        ) % 7;


    if ($days === 0) {

        if (
            (int)$next->format('H') > 19
            ||
            (
                (int)$next->format('H') === 19
                &&
                (int)$next->format('i') >= 0
            )
        ) {

            $days = 7;
        }
    }


    $next = $next
        ->modify("+{$days} days")
        ->setTime(19, 0, 0);


    $sessionKey =
        $next->format('Ymd_His');


    $stmt = $pdo->prepare("
        INSERT IGNORE INTO elite_game_sessions
        (
            session_key,
            scheduled_at,
            prize,
            status
        )
        VALUES (?, ?, ?, 'open')
    ");


    $stmt->execute([

        $sessionKey,

        $next->format(
            'Y-m-d H:i:s'
        ),

        $config['prize'] ?? 200000

    ]);
}
