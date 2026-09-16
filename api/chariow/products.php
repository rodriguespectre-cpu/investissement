<?php

header('Content-Type: application/json; charset=utf-8');


/*
|--------------------------------------------------------------------------
| CONFIGURATION
|--------------------------------------------------------------------------
*/

$config = require '../../config/chariow.php';



/*
|--------------------------------------------------------------------------
| APPEL API CHARIOW
|--------------------------------------------------------------------------
*/

$curl = curl_init();


curl_setopt_array($curl, [

    CURLOPT_URL =>
        $config['api_url'] . '/products',

    CURLOPT_RETURNTRANSFER =>
        true,

    CURLOPT_HTTPHEADER => [

        'Authorization: Bearer ' .
            $config['api_key'],

        'Accept: application/json'

    ],

    CURLOPT_TIMEOUT => 30

]);



$response = curl_exec($curl);



if (curl_errno($curl)) {

    echo json_encode([

        'success' => false,

        'error' =>
            curl_error($curl)

    ]);

    exit;

}



curl_close($curl);



/*
|--------------------------------------------------------------------------
| TRAITEMENT REPONSE
|--------------------------------------------------------------------------
*/

$data = json_decode(
    $response,
    true
);



if (!is_array($data)) {

    echo json_encode([

        'success' => false,

        'error' =>
            'Réponse Chariow invalide'

    ]);

    exit;

}



/*
|--------------------------------------------------------------------------
| RETOUR PROPRE
|--------------------------------------------------------------------------
*/

echo json_encode([

    'success' => true,

    'total' =>
        count($data['data'] ?? []),

    'products' =>
        $data['data'] ?? []

],

JSON_PRETTY_PRINT |

JSON_UNESCAPED_UNICODE |

JSON_UNESCAPED_SLASHES
);
