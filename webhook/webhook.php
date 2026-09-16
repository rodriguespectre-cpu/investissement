<?php

declare(strict_types=1);

session_start();

header('Content-Type: application/json; charset=utf-8');

/*
|--------------------------------------------------------------------------
| CONFIGURATION
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../config/database.php';

$chariowConfig = require __DIR__ . '/../config/chariow.php';

$logDir  = __DIR__ . '/logs';
$logFile = $logDir . '/chariow-webhook.log';

if (!is_dir($logDir)) {
    mkdir($logDir, 0700, true);
}

if (!file_exists($logFile)) {
    touch($logFile);
    chmod($logFile, 0600);
}

/*
|--------------------------------------------------------------------------
| LOGGING SÉCURISÉ
|--------------------------------------------------------------------------
*/

function webhookLog(
    string $message,
    array $context = []
): void {

    global $logFile;

    /*
     * Ne jamais écrire le secret Chariow,
     * les mots de passe ou des données inutiles.
     */
    $sensitiveKeys = [
        'password',
        'api_key',
        'secret',
        'authorization',
        'x-chariow-signature'
    ];

    foreach ($sensitiveKeys as $key) {
        if (isset($context[$key])) {
            $context[$key] = '[REDACTED]';
        }
    }

    $entry = [
        'time'    => date('c'),
        'message' => $message,
        'context' => $context
    ];

    file_put_contents(
        $logFile,
        json_encode(
            $entry,
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
        ) . PHP_EOL,
        FILE_APPEND | LOCK_EX
    );
}

/*
|--------------------------------------------------------------------------
| RÉPONSE JSON
|--------------------------------------------------------------------------
*/

function respond(
    int $status,
    bool $success,
    string $message,
    array $extra = []
): never {

    http_response_code($status);

    echo json_encode(
        array_merge(
            [
                'success' => $success,
                'message' => $message
            ],
            $extra
        ),
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    );

    exit;
}

/*
|--------------------------------------------------------------------------
| MÉTHODE HTTP
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    webhookLog(
        'Méthode HTTP refusée.',
        [
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN'
        ]
    );

    respond(
        405,
        false,
        'Méthode non autorisée.'
    );
}

/*
|--------------------------------------------------------------------------
| CORPS BRUT
|--------------------------------------------------------------------------
*/

$rawBody = file_get_contents('php://input');

if ($rawBody === false) {
    $rawBody = '';
}

if ($rawBody === '') {

    webhookLog(
        'Corps de requête vide.'
    );

    respond(
        400,
        false,
        'Payload vide.'
    );
}

/*
|--------------------------------------------------------------------------
| SIGNATURE CHARIOW
|--------------------------------------------------------------------------
*/

$signature = '';

if (isset($_SERVER['HTTP_X_CHARIOW_SIGNATURE'])) {
    $signature = trim(
        $_SERVER['HTTP_X_CHARIOW_SIGNATURE']
    );
}

if ($signature === '') {

    webhookLog(
        'Signature Chariow absente.'
    );

    respond(
        401,
        false,
        'Signature invalide.'
    );
}

/*
|--------------------------------------------------------------------------
| SECRET
|--------------------------------------------------------------------------
*/

$secret = getenv('CHARIOW_WEBHOOK_SECRET');

if (!$secret) {
    $secret = $_ENV['CHARIOW_WEBHOOK_SECRET'] ?? '';
}

if ($secret === '') {

    webhookLog(
        'Secret webhook non configuré.'
    );

    respond(
        500,
        false,
        'Webhook non configuré.'
    );
}

/*
|--------------------------------------------------------------------------
| CALCUL SIGNATURE
|--------------------------------------------------------------------------
*/

$expectedSignature =
    'sha256=' .
    hash_hmac(
        'sha256',
        $rawBody,
        $secret
    );

/*
|--------------------------------------------------------------------------
| COMPARAISON CONSTANTE
|--------------------------------------------------------------------------
*/

if (!hash_equals(
    $expectedSignature,
    $signature
)) {

    webhookLog(
        'Signature invalide.',
        [
            'signature_received' => 'present'
        ]
    );

    respond(
        401,
        false,
        'Signature invalide.'
    );
}

/*
|--------------------------------------------------------------------------
| JSON
|--------------------------------------------------------------------------
*/

$payload = json_decode(
    $rawBody,
    true
);

if (!is_array($payload)) {

    webhookLog(
        'JSON invalide.'
    );

    respond(
        400,
        false,
        'Payload JSON invalide.'
    );
}

webhookLog(
    'Payload Chariow reçu.',
    [
        'payload' => $payload
    ]
);

/*
|--------------------------------------------------------------------------
| ÉVÉNEMENT
|--------------------------------------------------------------------------
*/

$event = (string)(
    $payload['event'] ?? ''
);

if ($event !== 'successful.sale') {

    webhookLog(
        'Événement ignoré.',
        [
            'event' => $event
        ]
    );

    respond(
        200,
        true,
        'Événement reçu.'
    );
}

/*
|--------------------------------------------------------------------------
| DONNÉES SALE
|--------------------------------------------------------------------------
*/

$sale = $payload['sale'] ?? [];

$product = $payload['product'] ?? [];

$customer = $payload['customer'] ?? [];

$metadata = $payload['custom_metadata'] ?? [];

/*
|--------------------------------------------------------------------------
| SALE ID
|--------------------------------------------------------------------------
*/

$saleId = (string)(
    $sale['id'] ?? ''
);

if ($saleId === '') {

    webhookLog(
        'Sale ID manquant.'
    );

    respond(
        400,
        false,
        'Sale ID manquant.'
    );
}

/*
|--------------------------------------------------------------------------
| PRODUCT ID
|--------------------------------------------------------------------------
|
| Chariow peut fournir product_id dans :
|
| sale.product_id
| product.id
|
*/

$productId = '';

if (
    isset($sale['product_id']) &&
    $sale['product_id'] !== ''
) {
    $productId = (string)$sale['product_id'];
}

if (
    $productId === '' &&
    isset($product['id'])
) {
    $productId = (string)$product['id'];
}

if ($productId === '') {

    webhookLog(
        'Product ID manquant.',
        [
            'sale_id' => $saleId
        ]
    );

    respond(
        400,
        false,
        'Product ID manquant.'
    );
}

/*
|--------------------------------------------------------------------------
| MONTANT CHARIOW
|--------------------------------------------------------------------------
*/

$chariowAmount = 0.0;

if (
    isset($sale['amount']['value']) &&
    is_numeric($sale['amount']['value'])
) {
    $chariowAmount =
        (float)$sale['amount']['value'];
}

if ($chariowAmount <= 0) {

    webhookLog(
        'Montant Chariow invalide.',
        [
            'sale_id' => $saleId
        ]
    );

    respond(
        400,
        false,
        'Montant invalide.'
    );
}

/*
|--------------------------------------------------------------------------
| MÉTADONNÉES
|--------------------------------------------------------------------------
*/

$reference = '';

if (isset($metadata['reference'])) {
    $reference =
        trim((string)$metadata['reference']);
}

$transactionId = 0;

if (
    isset($metadata['transaction_id']) &&
    is_numeric($metadata['transaction_id'])
) {
    $transactionId =
        (int)$metadata['transaction_id'];
}

webhookLog(
    'Événement analysé.',
    [
        'event'         => $event,
        'sale_id'       => $saleId,
        'product_id'    => $productId,
        'reference'     => $reference,
        'transaction_id'=> $transactionId,
        'amount'        => $chariowAmount
    ]
);

/*
|--------------------------------------------------------------------------
| UTILISATEUR / TRANSACTION
|--------------------------------------------------------------------------
*/

if ($transactionId <= 0) {

    webhookLog(
        'Transaction ID manquant.',
        [
            'sale_id' => $saleId,
            'reference' => $reference
        ]
    );

    respond(
        400,
        false,
        'Transaction locale manquante.'
    );
}

try {

    /*
    |--------------------------------------------------------------------------
    | TRANSACTION SQL
    |--------------------------------------------------------------------------
    */

    $pdo->beginTransaction();

    /*
    |--------------------------------------------------------------------------
    | RÉCUPÉRER LA TRANSACTION LOCALE
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT *
        FROM transactions
        WHERE id = ?
        LIMIT 1
        FOR UPDATE
    ");

    $stmt->execute([
        $transactionId
    ]);

    $transaction =
        $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$transaction) {

        throw new RuntimeException(
            'Transaction locale introuvable.'
        );
    }

    $userId =
        (int)$transaction['user_id'];

    $localAmount =
        (float)$transaction['amount'];

    /*
    |--------------------------------------------------------------------------
    | UTILISATEUR
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT *
        FROM users
        WHERE id = ?
        LIMIT 1
        FOR UPDATE
    ");

    $stmt->execute([
        $userId
    ]);

    $user =
        $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {

        throw new RuntimeException(
            'Utilisateur introuvable.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | PRODUIT ELITE
    |--------------------------------------------------------------------------
    */

    $eliteProductId = 'prd_y23cnfhp';

    $isEliteProduct =
        ($productId === $eliteProductId);

    /*
    |--------------------------------------------------------------------------
    | VÉRIFICATION DU PRODUIT
    |--------------------------------------------------------------------------
    */

    $authorizedProducts =
        $chariowConfig['deposit_products'] ?? [];

    if (!isset(
        $authorizedProducts[$productId]
    )) {

        throw new RuntimeException(
            'Produit Chariow non autorisé.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | MONTANT ATTENDU
    |--------------------------------------------------------------------------
    */

    $expectedAmount =
        (float)(
            $authorizedProducts[$productId]['amount']
            ?? 0
        );

    if ($expectedAmount <= 0) {

        throw new RuntimeException(
            'Montant produit non configuré.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | VÉRIFICATION MONTANT
    |--------------------------------------------------------------------------
    */

    if (
        abs(
            $localAmount -
            $expectedAmount
        ) > 0.01
    ) {

        webhookLog(
            'Montant local différent du produit.',
            [
                'transaction_id' => $transactionId,
                'local_amount'   => $localAmount,
                'expected_amount'=> $expectedAmount,
                'product_id'     => $productId
            ]
        );

        throw new RuntimeException(
            'Montant local incorrect.'
        );
    }

    if (
        abs(
            $chariowAmount -
            $expectedAmount
        ) > 0.01
    ) {

        webhookLog(
            'Montant Chariow différent.',
            [
                'transaction_id' => $transactionId,
                'expected_amount'=> $expectedAmount,
                'chariow_amount' => $chariowAmount,
                'product_id'     => $productId
            ]
        );

        throw new RuntimeException(
            'Montant Chariow incorrect.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | PROTECTION IDEMPOTENCE
    |--------------------------------------------------------------------------
    */

    if (
        $transaction['status'] === 'completed'
    ) {

        /*
         * Si c'est un produit Elite et que l'ancienne
         * tentative n'a pas activé Elite, on répare
         * l'accès ici.
         */

        if ($isEliteProduct) {

            $stmt = $pdo->prepare("
                SELECT *
                FROM elite_members
                WHERE user_id = ?
                LIMIT 1
                FOR UPDATE
            ");

            $stmt->execute([
                $userId
            ]);

            $eliteMember =
                $stmt->fetch(PDO::FETCH_ASSOC);

            if (
                !$eliteMember ||
                $eliteMember['status'] !== 'active'
            ) {

                if ($eliteMember) {

                    $stmt = $pdo->prepare("
                        UPDATE elite_members
                        SET
                            status = 'active',
                            amount = ?,
                            payment_method = 'chariow',
                            transaction_id = ?,
                            reference = ?,
                            activated_at = NOW(),
                            expires_at = NULL,
                            updated_at = NOW()
                        WHERE user_id = ?
                    ");

                    $stmt->execute([
                        $expectedAmount,
                        $transactionId,
                        $reference !== ''
                            ? $reference
                            : $saleId,
                        $userId
                    ]);

                } else {

                    $stmt = $pdo->prepare("
                        INSERT INTO elite_members (
                            user_id,
                            status,
                            amount,
                            payment_method,
                            transaction_id,
                            reference,
                            activated_at,
                            expires_at
                        )
                        VALUES (
                            ?,
                            'active',
                            ?,
                            'chariow',
                            ?,
                            ?,
                            NOW(),
                            NULL
                        )
                    ");

                    $stmt->execute([
                        $userId,
                        $expectedAmount,
                        $transactionId,
                        $reference !== ''
                            ? $reference
                            : $saleId
                    ]);
                }

                $stmt = $pdo->prepare("
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
                        'success',
                        ?,
                        ?,
                        0,
                        ?
                    )
                ");

                $stmt->execute([
                    $userId,
                    'Espace Elite activé',
                    'Votre paiement Chariow a été confirmé. Votre accès Elite est maintenant actif.',
                    'elite.php'
                ]);
            }
        }

        $pdo->commit();

        webhookLog(
            'Transaction déjà traitée.',
            [
                'transaction_id' => $transactionId,
                'sale_id'        => $saleId,
                'product_id'     => $productId
            ]
        );

        respond(
            200,
            true,
            'Transaction déjà traitée.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | TRAITEMENT ELITE CHARIOW
    |--------------------------------------------------------------------------
    |
    | IMPORTANT :
    |
    | Ici on N'AJOUTE PAS l'argent dans users.balance.
    |
    */

    if ($isEliteProduct) {

        /*
        |--------------------------------------------------------------------------
        | ELITE MEMBERS
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            SELECT *
            FROM elite_members
            WHERE user_id = ?
            LIMIT 1
            FOR UPDATE
        ");

        $stmt->execute([
            $userId
        ]);

        $eliteMember =
            $stmt->fetch(PDO::FETCH_ASSOC);

        if ($eliteMember) {

            $stmt = $pdo->prepare("
                UPDATE elite_members
                SET
                    status = 'active',
                    amount = ?,
                    payment_method = 'chariow',
                    transaction_id = ?,
                    reference = ?,
                    activated_at = NOW(),
                    expires_at = NULL,
                    updated_at = NOW()
                WHERE user_id = ?
            ");

            $stmt->execute([
                $expectedAmount,
                $transactionId,
                $reference !== ''
                    ? $reference
                    : $saleId,
                $userId
            ]);

        } else {

            $stmt = $pdo->prepare("
                INSERT INTO elite_members (
                    user_id,
                    status,
                    amount,
                    payment_method,
                    transaction_id,
                    reference,
                    activated_at,
                    expires_at
                )
                VALUES (
                    ?,
                    'active',
                    ?,
                    'chariow',
                    ?,
                    ?,
                    NOW(),
                    NULL
                )
            ");

            $stmt->execute([
                $userId,
                $expectedAmount,
                $transactionId,
                $reference !== ''
                    ? $reference
                    : $saleId
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | TRANSACTION = COMPLETED
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            UPDATE transactions
            SET
                status = 'completed',
                payment_method = 'chariow',
                reference = ?,
                description = ?,
                completed_at = NOW(),
                updated_at = NOW()
            WHERE id = ?
        ");

        $stmt->execute([
            $reference !== ''
                ? $reference
                : $saleId,

            'Activation de l’espace Elite via Chariow',

            $transactionId
        ]);

        /*
        |--------------------------------------------------------------------------
        | NOTIFICATION
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
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
                'success',
                ?,
                ?,
                0,
                ?
            )
        ");

        $stmt->execute([
            $userId,

            'Espace Elite activé',

            'Félicitations ! Votre paiement Chariow a été confirmé et votre accès Elite est maintenant actif.',

            'elite.php'
        ]);

        $pdo->commit();

        webhookLog(
            'Paiement Elite traité avec succès.',
            [
                'sale_id'        => $saleId,
                'product_id'     => $productId,
                'transaction_id' => $transactionId,
                'user_id'        => $userId,
                'amount'         => $expectedAmount,
                'reference'      => $reference
            ]
        );

        respond(
            200,
            true,
            'Paiement Elite traité avec succès.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | TRAITEMENT DÉPÔT CLASSIQUE
    |--------------------------------------------------------------------------
    */

    /*
     * Le produit n'est pas Elite.
     * Il s'agit donc d'un dépôt.
     */

    /*
    |--------------------------------------------------------------------------
    | CRÉDITER BALANCE
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        UPDATE users
        SET
            balance = balance + ?,
            updated_at = NOW()
        WHERE id = ?
    ");

    $stmt->execute([
        $expectedAmount,
        $userId
    ]);

    /*
    |--------------------------------------------------------------------------
    | BALANCE LEDGER
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        INSERT INTO balances (
            user_id,
            amount,
            type,
            source,
            reference_id,
            description
        )
        VALUES (
            ?,
            ?,
            'credit',
            'deposit',
            ?,
            ?
        )
    ");

    $stmt->execute([
        $userId,
        $expectedAmount,
        $transactionId,
        'Dépôt Chariow confirmé'
    ]);

    /*
    |--------------------------------------------------------------------------
    | TRANSACTION = COMPLETED
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        UPDATE transactions
        SET
            status = 'completed',
            payment_method = 'chariow',
            reference = ?,
            description = ?,
            completed_at = NOW(),
            updated_at = NOW()
        WHERE id = ?
    ");

    $stmt->execute([
        $reference !== ''
            ? $reference
            : $saleId,

        'Dépôt confirmé via Chariow',

        $transactionId
    ]);

    /*
    |--------------------------------------------------------------------------
    | NOTIFICATION
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
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
            'success',
            ?,
            ?,
            0,
            ?
        )
    ");

    $stmt->execute([
        $userId,

        'Dépôt confirmé',

        'Votre paiement Chariow a été confirmé. Votre solde a été crédité de ' .
        number_format(
            $expectedAmount,
            0,
            ',',
            ' '
        ) .
        ' FCFA.',

        'index.php'
    ]);

    /*
    |--------------------------------------------------------------------------
    | COMMIT
    |--------------------------------------------------------------------------
    */

    $pdo->commit();

    /*
    |--------------------------------------------------------------------------
    | LOG
    |--------------------------------------------------------------------------
    */

    webhookLog(
        'Paiement traité avec succès.',
        [
            'sale_id'        => $saleId,
            'product_id'     => $productId,
            'transaction_id' => $transactionId,
            'user_id'        => $userId,
            'amount'         => $expectedAmount,
            'reference'      => $reference
        ]
    );

    respond(
        200,
        true,
        'Paiement traité avec succès.'
    );

} catch (Throwable $e) {

    /*
    |--------------------------------------------------------------------------
    | ROLLBACK
    |--------------------------------------------------------------------------
    */

    if (
        isset($pdo) &&
        $pdo instanceof PDO &&
        $pdo->inTransaction()
    ) {
        $pdo->rollBack();
    }

    webhookLog(
        'Erreur traitement webhook.',
        [
            'error'          => $e->getMessage(),
            'transaction_id' => $transactionId,
            'product_id'     => $productId,
            'sale_id'        => $saleId
        ]
    );

    /*
    |--------------------------------------------------------------------------
    | RÉPONSE
    |--------------------------------------------------------------------------
    */

    respond(
        500,
        false,
        'Erreur lors du traitement du paiement.'
    );
}
