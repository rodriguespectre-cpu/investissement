<?php

declare(strict_types=1);

$apiKey = getenv('CHARIOW_API_KEY');

if (!$apiKey) {
    die("ERREUR : CHARIOW_API_KEY n'est pas définie.\n");
}

$url = 'https://api.chariow.com/v1/checkout';

$payload = [
    'product_id' => 'prd_j3tlbau7',
    'email' => 'rodriguespectre@gmail.com',
    'first_name' => 'Test',
    'last_name' => 'Utilisateur',
    'phone' => [
        'number' => '690000000',
        'country_code' => 'CM'
    ]
];

$json = json_encode(
    $payload,
    JSON_UNESCAPED_UNICODE |
    JSON_UNESCAPED_SLASHES
);

$ch = curl_init($url);

curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $json,

    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $apiKey,
        'Accept: application/json',
        'Content-Type: application/json'
    ],

    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HEADER => false,

    CURLOPT_CONNECTTIMEOUT => 15,
    CURLOPT_TIMEOUT => 30
]);

$response = curl_exec($ch);

$curlError = curl_error($ch);

$httpCode = curl_getinfo(
    $ch,
    CURLINFO_HTTP_CODE
);

$contentType = curl_getinfo(
    $ch,
    CURLINFO_CONTENT_TYPE
);

curl_close($ch);

echo "\n==============================\n";
echo "       TEST CHARIOW\n";
echo "==============================\n";

echo "URL : $url\n";
echo "HTTP : $httpCode\n";
echo "Content-Type : " . ($contentType ?: 'inconnu') . "\n";

echo "\nCURL ERROR :\n";
echo ($curlError ?: 'Aucune') . "\n";

echo "\nREPONSE BRUTE :\n";
echo ($response ?: '[VIDE]') . "\n";

echo "\n==============================\n";

