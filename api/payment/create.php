<?php



declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);
ini_set('log_errors', '1');



session_start();

require_once '../../config/database.php';


/*
|--------------------------------------------------------------------------
| RÉPONSE JSON
|--------------------------------------------------------------------------
*/

function jsonResponse(
    bool $success,
    string $message,
    array $data = [],
    int $status = 200
): never {

    http_response_code($status);

    header('Content-Type: application/json; charset=utf-8');

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
| AUTHENTIFICATION
|--------------------------------------------------------------------------
*/

if (!isLoggedIn()) {

    jsonResponse(
        false,
        'Utilisateur non authentifié.',
        [],
        401
    );

}


$user = getCurrentUser();


if (!$user) {

    session_destroy();

    jsonResponse(
        false,
        'Session utilisateur invalide.',
        [],
        401
    );

}


$userId = (int)$user['id'];


/*
|--------------------------------------------------------------------------
| MÉTHODE
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    jsonResponse(
        false,
        'Méthode non autorisée.',
        [],
        405
    );

}


/*
|--------------------------------------------------------------------------
| CONFIGURATION CHARIOW
|--------------------------------------------------------------------------
*/

$chariowConfig = require '../../config/chariow.php';


$apiKey =
    trim(
        (string)(
            $chariowConfig['api_key']
            ?? getenv('CHARIOW_API_KEY')
            ?? ''
        )
    );


if (
    $apiKey === ''
    || $apiKey === 'REMPLACE_PAR_TA_CLE_API'
) {

    jsonResponse(
        false,
        'La clé API Chariow n’est pas configurée.',
        [],
        500
    );

}


/*
|--------------------------------------------------------------------------
| PRODUITS DE DÉPÔT
|--------------------------------------------------------------------------
|
| Le montant envoyé par le navigateur n'est PAS considéré
| comme fiable.
|
| On utilise uniquement cette liste serveur.
|
*/

$depositProducts = [

    'prd_j3tlbau7' => [
        'amount' => 4000,
        'name'   => 'Dépôt 4 000 FCFA'
    ],

    'prd_qip2y4' => [
        'amount' => 5000,
        'name'   => 'Dépôt 5 000 FCFA'
    ],

    'prd_ubsw2b8q' => [
        'amount' => 8000,
        'name'   => 'Dépôt 8 000 FCFA'
    ],

    'prd_701hymm6' => [
        'amount' => 10000,
        'name'   => 'Dépôt 10 000 FCFA'
    ],

    'prd_v28utfkz' => [
        'amount' => 15000,
        'name'   => 'Dépôt 15 000 FCFA'
    ],

    'prd_d0dp630n' => [
        'amount' => 20000,
        'name'   => 'Dépôt 20 000 FCFA'
    ],

    'prd_kxrgg539' => [
        'amount' => 30000,
        'name'   => 'Dépôt 30 000 FCFA'
    ],

    'prd_l7qwk6' => [
        'amount' => 50000,
        'name'   => 'Dépôt 50 000 FCFA'
    ],

    'prd_tijd2g' => [
        'amount' => 100000,
        'name'   => 'Dépôt 100 000 FCFA'
    ]

];


/*
|--------------------------------------------------------------------------
| PRODUCT ID
|--------------------------------------------------------------------------
*/

$productId =
    trim(
        (string)(
            $_POST['product_id']
            ?? ''
        )
    );


if (
    $productId === ''
    || !isset($depositProducts[$productId])
) {

    jsonResponse(
        false,
        'Produit de dépôt invalide.',
        [],
        422
    );

}


$product =
    $depositProducts[$productId];


$amount =
    (float)$product['amount'];


/*
|--------------------------------------------------------------------------
| INFORMATIONS UTILISATEUR
|--------------------------------------------------------------------------
*/

$email =
    trim(
        (string)(
            $user['email']
            ?? ''
        )
    );


$username =
    trim(
        (string)(
            $user['username']
            ?? ''
        )
    );


$countryCode =
    strtoupper(
        trim(
            (string)(
                $user['country_code']
                ?? 'CM'
            )
        )
    );


$currencyCode =
    strtoupper(
        trim(
            (string)(
                $user['currency_code']
                ?? 'XAF'
            )
        )
    );


if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

    jsonResponse(
        false,
        'L’adresse email de votre compte est invalide.',
        [],
        422
    );

}


/*
|--------------------------------------------------------------------------
| PRÉNOM / NOM
|--------------------------------------------------------------------------
|
| Ta table users possède username mais pas first_name / last_name.
|
| On essaie donc de découper username.
|
*/

$nameParts =
    preg_split(
        '/\s+/',
        $username,
        -1,
        PREG_SPLIT_NO_EMPTY
    );

if (!$nameParts) {
    $nameParts = ['Client'];
}

$firstName =
    ucfirst(
        strtolower(
            (string)$nameParts[0]
        )
    );

if (count($nameParts) > 1) {

    $lastName =
        ucfirst(
            strtolower(
                implode(
                    ' ',
                    array_slice(
                        $nameParts,
                        1
                    )
                )
            )
        );

} else {

    $lastName = 'Client';

}


/*
|--------------------------------------------------------------------------
| TÉLÉPHONE DE L'UTILISATEUR
|--------------------------------------------------------------------------
|
| Le numéro est déjà enregistré dans users.phone.
| On ne demande donc PAS le numéro au navigateur.
|
*/

$phoneNumber = trim(
    (string)($user['phone'] ?? '')
);

$phoneNumber = preg_replace(
    '/[^0-9+]/',
    '',
    $phoneNumber
);

if ($phoneNumber === '') {

    jsonResponse(
        false,
        'Votre numéro de téléphone est requis pour effectuer le paiement.',
        [
            'field' => 'phone'
        ],
        422
    );

}


/*
|--------------------------------------------------------------------------
| NORMALISATION TÉLÉPHONE
|--------------------------------------------------------------------------
|
| Si l'utilisateur saisit +237..., on enlève le +237
| car Chariow reçoit le pays séparément.
|
*/

$dialCodes = [

    'CM' => '+237',
    'CI' => '+225',
    'BJ' => '+229',
    'BF' => '+226',
    'TG' => '+228',
    'SN' => '+221',
    'CD' => '+243',
    'CG' => '+242',
    'GA' => '+241'

];


if (
    isset($dialCodes[$countryCode])
    && str_starts_with(
        $phoneNumber,
        $dialCodes[$countryCode]
    )
) {

    $phoneNumber =
        substr(
            $phoneNumber,
            strlen(
                $dialCodes[$countryCode]
            )
        );

}


/*
|--------------------------------------------------------------------------
| RÉFÉRENCE INTERNE
|--------------------------------------------------------------------------
*/

try {

    $reference =
        'DEP-' .
        date('YmdHis') .
        '-' .
        strtoupper(
            bin2hex(
                random_bytes(4)
            )
        );

} catch (Throwable $e) {

    jsonResponse(
        false,
        'Impossible de générer la référence du dépôt.',
        [],
        500
    );

}


/*
|--------------------------------------------------------------------------
| CRÉATION TRANSACTION LOCALE
|--------------------------------------------------------------------------
*/

try {

    $pdo->beginTransaction();


    $stmt =
        $pdo->prepare("
            INSERT INTO transactions (
                user_id,
                type,
                amount,
                fee,
                status,
                payment_method,
                reference,
                description
            )
            VALUES (
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


    $description =
        'Dépôt de ' .
        number_format(
            $amount,
            0,
            ',',
            ' '
        ) .
        ' FCFA via Chariow - produit ' .
        $productId;


    $stmt->execute([

        $userId,

        $amount,

        $reference,

        $description

    ]);


    $transactionId =
        (int)$pdo->lastInsertId();


    $pdo->commit();

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {

        $pdo->rollBack();

    }


    jsonResponse(
        false,
        'Impossible de créer la transaction.',
        [],
        500
    );

}


/*
|--------------------------------------------------------------------------
| PAYLOAD CHARIOW
|--------------------------------------------------------------------------
*/

$payload = [

    'product_id' => $productId,

    'email' => $email,

    'first_name' => $firstName,

    'last_name' => $lastName,

    'phone' => [

        'number' => $phoneNumber,

        'country_code' => $countryCode

    ]

];


/*
|--------------------------------------------------------------------------
| APPEL API CHARIOW
|--------------------------------------------------------------------------
*/

$apiUrl =
    rtrim(
        $chariowConfig['api_base_url']
            ?? 'https://api.chariow.com',
        '/'
    );

$endpoint =
    $chariowConfig['checkout_endpoint']
        ?? '/v1/checkout';

$endpoint =
    $apiUrl . '/' . ltrim($endpoint, '/');


/*
|--------------------------------------------------------------------------
| ENCODAGE JSON
|--------------------------------------------------------------------------
|
| Chariow attend un body JSON. Le passage en
| application/x-www-form-urlencoded n'était pas la cause du 422
| (le vrai problème était un caractère parasite dans la clé API,
| corrigé plus haut avec trim()). On garde donc du JSON, qui est
| le format natif attendu par l'API.
|
*/

$jsonPayload =
    json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES |
        JSON_THROW_ON_ERROR
    );


$ch = curl_init($endpoint);

curl_setopt_array($ch, [

    CURLOPT_POST => true,

    CURLOPT_POSTFIELDS => $jsonPayload,

    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $apiKey,
        'Accept: application/json',
        'Content-Type: application/json',
        'Content-Length: ' . strlen($jsonPayload),
        'User-Agent: InvestPro-Chariow/1.0',
        'Expect:'
    ],

    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,

    CURLOPT_HEADER => false,

    CURLINFO_HEADER_OUT => true,

    CURLOPT_RETURNTRANSFER => true,

    CURLOPT_CONNECTTIMEOUT => 10,

    CURLOPT_TIMEOUT => (int)($chariowConfig['timeout'] ?? 30)

]);

$response = curl_exec($ch);

$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
$redirectCount = curl_getinfo($ch, CURLINFO_REDIRECT_COUNT);
$sentHeaders = curl_getinfo($ch, CURLINFO_HEADER_OUT);
$curlError = curl_error($ch);

curl_close($ch);

error_log('CHARIOW URL: ' . $endpoint);
error_log('CHARIOW JSON REQUEST');
error_log('CHARIOW BODY LENGTH: ' . strlen($jsonPayload));
error_log('CHARIOW EFFECTIVE URL: ' . $effectiveUrl);
error_log('CHARIOW REDIRECT COUNT: ' . $redirectCount);
error_log('CHARIOW SENT HEADERS: ' . str_replace("\r\n", "\\r\\n", (string)$sentHeaders));
error_log('=== CHARIOW DEBUG ===');
error_log('HTTP CODE: ' . $httpCode);
error_log('RESPONSE: ' . (string)$response);
error_log('CURL ERROR: ' . $curlError);
error_log('=== END CHARIOW DEBUG ===');


/*
|--------------------------------------------------------------------------
| ERREUR CURL
|--------------------------------------------------------------------------
*/

if ($response === false) {

    $stmt =
        $pdo->prepare("
            UPDATE transactions
            SET
                status = 'failed',
                description = CONCAT(
                    description,
                    ' | Erreur Chariow: ',
                    ?
                )
            WHERE id = ?
        ");

    $stmt->execute([
        $curlError ?: 'Erreur inconnue',
        $transactionId
    ]);


    jsonResponse(
        false,
        'Impossible de contacter Chariow.',
        [
            'reference' => $reference
        ],
        502
    );

}


/*
|--------------------------------------------------------------------------
| DÉCODAGE
|--------------------------------------------------------------------------
*/

$responseData =
    json_decode(
        $response,
        true
    );


if (!is_array($responseData)) {

    jsonResponse(
        false,
        'Chariow a retourné une réponse invalide.',
        [
            'reference' => $reference,
            'http_code' => $httpCode
        ],
        502
    );

}


/*
|--------------------------------------------------------------------------
| ERREUR CHARIOW
|--------------------------------------------------------------------------
*/

if (
    $httpCode < 200
    || $httpCode >= 300
) {

    $errorMessage =
        $responseData['message']
        ?? 'Chariow a refusé la création du paiement.';

    /*
    |--------------------------------------------------------------------------
    | DEBUG CHARIOW
    |--------------------------------------------------------------------------
    */

    $debugFile = __DIR__ . '/../../chariow_debug.json';

    $debugData = [
        'date' => date('Y-m-d H:i:s'),
        'http_code' => $httpCode,
        'reference' => $reference,
        'product_id' => $productId,
        'payload' => $payload,
        'response' => $responseData,
        'curl_error' => $curlError
    ];

    file_put_contents(
        $debugFile,
        json_encode(
            $debugData,
            JSON_PRETTY_PRINT |
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
        )
    );


    $stmt =
        $pdo->prepare("
            UPDATE transactions
            SET
                status = 'failed',
                description = CONCAT(
                    description,
                    ' | ',
                    ?
                )
            WHERE id = ?
        ");


    $stmt->execute([
        $errorMessage,
        $transactionId
    ]);


    jsonResponse(
        false,
        $errorMessage,
        [
            'reference' => $reference,
            'http_code' => $httpCode,
            'errors' =>
                $responseData['errors']
                ?? []
        ],
        422
    );

}


/*
|--------------------------------------------------------------------------
| RÉCUPÉRATION CHECKOUT URL
|--------------------------------------------------------------------------
*/

$checkoutUrl =
    $responseData['data']['payment']['checkout_url']
    ?? null;


$chariowSaleId =
    $responseData['data']['purchase']['id']
    ?? null;


$chariowTransactionId =
    $responseData['data']['payment']['transaction_id']
    ?? null;


if (!$checkoutUrl) {

    $stmt =
        $pdo->prepare("
            UPDATE transactions
            SET status = 'failed'
            WHERE id = ?
        ");


    $stmt->execute([
        $transactionId
    ]);


    jsonResponse(
        false,
        'Chariow n’a pas retourné de page de paiement.',
        [
            'reference' => $reference
        ],
        502
    );

}


/*
|--------------------------------------------------------------------------
| STOCKAGE TEMPORAIRE EN SESSION
|--------------------------------------------------------------------------
|
| Ta table transactions ne possède pas de colonne payment_url.
| On évite donc de modifier la structure de ta base.
|
*/

if (!isset($_SESSION['chariow_checkouts'])) {

    $_SESSION['chariow_checkouts'] = [];

}


$_SESSION['chariow_checkouts'][$reference] = [

    'checkout_url' =>
        $checkoutUrl,

    'product_id' =>
        $productId,

    'transaction_id' =>
        $transactionId,

    'chariow_sale_id' =>
        $chariowSaleId,

    'chariow_transaction_id' =>
        $chariowTransactionId,

    'amount' =>
        $amount,

    'created_at' =>
        time()

];


/*
|--------------------------------------------------------------------------
| NETTOYAGE DES ANCIENS CHECKOUTS
|--------------------------------------------------------------------------
*/

foreach (
    $_SESSION['chariow_checkouts']
    as $key => $checkout
) {

    if (
        isset($checkout['created_at'])
        && (
            time()
            - (int)$checkout['created_at']
        ) > 1800
    ) {

        unset(
            $_SESSION['chariow_checkouts'][$key]
        );

    }

}


/*
|--------------------------------------------------------------------------
| SUCCÈS
|--------------------------------------------------------------------------
|
| Le navigateur doit d'abord aller vers loading.php.
| loading.php récupérera ensuite checkout_url depuis la session.
|
*/

$loadingUrl =
    '../../pages/dashboard/loading.php'
    . '?reference=' .
    urlencode($reference);


jsonResponse(
    true,
    'Paiement créé avec succès.',
    [

        'transaction_id' =>
            $transactionId,

        'reference' =>
            $reference,

        'product_id' =>
            $productId,

        'amount' =>
            $amount,

        'currency' =>
            $currencyCode,

        'chariow_sale_id' =>
            $chariowSaleId,

        'chariow_transaction_id' =>
            $chariowTransactionId,

        'payment_url' =>
            $loadingUrl

    ]
);
