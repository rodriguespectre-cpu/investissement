<?php

session_start();

require_once '../../config/database.php';



$eliteGameConfig = require '../../config/elite_game.php';


if (!isLoggedIn()) {
    header('Location: ../login.php');
    exit;
}

$user = getCurrentUser();

if (!$user) {
    session_destroy();
    header('Location: ../login.php');
    exit;
}

$userId = (int) $user['id'];

/*
|--------------------------------------------------------------------------
| CONFIGURATION ELITE
|--------------------------------------------------------------------------
*/
$eliteTimezone = new DateTimeZone(
    $eliteGameConfig['timezone'] ?? 'UTC'
);

$eliteNow = new DateTimeImmutable(
    'now',
    $eliteTimezone
);

/*
|--------------------------------------------------------------------------
| RÉCUPÉRER LA PROCHAINE SÉANCE
|--------------------------------------------------------------------------
*/

$eliteSessionStmt = $pdo->prepare("
    SELECT *
    FROM elite_game_sessions
    WHERE status = 'open'
    AND scheduled_at > ?
    ORDER BY scheduled_at ASC
    LIMIT 1
");

$eliteSessionStmt->execute([
    $eliteNow->format('Y-m-d H:i:s')
]);

$eliteSession =
    $eliteSessionStmt->fetch(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| CRÉER UNE SÉANCE SI ELLE N'EXISTE PAS
|--------------------------------------------------------------------------
*/

if (!$eliteSession) {

    $next = $eliteNow;

    $daysUntilWednesday =
        (
            3 -
            (int)$next->format('N') +
            7
        ) % 7;

    if ($daysUntilWednesday === 0) {

        if (
            (int)$next->format('H') > 19
            ||
            (
                (int)$next->format('H') === 19 &&
                (int)$next->format('i') >= 0
            )
        ) {
            $daysUntilWednesday = 7;
        }
    }

    $next = $next
        ->modify("+{$daysUntilWednesday} days")
        ->setTime(19, 0, 0);

    /*
    |--------------------------------------------------------------------------
    | SESSION ELITE
    |--------------------------------------------------------------------------
    */

    $scheduledAt = $next->format('Y-m-d H:i:s');

    $prizeAmount = (float)(
        $eliteGameConfig['prize'] ?? 200000
    );

    /*
    |--------------------------------------------------------------------------
    | Vérifier si cette session existe
    |--------------------------------------------------------------------------
    */

    $eliteSessionStmt = $pdo->prepare("
        SELECT *
        FROM elite_game_sessions
        WHERE scheduled_at = ?
        LIMIT 1
    ");

    $eliteSessionStmt->execute([
        $scheduledAt
    ]);

    $eliteSession =
        $eliteSessionStmt->fetch(PDO::FETCH_ASSOC);

    /*
    |--------------------------------------------------------------------------
    | Créer la session si nécessaire
    |--------------------------------------------------------------------------
    */

    if (!$eliteSession) {

        $createSession = $pdo->prepare("
            INSERT INTO elite_game_sessions
            (
                scheduled_at,
                prize_amount,
                status
            )
            VALUES (?, ?, 'open')
        ");

        $createSession->execute([
            $scheduledAt,
            $prizeAmount
        ]);

        /*
        |--------------------------------------------------------------------------
        | Récupérer la session créée
        |--------------------------------------------------------------------------
        */

        $eliteSessionStmt = $pdo->prepare("
            SELECT *
            FROM elite_game_sessions
            WHERE scheduled_at = ?
            LIMIT 1
        ");

        $eliteSessionStmt->execute([
            $scheduledAt
        ]);

        $eliteSession =
            $eliteSessionStmt->fetch(PDO::FETCH_ASSOC);
    }
}

/*
|--------------------------------------------------------------------------
| VÉRIFIER LA PARTICIPATION DE L'UTILISATEUR
|--------------------------------------------------------------------------
*/

$eliteAlreadyPlayed = false;
$eliteSelectedColor = null;

if ($eliteSession) {

    $entryStmt = $pdo->prepare("
        SELECT color
        FROM elite_game_entries
        WHERE session_id = ?
        AND user_id = ?
        LIMIT 1
    ");

    $entryStmt->execute([
        (int)$eliteSession['id'],
        (int)$user['id']
    ]);

    $entry = $entryStmt->fetch(PDO::FETCH_ASSOC);

    if ($entry) {

        $eliteAlreadyPlayed = true;

        $eliteSelectedColor =
            $entry['color'];
    }
}

$eliteColors =
    $eliteGameConfig['colors'] ?? [];

$elitePrize =
    (float)(
        $eliteSession['prize_amount']
        ?? 200000
    );

$eliteSessionDate =
    $eliteSession['scheduled_at']
    ?? null;


/*
|--------------------------------------------------------------------------
| INVESTPRO — ESPACE ELITE
|--------------------------------------------------------------------------
| Prix : 3 000 XAF
| Produit Chariow : prd_y23cnfhp
|
| IMPORTANT :
| Le paiement Chariow reste PENDING jusqu'à la confirmation webhook.
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| AUTHENTIFICATION
|--------------------------------------------------------------------------
*/


$elitePrice = 3000.00;
$eliteProductId = 'prd_y23cnfhp';
$eliteProductName = 'Payeici';

/*
|--------------------------------------------------------------------------
| CRÉATION DE LA TABLE ELITE
|--------------------------------------------------------------------------
|
| Cette table permet de savoir si l'utilisateur possède l'accès Elite.
|
*/

try {

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS elite_members (
            id INT(11) NOT NULL AUTO_INCREMENT,
            user_id INT(11) NOT NULL,
            status ENUM('pending','active','expired','cancelled')
                NOT NULL DEFAULT 'pending',
            amount DECIMAL(15,2) NOT NULL DEFAULT 3000.00,
            payment_method VARCHAR(50) DEFAULT NULL,
            transaction_id INT(11) DEFAULT NULL,
            reference VARCHAR(100) DEFAULT NULL,
            activated_at TIMESTAMP NULL DEFAULT NULL,
            expires_at TIMESTAMP NULL DEFAULT NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_elite_user (user_id),
            KEY idx_elite_status (status),
            CONSTRAINT fk_elite_user
                FOREIGN KEY (user_id)
                REFERENCES users(id)
                ON DELETE CASCADE
        ) ENGINE=InnoDB
        DEFAULT CHARSET=utf8mb4
        COLLATE=utf8mb4_unicode_ci
    ");

} catch (Throwable $e) {

    die(
        'Impossible de préparer l’espace Elite.'
    );
}

/*
|--------------------------------------------------------------------------
| FONCTIONS
|--------------------------------------------------------------------------
*/

function eliteMoney($amount): string
{
    return number_format(
        (float) $amount,
        0,
        ',',
        ' '
    ) . ' FCFA';
}

function eliteReference(): string
{
    try {

        return 'ELT-' .
            date('YmdHis') .
            '-' .
            strtoupper(
                bin2hex(
                    random_bytes(4)
                )
            );

    } catch (Throwable $e) {

        return 'ELT-' .
            date('YmdHis') .
            '-' .
            strtoupper(
                substr(
                    md5(uniqid('', true)),
                    0,
                    8
                )
            );
    }
}

function eliteNotify(
    PDO $pdo,
    int $userId,
    string $title,
    string $message,
    ?string $link = null
): void {

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
            'info',
            ?,
            ?,
            0,
            ?
        )
    ");

    $stmt->execute([
        $userId,
        $title,
        $message,
        $link
    ]);
}

/*
|--------------------------------------------------------------------------
| VÉRIFICATION ACCÈS EXISTANT
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT *
    FROM elite_members
    WHERE user_id = ?
    LIMIT 1
");

$stmt->execute([
    $userId
]);

$eliteMember = $stmt->fetch(PDO::FETCH_ASSOC);

$isElite = false;

if ($eliteMember) {

    if ($eliteMember['status'] === 'active') {

        if (
            empty($eliteMember['expires_at'])
            ||
            strtotime($eliteMember['expires_at']) > time()
        ) {

            $isElite = true;

        } else {

            $update = $pdo->prepare("
                UPDATE elite_members
                SET status = 'expired'
                WHERE user_id = ?
            ");

            $update->execute([
                $userId
            ]);

            $eliteMember['status'] = 'expired';
        }
    }
}

/*
|--------------------------------------------------------------------------
| TRAITEMENT DES ACTIONS
|--------------------------------------------------------------------------
*/

$action = $_POST['action'] ?? '';

$successMessage = '';
$errorMessage = '';

/*
|--------------------------------------------------------------------------
| PAIEMENT AVEC LE SOLDE
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    &&
    $action === 'pay_balance'
) {

    try {

        $pdo->beginTransaction();

        /*
        | Verrouillage utilisateur
        */

        $stmt = $pdo->prepare("
            SELECT *
            FROM users
            WHERE id = ?
            FOR UPDATE
        ");

        $stmt->execute([
            $userId
        ]);

        $lockedUser =
            $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$lockedUser) {
            throw new Exception(
                'Utilisateur introuvable.'
            );
        }

        /*
        | Vérifier si déjà Elite
        */

        $stmt = $pdo->prepare("
            SELECT *
            FROM elite_members
            WHERE user_id = ?
            FOR UPDATE
        ");

        $stmt->execute([
            $userId
        ]);

        $existingElite =
            $stmt->fetch(PDO::FETCH_ASSOC);

        if (
            $existingElite
            &&
            $existingElite['status'] === 'active'
            &&
            (
                empty($existingElite['expires_at'])
                ||
                strtotime($existingElite['expires_at']) > time()
            )
        ) {

            throw new Exception(
                'Votre accès Elite est déjà actif.'
            );
        }

        /*
        | Solde disponible
        */

        $balance =
            (float) $lockedUser['balance'];

        if ($balance < $elitePrice) {

            throw new Exception(
                'Votre solde est insuffisant. ' .
                'Il vous faut au moins ' .
                eliteMoney($elitePrice) .
                '.'
            );
        }

        /*
        | Référence
        */

        $reference =
            eliteReference();

        /*
        | Débit utilisateur
        */

        $stmt = $pdo->prepare("
            UPDATE users
            SET balance = balance - ?
            WHERE id = ?
              AND balance >= ?
        ");

        $stmt->execute([
            $elitePrice,
            $userId,
            $elitePrice
        ]);

        if ($stmt->rowCount() !== 1) {

            throw new Exception(
                'Impossible de débiter votre solde.'
            );
        }

        /*
        | Transaction
        */

        $description =
            'Activation espace Elite - ' .
            eliteMoney($elitePrice);

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
                'Solde',
                ?,
                ?,
                NOW()
            )
        ");

        $stmt->execute([
            $userId,
            $elitePrice,
            $reference,
            $description
        ]);

        $transactionId =
            (int) $pdo->lastInsertId();

        /*
        | Balance ledger
        */

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
                'debit',
                'admin_adjustment',
                ?,
                ?
            )
        ");

        $stmt->execute([
            $userId,
            $elitePrice,
            $transactionId,
            'Activation espace Elite'
        ]);

        /*
        | Activation Elite
        */

        if ($existingElite) {

            $stmt = $pdo->prepare("
                UPDATE elite_members
                SET
                    status = 'active',
                    amount = ?,
                    payment_method = 'Solde',
                    transaction_id = ?,
                    reference = ?,
                    activated_at = NOW(),
                    expires_at = NULL
                WHERE user_id = ?
            ");

            $stmt->execute([
                $elitePrice,
                $transactionId,
                $reference,
                $userId
            ]);

        } else {

            $stmt = $pdo->prepare("
                INSERT INTO elite_members
                (
                    user_id,
                    status,
                    amount,
                    payment_method,
                    transaction_id,
                    reference,
                    activated_at
                )
                VALUES
                (
                    ?,
                    'active',
                    ?,
                    'Solde',
                    ?,
                    ?,
                    NOW()
                )
            ");

            $stmt->execute([
                $userId,
                $elitePrice,
                $transactionId,
                $reference
            ]);
        }

        /*
        | Notification
        */

        eliteNotify(
            $pdo,
            $userId,
            'Espace Elite activé',
            'Félicitations ! Votre accès Elite a été activé avec succès.',
            'elite.php'
        );

        $pdo->commit();

        $successMessage =
            'Votre espace Elite est maintenant actif.';

        $isElite = true;

        $stmt = $pdo->prepare("
            SELECT *
            FROM elite_members
            WHERE user_id = ?
            LIMIT 1
        ");

        $stmt->execute([
            $userId
        ]);

        $eliteMember =
            $stmt->fetch(PDO::FETCH_ASSOC);

    } catch (Throwable $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $errorMessage =
            $e->getMessage();
    }
}

/*
|--------------------------------------------------------------------------
| CRÉATION CHECKOUT CHARIOW
|--------------------------------------------------------------------------
|
| Le webhook sera configuré ensuite.
|
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    &&
    $action === 'pay_chariow'
) {

    try {

        /*
        | Vérifier l'accès
        */

        if ($isElite) {

            throw new Exception(
                'Votre accès Elite est déjà actif.'
            );
        }

        /*
        | Informations utilisateur
        */

        $email =
            trim(
                $user['email'] ?? ''
            );

        $firstName =
            trim(
                $user['first_name'] ?? ''
            );

        $lastName =
            trim(
                $user['last_name'] ?? ''
            );

        $phone =
            trim(
                $user['phone']
                ?? $user['phone_number']
                ?? ''
            );

        $countryCode =
            strtoupper(
                trim(
                    $user['country_code']
                    ?? 'CM'
                )
            );

        if ($email === '') {

            throw new Exception(
                'Votre adresse email est manquante.'
            );
        }

        if ($phone === '') {

            throw new Exception(
                'Votre numéro de téléphone est requis pour le paiement.'
            );
        }

        if ($firstName === '') {
            $firstName = 'InvestPro';
        }

        if ($lastName === '') {
            $lastName = 'User';
        }

        /*
        | Référence
        */

        $reference =
            eliteReference();

        /*
        | Transaction pending
        */

        $pdo->beginTransaction();

        $description =
            'Accès Elite - Payeici - 3 000 XAF';

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
                description
            )
            VALUES
            (
                ?,
                'deposit',
                ?,
                0.00,
                'pending',
                'Chariow',
                ?,
                ?
            )
        ");

        $stmt->execute([
            $userId,
            $elitePrice,
            $reference,
            $description
        ]);

        $transactionId =
            (int) $pdo->lastInsertId();

        /*
        | Enregistrer le paiement Elite pending
        */

        if ($eliteMember) {

            $stmt = $pdo->prepare("
                UPDATE elite_members
                SET
                    status = 'pending',
                    amount = ?,
                    payment_method = 'Chariow',
                    transaction_id = ?,
                    reference = ?
                WHERE user_id = ?
            ");

            $stmt->execute([
                $elitePrice,
                $transactionId,
                $reference,
                $userId
            ]);

        } else {

            $stmt = $pdo->prepare("
                INSERT INTO elite_members
                (
                    user_id,
                    status,
                    amount,
                    payment_method,
                    transaction_id,
                    reference
                )
                VALUES
                (
                    ?,
                    'pending',
                    ?,
                    'Chariow',
                    ?,
                    ?
                )
            ");

            $stmt->execute([
                $userId,
                $elitePrice,
                $transactionId,
                $reference
            ]);
        }

        $pdo->commit();

        /*
        | Configuration Chariow
        */

        $config =
            require '../../config/chariow.php';

        $apiBase =
            rtrim(
                $config['api_base_url']
                ?? 'https://api.chariow.com',
                '/'
            );

        $endpoint =
            $config['checkout_endpoint']
            ?? '/v1/checkout';

        $apiUrl =
            $apiBase .
            '/' .
            ltrim(
                $endpoint,
                '/'
            );

$apiKey =
    trim(
        (string)(
            $config['api_key']
            ?? ($_ENV['CHARIOW_API_KEY'] ?? null)
            ?? getenv('CHARIOW_API_KEY')
            ?? ''
        )
    );

        /*
        | Payload
        */

        $payload = [

            'product_id' =>
                $eliteProductId,

            'email' =>
                $email,

            'first_name' =>
                $firstName,

            'last_name' =>
                $lastName,

            'phone' => [

                'number' =>
                    $phone,

                'country_code' =>
                    $countryCode
            ],

            'custom_metadata' => [

                'investpro_purpose' =>
                    'elite_access',

                'investpro_user_id' =>
                    (string) $userId,

                'investpro_transaction_id' =>
                    (string) $transactionId,

                'investpro_reference' =>
                    $reference,

                'elite_amount' =>
                    '3000',

                'elite_product_id' =>
                    $eliteProductId
            ]
        ];

        /*
        | Appel API
        */

        $ch =
            curl_init(
                $apiUrl
            );

        curl_setopt_array(
            $ch,
            [

                CURLOPT_POST =>
                    true,

                CURLOPT_POSTFIELDS =>
                    json_encode(
                        $payload,
                        JSON_UNESCAPED_UNICODE |
                        JSON_UNESCAPED_SLASHES
                    ),

                CURLOPT_HTTPHEADER => [

                    'Authorization: Bearer ' .
                        $apiKey,

                    'Accept: application/json',

                    'Content-Type: application/json'
                ],

                CURLOPT_RETURNTRANSFER =>
                    true,

                CURLOPT_CONNECTTIMEOUT =>
                    10,

                CURLOPT_TIMEOUT =>
                    (int)(
                        $config['timeout']
                        ?? 30
                    )
            ]
        );

        $response =
            curl_exec($ch);

        $curlError =
            curl_error($ch);

        $httpCode =
            (int) curl_getinfo(
                $ch,
                CURLINFO_HTTP_CODE
            );

        

        if ($response === false) {

            throw new Exception(
                'Impossible de contacter Chariow.'
            );
        }

        $data =
            json_decode(
                $response,
                true
            );

        if (!is_array($data)) {

            throw new Exception(
                'Réponse invalide de Chariow.'
            );
        }

        if (
            $httpCode < 200
            ||
            $httpCode >= 300
        ) {

            throw new Exception(
                $data['message']
                ??
                'Chariow a refusé le paiement.'
            );
        }

        $payment =
            $data['data']['payment']
            ?? [];

        $checkoutUrl =
            $payment['checkout_url']
            ?? null;

        if (!$checkoutUrl) {

            throw new Exception(
                'Chariow n’a pas retourné de lien de paiement.'
            );
        }

        /*
        | Redirection vers Chariow
        */

        header(
            'Location: ' .
            $checkoutUrl
        );

        exit;

    } catch (Throwable $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $errorMessage =
            $e->getMessage();
    }
}

/*
|--------------------------------------------------------------------------
| RECHARGER L'ÉTAT ELITE
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT *
    FROM elite_members
    WHERE user_id = ?
    LIMIT 1
");

$stmt->execute([
    $userId
]);

$eliteMember =
    $stmt->fetch(PDO::FETCH_ASSOC);

if (
    $eliteMember
    &&
    $eliteMember['status'] === 'active'
) {
    $isElite = true;
}

/*
|--------------------------------------------------------------------------
| SOLDE
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT balance
    FROM users
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([
    $userId
]);

$currentBalance =
    (float)(
        $stmt->fetchColumn()
        ?? 0
    );

/*
|--------------------------------------------------------------------------
| INITIAL
|--------------------------------------------------------------------------
*/

$firstLetter =
    strtoupper(
        substr(
            $user['username'] ?? 'U',
            0,
            1
        )
    );

?>
<!DOCTYPE html>
<html lang="fr">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Elite — InvestPro</title>

<link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
>

<style>

* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

:root {
    --elite: #2563eb;
    --elite-dark: #1d4ed8;
    --gold: #f59e0b;
    --dark: #0f172a;
    --muted: #64748b;
    --bg: #f5f7fb;
    --white: #ffffff;
    --border: #e5e7eb;
    --success: #16a34a;
    --danger: #dc2626;
}

body {
    font-family:
        Inter,
        -apple-system,
        BlinkMacSystemFont,
        "Segoe UI",
        sans-serif;

    background: var(--bg);
    color: var(--dark);
}

a {
    text-decoration: none;
    color: inherit;
}

.page {
    max-width: 1250px;
    margin: auto;
    padding: 24px;
}

/*
|--------------------------------------------------------------------------
| HEADER
|--------------------------------------------------------------------------
*/

.top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
    margin-bottom: 25px;
}

.back {
    display: inline-flex;
    align-items: center;
    gap: 9px;
    color: var(--muted);
    font-weight: 700;
}

.back:hover {
    color: var(--elite);
}

.user-badge {
    display: flex;
    align-items: center;
    gap: 10px;
}

.avatar {
    width: 42px;
    height: 42px;
    border-radius: 50%;
    display: grid;
    place-items: center;
    background: linear-gradient(
        135deg,
        #2563eb,
        #7c3aed
    );
    color: white;
    font-weight: 800;
}

/*
|--------------------------------------------------------------------------
| HERO
|--------------------------------------------------------------------------
*/

.hero {
    position: relative;
    overflow: hidden;
    border-radius: 28px;
    padding: 45px;
    background:
        radial-gradient(
            circle at 90% 10%,
            rgba(245,158,11,.25),
            transparent 30%
        ),
        linear-gradient(
            135deg,
            #0f172a,
            #172554 55%,
            #1d4ed8
        );

    color: white;
    margin-bottom: 25px;
}

.hero::after {
    content: "✦";
    position: absolute;
    right: 55px;
    bottom: 15px;
    font-size: 150px;
    opacity: .05;
}

.elite-tag {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: rgba(255,255,255,.12);
    border: 1px solid rgba(255,255,255,.2);
    padding: 8px 14px;
    border-radius: 999px;
    font-size: 13px;
    font-weight: 800;
    letter-spacing: .5px;
    margin-bottom: 18px;
}

.hero h1 {
    font-size: clamp(34px, 6vw, 60px);
    line-height: 1;
    margin-bottom: 18px;
}

.hero h1 span {
    color: #fbbf24;
}

.hero p {
    max-width: 680px;
    color: rgba(255,255,255,.78);
    line-height: 1.7;
    font-size: 16px;
}

.hero-status {
    margin-top: 25px;
    display: inline-flex;
    align-items: center;
    gap: 9px;
    padding: 11px 16px;
    border-radius: 12px;
    background: rgba(255,255,255,.1);
}

/*
|--------------------------------------------------------------------------
| MESSAGES
|--------------------------------------------------------------------------
*/

.message {
    padding: 15px 18px;
    border-radius: 14px;
    margin-bottom: 20px;
    font-weight: 700;
}

.message.success {
    background: #dcfce7;
    color: #166534;
}

.message.error {
    background: #fee2e2;
    color: #991b1b;
}

/*
|--------------------------------------------------------------------------
| ACCESS CARD
|--------------------------------------------------------------------------
*/

.access-card {
    background: white;
    border: 1px solid var(--border);
    border-radius: 24px;
    padding: 30px;
    margin-bottom: 28px;
    box-shadow:
        0 15px 45px rgba(15,23,42,.06);
}

.access-card h2 {
    margin-bottom: 8px;
}

.access-card > p {
    color: var(--muted);
    line-height: 1.6;
}

.payment-grid {
    display: grid;
    grid-template-columns:
        repeat(2, minmax(0, 1fr));
    gap: 18px;
    margin-top: 25px;
}

.payment-card {
    border: 1px solid var(--border);
    border-radius: 20px;
    padding: 25px;
    background: #fff;
}

.payment-card.primary {
    border-color: #bfdbfe;
    background: linear-gradient(
        145deg,
        #eff6ff,
        #ffffff
    );
}

.payment-icon {
    width: 50px;
    height: 50px;
    display: grid;
    place-items: center;
    border-radius: 15px;
    background: #dbeafe;
    color: var(--elite);
    font-size: 21px;
    margin-bottom: 15px;
}

.payment-card h3 {
    margin-bottom: 8px;
}

.payment-card p {
    color: var(--muted);
    line-height: 1.55;
    margin-bottom: 17px;
}

.price {
    font-size: 27px;
    font-weight: 900;
    margin-bottom: 16px;
}

.btn {
    border: 0;
    width: 100%;
    padding: 14px 18px;
    border-radius: 13px;
    cursor: pointer;
    font-weight: 800;
    font-size: 15px;
    transition: .2s ease;
}

.btn:hover {
    transform: translateY(-2px);
}

.btn-blue {
    background: var(--elite);
    color: white;
}

.btn-blue:hover {
    background: var(--elite-dark);
}

.btn-gold {
    background: linear-gradient(
        135deg,
        #f59e0b,
        #d97706
    );
    color: white;
}

.balance {
    margin-bottom: 12px;
    color: var(--muted);
}

.balance strong {
    color: var(--dark);
}

/*
|--------------------------------------------------------------------------
| ELITE CONTENT
|--------------------------------------------------------------------------
*/

.section-title {
    margin: 35px 0 18px;
}

.section-title small {
    display: block;
    color: var(--elite);
    font-weight: 900;
    letter-spacing: 1px;
    margin-bottom: 5px;
}

.section-title h2 {
    font-size: 27px;
}

.features {
    display: grid;
    grid-template-columns:
        repeat(3, minmax(0, 1fr));
    gap: 18px;
}

.feature {
    background: white;
    border: 1px solid var(--border);
    border-radius: 22px;
    padding: 25px;
    transition: .2s ease;
}

.feature:hover {
    transform: translateY(-4px);
    box-shadow:
        0 15px 35px rgba(15,23,42,.08);
}

.feature-icon {
    width: 55px;
    height: 55px;
    display: grid;
    place-items: center;
    border-radius: 17px;
    background: #eff6ff;
    color: var(--elite);
    font-size: 23px;
    margin-bottom: 18px;
}

.feature h3 {
    margin-bottom: 8px;
}

.feature p {
    color: var(--muted);
    line-height: 1.6;
}

.locked {
    opacity: .62;
}

.badge {
    display: inline-block;
    margin-top: 15px;
    padding: 6px 10px;
    border-radius: 8px;
    background: #f1f5f9;
    color: var(--muted);
    font-size: 12px;
    font-weight: 800;
}

/*
|--------------------------------------------------------------------------
| FOOTER
|--------------------------------------------------------------------------
*/

.footer {
    text-align: center;
    color: var(--muted);
    padding: 35px 10px 10px;
    font-size: 13px;
}

/*
|--------------------------------------------------------------------------
| RESPONSIVE
|--------------------------------------------------------------------------
*/

@media (max-width: 800px) {

    .page {
        padding: 15px;
    }

    .hero {
        padding: 30px 23px;
        border-radius: 22px;
    }

    .payment-grid,
    .features {
        grid-template-columns: 1fr;
    }

    .top {
        align-items: flex-start;
    }

}

</style>

</head>

<body>

<div class="page">

    <!-- HEADER -->

    <div class="top">

        <a
            href="index.php"
            class="back"
        >
            <i class="fas fa-arrow-left"></i>
            Tableau de bord
        </a>

        <div class="user-badge">

            <div class="avatar">
                <?= htmlspecialchars($firstLetter) ?>
            </div>

            <strong>
                <?= htmlspecialchars(
                    $user['username']
                ) ?>
            </strong>

        </div>

    </div>


    <!-- HERO -->

    <section class="hero">

        <div class="elite-tag">
            <i class="fas fa-crown"></i>
            INVESTPRO ELITE
        </div>

        <h1>
            Bienvenue dans
            <span>Elite.</span>
        </h1>

        <p>
            Une nouvelle expérience réservée aux membres
            Elite : jeux, vidéos exclusives, missions et
            récompenses. Accomplissez les activités
            disponibles et développez votre espace de gains.
        </p>

        <?php if ($isElite): ?>

            <div class="hero-status">
                <i class="fas fa-circle-check"></i>
                Votre accès Elite est actif
            </div>

        <?php elseif (
            $eliteMember
            &&
            $eliteMember['status'] === 'pending'
        ): ?>

            <div class="hero-status">
                <i class="fas fa-clock"></i>
                Paiement en attente de confirmation
            </div>

        <?php endif; ?>

    </section>


    <!-- MESSAGES -->

    <?php if ($successMessage): ?>

        <div class="message success">

            <i class="fas fa-circle-check"></i>

            <?= htmlspecialchars(
                $successMessage
            ) ?>

        </div>

    <?php endif; ?>


    <?php if ($errorMessage): ?>

        <div class="message error">

            <i class="fas fa-circle-exclamation"></i>

            <?= htmlspecialchars(
                $errorMessage
            ) ?>

        </div>

    <?php endif; ?>


    <?php if (!$isElite): ?>

        <!-- ACHAT ELITE -->

        <section class="access-card">

            <h2>
                Débloquer l'espace Elite
            </h2>

            <p>
                L'accès Elite coûte
                <strong>3 000 FCFA</strong>.
                Vous pouvez utiliser directement votre
                solde InvestPro ou effectuer le paiement
                sécurisé avec Chariow.
            </p>


            <div class="payment-grid">

                <!-- SOLDE -->

                <div class="payment-card primary">

                    <div class="payment-icon">

                        <i class="fas fa-wallet"></i>

                    </div>

                    <h3>
                        Payer avec mon solde
                    </h3>

                    <p>
                        Le montant sera débité directement
                        de votre solde InvestPro.
                    </p>

                    <div class="price">
                        3 000 FCFA
                    </div>

                    <div class="balance">

                        Solde disponible :
                        <strong>
                            <?= eliteMoney(
                                $currentBalance
                            ) ?>
                        </strong>

                    </div>

                    <form method="POST">

                        <input
                            type="hidden"
                            name="action"
                            value="pay_balance"
                        >

                        <button
                            type="submit"
                            class="btn btn-blue"
                        >

                            <i class="fas fa-lock-open"></i>

                            Activer avec mon solde

                        </button>

                    </form>

                </div>


                <!-- CHARIOW -->

                <div class="payment-card">

                    <div class="payment-icon">

                        <i class="fas fa-credit-card"></i>

                    </div>

                    <h3>
                        Payer votre accès
                    </h3>

                    <p>
                        Vous serez redirigé vers la page
                        de paiement  pour régler
                        les 3 000 FCFA.
                    </p>

                    <div class="price">
                        3 000 FCFA
                    </div>

                    <form method="POST">

                        <input
                            type="hidden"
                            name="action"
                            value="pay_chariow"
                        >

                        <button
                            type="submit"
                            class="btn btn-gold"
                        >

                            <i class="fas fa-arrow-up-right-from-square"></i>

                            Payer votre accès

                        </button>

                    </form>

                </div>

            </div>

        </section>

    <?php endif; ?>


    <?php if ($isElite): ?>

        <!-- ESPACE ELITE -->

        <div class="section-title">

            <small>
                ESPACE PRIVÉ
            </small>

            <h2>
                Votre expérience Elite
            </h2>

        </div>


        <section class="features">

            <!-- JEUX -->

            <a
                href="elite-game.php"
                class="feature"
            >

                <div class="feature-icon">

                    <i class="fas fa-gamepad"></i>

                </div>

                <h3>
                    Jeux
                </h3>

                <p>
                    Participez au jeu et tentez de gagner jusqu'a 200 000 FCFA.
                </p>

                <span class="badge">
                    Wins level
                </span>

            </a>


            <!-- VIDÉOS -->

            <a
                href="#"
                class="feature"
            >

                <div class="feature-icon">

                    <i class="fas fa-play"></i>

                </div>

                <h3>
                    Vidéos
                </h3>

                <p>
                    Regardez les contenus exclusifs
                    proposés aux membres Elite.
                </p>

                <span class="badge">
                    Bientôt disponible
                </span>

            </a>


            <!-- TÂCHES -->

            <a
                href="tasks.php"
                class="feature"
            >

                <div class="feature-icon">

                    <i class="fas fa-list-check"></i>

                </div>

                <h3>
                    Tâches rémunérées
                </h3>

                <p>
                    Accomplissez les missions disponibles
                    et recevez vos récompenses lorsque
                    les conditions sont remplies.
                </p>

                <span class="badge">
                    Gagner en partageant
                </span>

            </a>

        </section>


        <div class="section-title">

            <small>
                RÉCOMPENSES
            </small>

            <h2>
                Gagnez en accomplissant des activités
            </h2>

        </div>


        <section class="features">

            <div class="feature">

                <div class="feature-icon">

                    <i class="fas fa-coins"></i>

                </div>

                <h3>
                    Gains
                </h3>

                <p>
                    Les récompenses validées seront
                    créditées dans votre compte selon
                    les règles de chaque activité.
                </p>

            </div>


            <div class="feature">

                <div class="feature-icon">

                    <i class="fas fa-shield-halved"></i>

                </div>

                <h3>
                    Sécurisé
                </h3>

                <p>
                    Les validations et crédits sont
                    traités côté serveur afin de limiter
                    les manipulations côté navigateur.
                </p>

            </div>


            <div class="feature">

                <div class="feature-icon">

                    <i class="fas fa-bell"></i>

                </div>

                <h3>
                    Notifications
                </h3>

                <p>
                    Vous recevrez une notification lors
                    des événements importants concernant
                    votre espace Elite.
                </p>

            </div>

        </section>

    <?php endif; ?>


    <div class="footer">

        <i class="fas fa-crown"></i>

        InvestPro Elite — Espace membre premium

    </div>

</div>

</body>

</html>
