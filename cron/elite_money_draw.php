<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

$config = require __DIR__ . '/../config/elite_game.php';

date_default_timezone_set('UTC');

$prizeAmount = (float) $config['prize_amount'];

$colors = $config['colors'];

if (!$colors) {
    exit("Aucune couleur configurée.\n");
}


/*
|--------------------------------------------------------------------------
| TROUVER LES SÉANCES À EXÉCUTER
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT *
    FROM elite_game_sessions
    WHERE status = 'open'
      AND scheduled_at <= UTC_TIMESTAMP()
    ORDER BY scheduled_at ASC
");

$stmt->execute();

$sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);


if (!$sessions) {
    exit("Aucune séance à exécuter.\n");
}


/*
|--------------------------------------------------------------------------
| TRAITER CHAQUE SÉANCE
|--------------------------------------------------------------------------
*/

foreach ($sessions as $session) {

    $sessionId = (int) $session['id'];

    echo "Séance #{$sessionId}\n";

    try {

        $pdo->beginTransaction();


        /*
        |--------------------------------------------------------------------------
        | VERROUILLER LA SÉANCE
        |--------------------------------------------------------------------------
        */

        $lock = $pdo->prepare("
            SELECT *
            FROM elite_game_sessions
            WHERE id = ?
            FOR UPDATE
        ");

        $lock->execute([$sessionId]);

        $lockedSession = $lock->fetch(PDO::FETCH_ASSOC);

        if (
            !$lockedSession ||
            $lockedSession['status'] !== 'open'
        ) {

            $pdo->rollBack();

            continue;
        }


        /*
        |--------------------------------------------------------------------------
        | RÉCUPÉRER LES PARTICIPANTS
        |--------------------------------------------------------------------------
        */

        $entriesStmt = $pdo->prepare("
            SELECT
                e.id,
                e.user_id,
                e.color
            FROM elite_game_entries e
            INNER JOIN elite_memberships em
                ON em.user_id = e.user_id
            WHERE e.session_id = ?
              AND em.status = 'active'
        ");

        $entriesStmt->execute([$sessionId]);

        $entries = $entriesStmt->fetchAll(PDO::FETCH_ASSOC);


        $participantsCount = count($entries);


        /*
        |--------------------------------------------------------------------------
        | TIRAGE DE LA COULEUR
        |--------------------------------------------------------------------------
        */

        $winningColor = $colors[
            random_int(
                0,
                count($colors) - 1
            )
        ];


        /*
        |--------------------------------------------------------------------------
        | PARTICIPANTS DE LA COULEUR GAGNANTE
        |--------------------------------------------------------------------------
        */

        $eligible = array_values(
            array_filter(
                $entries,
                static function ($entry) use ($winningColor) {

                    return $entry['color'] === $winningColor;

                }
            )
        );


        $eligibleCount = count($eligible);


        /*
        |--------------------------------------------------------------------------
        | AUCUN PARTICIPANT
        |--------------------------------------------------------------------------
        */

        if ($eligibleCount === 0) {

            $update = $pdo->prepare("
                UPDATE elite_game_sessions
                SET
                    winning_color = ?,
                    winner_user_id = NULL,
                    prize_amount = ?,
                    status = 'no_winner',
                    participants_count = ?,
                    eligible_count = ?,
                    completed_at = UTC_TIMESTAMP()
                WHERE id = ?
            ");

            $update->execute([
                $winningColor,
                $prizeAmount,
                $participantsCount,
                0,
                $sessionId
            ]);


            $pdo->commit();

            echo "Couleur : {$winningColor}\n";
            echo "Aucun participant éligible.\n";

            continue;
        }


        /*
        |--------------------------------------------------------------------------
        | SECOND TIRAGE
        |--------------------------------------------------------------------------
        */

        $winnerIndex = random_int(
            0,
            $eligibleCount - 1
        );

        $winner = $eligible[$winnerIndex];

        $winnerUserId = (int) $winner['user_id'];


        /*
        |--------------------------------------------------------------------------
        | VERROUILLER L'UTILISATEUR
        |--------------------------------------------------------------------------
        */

        $userStmt = $pdo->prepare("
            SELECT id, username, balance
            FROM users
            WHERE id = ?
            FOR UPDATE
        ");

        $userStmt->execute([
            $winnerUserId
        ]);

        $winnerUser = $userStmt->fetch(PDO::FETCH_ASSOC);


        if (!$winnerUser) {

            throw new RuntimeException(
                'Utilisateur gagnant introuvable.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | CRÉDIT DU GAIN
        |--------------------------------------------------------------------------
        */

        $newBalance =
            (float) $winnerUser['balance']
            + $prizeAmount;


        $balanceUpdate = $pdo->prepare("
            UPDATE users
            SET balance = ?
            WHERE id = ?
        ");

        $balanceUpdate->execute([
            $newBalance,
            $winnerUserId
        ]);


        /*
        |--------------------------------------------------------------------------
        | HISTORIQUE BALANCE
        |--------------------------------------------------------------------------
        */

        $balanceInsert = $pdo->prepare("
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

        $balanceInsert->execute([

            $winnerUserId,

            $prizeAmount,

            $sessionId,

            'Gain Elite Money - séance #' .
            $sessionId

        ]);


        /*
        |--------------------------------------------------------------------------
        | TRANSACTION
        |--------------------------------------------------------------------------
        */

        $reference =
            'ELITE-' .
            date('YmdHis') .
            '-' .
            strtoupper(
                bin2hex(
                    random_bytes(4)
                )
            );


        $transaction = $pdo->prepare("
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
                UTC_TIMESTAMP()
            )
        ");

        $transaction->execute([

            $winnerUserId,

            $prizeAmount,

            $reference,

            'Gain Elite Money - ' .
            number_format(
                $prizeAmount,
                0,
                ',',
                ' '
            ) .
            ' XAF'

        ]);


        /*
        |--------------------------------------------------------------------------
        | FINALISER LA SÉANCE
        |--------------------------------------------------------------------------
        */

        $update = $pdo->prepare("
            UPDATE elite_game_sessions
            SET
                winning_color = ?,
                winner_user_id = ?,
                prize_amount = ?,
                status = 'completed',
                participants_count = ?,
                eligible_count = ?,
                completed_at = UTC_TIMESTAMP()
            WHERE id = ?
        ");

        $update->execute([

            $winningColor,

            $winnerUserId,

            $prizeAmount,

            $participantsCount,

            $eligibleCount,

            $sessionId

        ]);


        $pdo->commit();


        echo "Couleur gagnante : {$winningColor}\n";

        echo "Gagnant : {$winnerUser['username']}\n";

        echo "Gain : {$prizeAmount} XAF\n";

    } catch (Throwable $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        echo "ERREUR séance #{$sessionId}: ";
        echo $e->getMessage();
        echo PHP_EOL;
    }
}
