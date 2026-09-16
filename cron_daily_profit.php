<?php

require_once __DIR__ . '/config/database.php';


try {


    /*
    |--------------------------------------------------------------------------
    | Récupérer les investissements actifs
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->query("
        SELECT *
        FROM investments
        WHERE status = 'active'
    ");


    $investments = $stmt->fetchAll(PDO::FETCH_ASSOC);



    foreach ($investments as $investment) {


        $today = date('Y-m-d');


        /*
        |--------------------------------------------------------------------------
        | Empêcher double paiement
        |--------------------------------------------------------------------------
        */

        if ($investment['last_profit_date'] === $today) {
            continue;
        }



        /*
        |--------------------------------------------------------------------------
        | Vérifier la durée
        |--------------------------------------------------------------------------
        */

        $start = new DateTime($investment['start_date']);
        $now = new DateTime();


        $daysPassed = $start->diff($now)->days;



        /*
        |--------------------------------------------------------------------------
        | Calcul gain journalier
        |--------------------------------------------------------------------------
        */

        $dailyProfit =
            $investment['total_profit']
            /
            $investment['duration_days'];



        /*
        |--------------------------------------------------------------------------
        | Si le cycle est terminé
        |--------------------------------------------------------------------------
        */

        if ($daysPassed >= $investment['duration_days']) {


            $pdo->beginTransaction();


            // Créditer le retour final

            $stmt = $pdo->prepare("
                UPDATE users
                SET balance = balance + ?
                WHERE id = ?
            ");


            $stmt->execute([
                $investment['return_amount'],
                $investment['user_id']
            ]);



            // Historique

            $stmt = $pdo->prepare("
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
                    'investment_profit',
                    ?,
                    ?
                )
            ");


            $stmt->execute([

                $investment['user_id'],

                $investment['return_amount'],

                $investment['id'],

                'Retour investissement terminé'

            ]);



            // Terminer investissement

            $stmt = $pdo->prepare("
                UPDATE investments
                SET status='completed',
                    last_profit_date=?
                WHERE id=?
            ");


            $stmt->execute([
                $today,
                $investment['id']
            ]);



            $pdo->commit();


            continue;

        }





        /*
        |--------------------------------------------------------------------------
        | Crédit quotidien
        |--------------------------------------------------------------------------
        */


        $pdo->beginTransaction();



        // Ajouter au bonus utilisateur

        $stmt = $pdo->prepare("
            UPDATE users
            SET bonus_balance = bonus_balance + ?
            WHERE id = ?
        ");


        $stmt->execute([

            $dailyProfit,

            $investment['user_id']

        ]);




        // Historique gain

        $stmt = $pdo->prepare("
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
                'investment_profit',
                ?,
                ?
            )
        ");



        $stmt->execute([

            $investment['user_id'],

            $dailyProfit,

            $investment['id'],

            'Gain quotidien investissement'

        ]);




        // Marquer le jour payé

        $stmt = $pdo->prepare("
            UPDATE investments
            SET last_profit_date = ?
            WHERE id = ?
        ");


        $stmt->execute([

            $today,

            $investment['id']

        ]);



        $pdo->commit();



    }



    echo "Gains quotidiens traités avec succès";


}

catch(Throwable $e){


    if($pdo->inTransaction()){
        $pdo->rollBack();
    }


    echo "Erreur : ".$e->getMessage();


}
