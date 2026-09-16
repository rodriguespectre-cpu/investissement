<?php

session_start();

require_once '../../config/database.php';

header('Content-Type: application/json; charset=utf-8');


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

    jsonResponse(
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

    jsonResponse(
        false,
        'Méthode non autorisée.',
        [],
        405
    );

}


/*
|--------------------------------------------------------------------------
| PRODUIT
|--------------------------------------------------------------------------
*/

$productId = trim(
    $_POST['product_id'] ?? ''
);


if ($productId === '') {

    jsonResponse(
        false,
        'Produit manquant.',
        [],
        422
    );

}


/*
|--------------------------------------------------------------------------
| CONFIGURATION CHARIOW
|--------------------------------------------------------------------------
*/

$config = require '../../config/chariow.php';


$products =
    $config['deposit_products']
    ?? [];


/*
|--------------------------------------------------------------------------
| VÉRIFICATION PRODUIT
|--------------------------------------------------------------------------
*/

if (!isset($products[$productId])) {

    jsonResponse(
        false,
        'Ce produit n’est pas autorisé pour un dépôt.',
        [],
        422
    );

}


$product =
    $products[$productId];


$amount =
    (float) $product['amount'];


/*
|--------------------------------------------------------------------------
| DONNÉES CLIENT
|--------------------------------------------------------------------------
*/

$email =
    trim($user['email'] ?? '');


$firstName =
    trim(
        $user['first_name']
        ?? ''
    );


$lastName =
    trim(
        $user['last_name']
        ?? ''
    );


$phone =
    trim(
        $user['phone']
        ?? $user['phone_number']
        ?? ''
    );


/*
|--------------------------------------------------------------------------
| VALIDATION CLIENT
|--------------------------------------------------------------------------
*/

if ($email === '') {

    jsonResponse(
        false,
        'Votre adresse email est manquante.',
        [],
        422
    );

}


if ($firstName === '') {

    $firstName = 'InvestPro';

}


if ($lastName === '') {

    $lastName = 'User';

}


/*
|--------------------------------------------------------------------------
| PAYS
|--------------------------------------------------------------------------
*/

$countryCode =
    strtoupper(
        trim(
            $user['country_code']
            ?? 'CM'
        )
    );


/*
|--------------------------------------------------------------------------
| NUMÉRO
|--------------------------------------------------------------------------
*/

if ($phone === '') {

    jsonResponse(
        false,
        'Votre numéro de téléphone est requis pour le paiement.',
        [],
        422
    );

}


/*
|--------------------------------------------------------------------------
| RÉFÉRENCE INVESTPRO
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
        'Impossible de générer la référence.',
        [],
        500
    );

}


/*
|--------------------------------------------------------------------------
| CRÉATION TRANSACTION
|--------------------------------------------------------------------------
*/

try {

    $pdo->beginTransaction();


    $description =
        'Dépôt InvestPro - ' .
        $product['name'] .
        ' - ' .
        number_format(
            $amount,
            0,
            ',',
            ' '
        ) .
        ' XAF';


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

        $amount,

        $reference,

        $description

    ]);


    $transactionId =
        (int) $pdo->lastInsertId();


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

        'number' => $phone,

        'country_code' => $countryCode

    ],

    'custom_metadata' => [

        'investpro_user_id' =>
            (string) $userId,

        'investpro_transaction_id' =>
            (string) $transactionId,

        'investpro_reference' =>
            $reference,

        'deposit_amount' =>
            (string) $amount

    ]

];


/*
|--------------------------------------------------------------------------
| URL API
|--------------------------------------------------------------------------
*/

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
    ltrim($endpoint, '/');


$apiKey =
    $config['api_key']
    ?? getenv('CHARIOW_API_KEY')
    ?? '';


if ($apiKey === '') {

    jsonResponse(
        false,
        'Clé API Chariow non configurée.',
        [],
        500
    );

}


/*
|--------------------------------------------------------------------------
| APPEL CHARIOW
|--------------------------------------------------------------------------
*/

$ch = curl_init($apiUrl);


curl_setopt_array(
    $ch,
    [

        CURLOPT_POST => true,

        CURLOPT_POSTFIELDS =>
            json_encode(
                $payload,
                JSON_UNESCAPED_UNICODE |
                JSON_UNESCAPED_SLASHES
            ),

        CURLOPT_HTTPHEADER => [

            'Authorization: Bearer ' . $apiKey,

            'Accept: application/json',

            'Content-Type: application/json'

        ],

        CURLOPT_RETURNTRANSFER => true,

        CURLOPT_CONNECTTIMEOUT => 10,

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


curl_close($ch);


/*
|--------------------------------------------------------------------------
| ERREUR CURL
|--------------------------------------------------------------------------
*/

if ($response === false) {

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

$data =
    json_decode(
        $response,
        true
    );


if (!is_array($data)) {

    jsonResponse(
        false,
        'Réponse invalide de Chariow.',
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
    ||
    $httpCode >= 300
) {

    jsonResponse(
        false,
        $data['message']
            ?? 'Chariow a refusé la création du paiement.',
        [
            'reference' => $reference,
            'errors' =>
                $data['errors']
                ?? []
        ],
        502
    );

}


/*
|--------------------------------------------------------------------------
| RÉCUPÉRATION CHECKOUT
|--------------------------------------------------------------------------
*/

$purchase =
    $data['data']['purchase']
    ?? [];


$payment =
    $data['data']['payment']
    ?? [];


$checkoutUrl =
    $payment['checkout_url']
    ?? null;


$chariowSaleId =
    $purchase['id']
    ?? null;


$chariowPaymentId =
    $payment['transaction_id']
    ?? null;


/*
|--------------------------------------------------------------------------
| VÉRIFICATION
|--------------------------------------------------------------------------
*/

if (!$checkoutUrl) {

    jsonResponse(
        false,
        'Chariow n’a pas retourné de lien de paiement.',
        [
            'reference' =>
                $reference
        ],
        502
    );

}


/*
|--------------------------------------------------------------------------
| SUCCÈS
|--------------------------------------------------------------------------
*/

jsonResponse(

    true,

    'Checkout Chariow créé.',

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
            'XAF',

        'chariow_sale_id' =>
            $chariowSaleId,

        'chariow_payment_id' =>
            $chariowPaymentId,

        'payment_url' =>
            $checkoutUrl

    ]

);
