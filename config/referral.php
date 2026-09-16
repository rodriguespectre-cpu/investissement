<?php

/**
 * ============================================================================
 * INVESTPRO — SYSTÈME DE PARRAINAGE
 * ============================================================================
 *
 * Commission :
 *   100 FCFA par filleul vérifié.
 *
 * Condition de retrait :
 *   L'utilisateur doit avoir au moins UN filleul ayant souscrit
 *   à l'abonnement Elite et dont le statut est "active".
 *
 * Le bonus est crédité dans :
 *   users.bonus_balance
 *
 * Les commissions sont également enregistrées dans :
 *   transactions.type = bonus
 * ============================================================================
 */


/**
 * ============================================================================
 * RÉCOMPENSER LE PARRAIN
 * ============================================================================
 *
 * Appeler cette fonction après la vérification email du nouveau compte.
 *
 * Exemple :
 *
 * rewardReferral($pdo, $newUserId);
 *
 */
function rewardReferral(
    PDO $pdo,
    int $newUserId
): bool {

    $reward = 100.00;

    try {

        $pdo->beginTransaction();


        /*
        |--------------------------------------------------------------------------
        | VERROUILLER LE NOUVEL UTILISATEUR
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            SELECT
                id,
                referred_by,
                referral_rewarded,
                email_verified
            FROM users
            WHERE id = ?
            FOR UPDATE
        ");

        $stmt->execute([
            $newUserId
        ]);

        $newUser = $stmt->fetch(PDO::FETCH_ASSOC);


        if (!$newUser) {

            $pdo->rollBack();

            return false;
        }


        /*
        |--------------------------------------------------------------------------
        | CONDITIONS
        |--------------------------------------------------------------------------
        */

        if (
            empty($newUser['referred_by'])
            ||
            (int)$newUser['referral_rewarded'] === 1
            ||
            (int)$newUser['email_verified'] !== 1
        ) {

            $pdo->rollBack();

            return false;
        }


        $referrerId = (int)$newUser['referred_by'];


        /*
        |--------------------------------------------------------------------------
        | EMPÊCHER L'AUTO-PARRAINAGE
        |--------------------------------------------------------------------------
        */

        if ($referrerId === $newUserId) {

            $pdo->rollBack();

            return false;
        }


        /*
        |--------------------------------------------------------------------------
        | VÉRIFIER LE PARRAIN
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            SELECT id
            FROM users
            WHERE id = ?
            FOR UPDATE
        ");

        $stmt->execute([
            $referrerId
        ]);

        if (!$stmt->fetchColumn()) {

            $pdo->rollBack();

            return false;
        }


        /*
        |--------------------------------------------------------------------------
        | MARQUER LE FILLEUL COMME RÉCOMPENSÉ
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            UPDATE users
            SET referral_rewarded = 1
            WHERE id = ?
            AND referral_rewarded = 0
        ");

        $stmt->execute([
            $newUserId
        ]);


        if ($stmt->rowCount() !== 1) {

            $pdo->rollBack();

            return false;
        }


        /*
        |--------------------------------------------------------------------------
        | CRÉDITER LA COMMISSION
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            UPDATE users
            SET bonus_balance = bonus_balance + ?
            WHERE id = ?
        ");

        $stmt->execute([
            $reward,
            $referrerId
        ]);


        /*
        |--------------------------------------------------------------------------
        | TRANSACTION BONUS
        |--------------------------------------------------------------------------
        */

        $reference =
            'REF-' .
            $newUserId .
            '-' .
            $referrerId;


        $description =
            'Commission de parrainage — inscription de l’utilisateur #' .
            $newUserId;


        $stmt = $pdo->prepare("
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
                'Referral',
                ?,
                ?,
                NOW()
            )
        ");

        $stmt->execute([

            $referrerId,

            $reward,

            $reference,

            $description

        ]);


        /*
        |--------------------------------------------------------------------------
        | NOTIFICATION
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
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
                'success',
                ?,
                ?,
                0,
                ?
            )
        ");

        $stmt->execute([

            $referrerId,

            'Nouvelle commission',

            'Félicitations ! Vous venez de recevoir 100 FCFA pour un nouveau filleul.',

            'tasks.php'

        ]);


        /*
        |--------------------------------------------------------------------------
        | FIN
        |--------------------------------------------------------------------------
        */

        $pdo->commit();

        return true;


    } catch (Throwable $e) {

        if ($pdo->inTransaction()) {

            $pdo->rollBack();

        }

        return false;
    }
}


/**
 * ============================================================================
 * VÉRIFIER SI LE PARRAIN A DROIT AU RETRAIT
 * ============================================================================
 *
 * Le retrait des commissions de parrainage est autorisé uniquement si :
 *
 *   1. l'utilisateur possède au moins un filleul ;
 *   2. au moins un de ses filleuls possède un abonnement Elite actif.
 *
 * Retourne :
 *
 *   true  = retrait autorisé
 *   false = retrait refusé
 *
 */
function canWithdrawReferralBonus(
    PDO $pdo,
    int $userId
): bool {

    /*
    |--------------------------------------------------------------------------
    | RECHERCHER UN FILLEUL AVEC ELITE ACTIF
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM users u
        INNER JOIN elite_members e
            ON e.user_id = u.id
        WHERE u.referred_by = ?
        AND e.status = 'active'
        AND (
            e.expires_at IS NULL
            OR e.expires_at > NOW()
        )
    ");

    $stmt->execute([
        $userId
    ]);

    $activeReferrals = (int)$stmt->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | CONDITION DE RETRAIT
    |--------------------------------------------------------------------------
    */

    return $activeReferrals >= 1;
}


/**
 * ============================================================================
 * OBTENIR LE NOMBRE DE FILLEULS ACTIFS
 * ============================================================================
 *
 * Utile pour afficher dans tasks.php :
 *
 *   "1 filleul actif"
 *   "3 filleuls actifs"
 *
 */
function getActiveReferralCount(
    PDO $pdo,
    int $userId
): int {

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM users u
        INNER JOIN elite_members e
            ON e.user_id = u.id
        WHERE u.referred_by = ?
        AND e.status = 'active'
        AND (
            e.expires_at IS NULL
            OR e.expires_at > NOW()
        )
    ");

    $stmt->execute([
        $userId
    ]);

    return (int)$stmt->fetchColumn();
}


/**
 * ============================================================================
 * INFORMATIONS COMPLÈTES DU PARRAINAGE
 * ============================================================================
 *
 * Retourne toutes les informations utiles pour la page tasks.php.
 *
 */
function getReferralStats(
    PDO $pdo,
    int $userId
): array {

    /*
    |--------------------------------------------------------------------------
    | NOMBRE TOTAL DE FILLEULS
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM users
        WHERE referred_by = ?
    ");

    $stmt->execute([
        $userId
    ]);

    $totalReferrals = (int)$stmt->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | FILLEULS AVEC ELITE ACTIF
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM users u
        INNER JOIN elite_members e
            ON e.user_id = u.id
        WHERE u.referred_by = ?
        AND e.status = 'active'
        AND (
            e.expires_at IS NULL
            OR e.expires_at > NOW()
        )
    ");

    $stmt->execute([
        $userId
    ]);

    $activeReferrals = (int)$stmt->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | BONUS DISPONIBLE
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT bonus_balance
        FROM users
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([
        $userId
    ]);

    $bonusBalance = (float)(
        $stmt->fetchColumn() ?? 0
    );


    /*
    |--------------------------------------------------------------------------
    | RETRAIT AUTORISÉ ?
    |--------------------------------------------------------------------------
    */

    $withdrawAllowed =
        $activeReferrals >= 1;


    /*
    |--------------------------------------------------------------------------
    | RETOUR
    |--------------------------------------------------------------------------
    */

    return [

        'total_referrals' =>
            $totalReferrals,

        'active_referrals' =>
            $activeReferrals,

        'bonus_balance' =>
            $bonusBalance,

        'withdraw_allowed' =>
            $withdrawAllowed,

        'reward_per_referral' =>
            100.00

    ];
}
?>
