<?php

declare(strict_types=1);

session_start();

require_once '../../config/database.php';


/*
|--------------------------------------------------------------------------
| AUTHENTIFICATION
|--------------------------------------------------------------------------
*/

if (!isLoggedIn()) {

    header('Location: ../login.php');

    exit;

}


/*
|--------------------------------------------------------------------------
| RÉFÉRENCE
|--------------------------------------------------------------------------
*/

$reference =
    trim(
        (string)(
            $_GET['reference']
            ?? ''
        )
    );


if ($reference === '') {

    http_response_code(400);

    exit('Référence de paiement manquante.');

}


/*
|--------------------------------------------------------------------------
| CHECKOUT CHARIOW
|--------------------------------------------------------------------------
*/

$checkout =
    $_SESSION['chariow_checkouts'][$reference]
    ?? null;


if (
    !is_array($checkout)
    || empty($checkout['checkout_url'])
) {

    http_response_code(404);

    exit(
        'Session de paiement introuvable ou expirée.'
    );

}


$checkoutUrl =
    (string)$checkout['checkout_url'];


$amount =
    (float)(
        $checkout['amount']
        ?? 0
    );


$productId =
    (string)(
        $checkout['product_id']
        ?? ''
    );


/*
|--------------------------------------------------------------------------
| NETTOYAGE AUTOMATIQUE
|--------------------------------------------------------------------------
*/

unset(
    $_SESSION['chariow_checkouts'][$reference]
);


/*
|--------------------------------------------------------------------------
| FORMATAGE
|--------------------------------------------------------------------------
*/

$formattedAmount =
    number_format(
        $amount,
        0,
        ',',
        ' '
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

<title>
    Préparation du paiement | InvestPro
</title>


<style>

/*
|--------------------------------------------------------------------------
| BASE
|--------------------------------------------------------------------------
*/

* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}


html,
body {

    width: 100%;
    min-height: 100%;

}


body {

    min-height: 100vh;

    display: flex;

    align-items: center;

    justify-content: center;

    padding: 24px;

    background:
        radial-gradient(
            circle at 50% 0%,
            rgba(70, 90, 160, .18),
            transparent 45%
        ),
        #080a10;

    color: #ffffff;

    font-family:
        Inter,
        system-ui,
        -apple-system,
        BlinkMacSystemFont,
        "Segoe UI",
        sans-serif;

    overflow: hidden;

}


/*
|--------------------------------------------------------------------------
| CONTAINER
|--------------------------------------------------------------------------
*/

.loading-page {

    width: 100%;

    max-width: 480px;

}


.loading-card {

    position: relative;

    overflow: hidden;

    padding: 42px 28px 34px;

    text-align: center;

    border: 1px solid rgba(255,255,255,.08);

    border-radius: 28px;

    background:
        linear-gradient(
            145deg,
            rgba(24,28,40,.96),
            rgba(12,14,21,.98)
        );

    box-shadow:
        0 30px 80px rgba(0,0,0,.45),
        inset 0 1px 0 rgba(255,255,255,.04);

}


/*
|--------------------------------------------------------------------------
| LUEUR
|--------------------------------------------------------------------------
*/

.loading-card::before {

    content: "";

    position: absolute;

    width: 220px;

    height: 220px;

    left: 50%;

    top: -150px;

    transform: translateX(-50%);

    background:
        radial-gradient(
            circle,
            rgba(92,124,255,.24),
            transparent 70%
        );

    pointer-events: none;

}


/*
|--------------------------------------------------------------------------
| LOGO ICON
|--------------------------------------------------------------------------
*/

.payment-icon {

    position: relative;

    width: 82px;

    height: 82px;

    margin: 0 auto 26px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 50%;

    background:
        linear-gradient(
            145deg,
            #1b2340,
            #101522
        );

    border: 1px solid rgba(120,145,255,.25);

    box-shadow:
        0 0 0 8px rgba(100,120,255,.04),
        0 0 40px rgba(80,110,255,.12);

}


.payment-icon i {

    font-size: 30px;

    color: #8da5ff;

}


/*
|--------------------------------------------------------------------------
| SPINNER
|--------------------------------------------------------------------------
*/

.spinner {

    position: absolute;

    inset: -9px;

    border-radius: 50%;

    border: 2px solid transparent;

    border-top-color: #829cff;

    border-right-color: rgba(130,156,255,.25);

    animation:
        spin 1.05s linear infinite;

}


@keyframes spin {

    to {
        transform: rotate(360deg);
    }

}


/*
|--------------------------------------------------------------------------
| TITRE
|--------------------------------------------------------------------------
*/

.loading-title {

    font-size: 23px;

    font-weight: 700;

    letter-spacing: -.4px;

    margin-bottom: 10px;

}


.loading-message {

    color: #949baa;

    font-size: 14px;

    line-height: 1.6;

    min-height: 44px;

}


/*
|--------------------------------------------------------------------------
| MONTANT
|--------------------------------------------------------------------------
*/

.amount-box {

    margin: 28px 0;

    padding: 16px;

    border-radius: 16px;

    background:
        rgba(255,255,255,.035);

    border:
        1px solid rgba(255,255,255,.06);

}


.amount-label {

    display: block;

    margin-bottom: 5px;

    color: #777f91;

    font-size: 12px;

}


.amount {

    font-size: 22px;

    font-weight: 750;

    color: #ffffff;

}


/*
|--------------------------------------------------------------------------
| ÉTAPES
|--------------------------------------------------------------------------
*/

.steps {

    display: flex;

    justify-content: space-between;

    margin-top: 28px;

}


.step {

    flex: 1;

    position: relative;

    color: #626a7c;

    font-size: 11px;

}


.step:not(:last-child)::after {

    content: "";

    position: absolute;

    top: 7px;

    left: calc(50% + 13px);

    width: calc(100% - 26px);

    height: 1px;

    background:
        rgba(255,255,255,.08);

}


.step-circle {

    position: relative;

    z-index: 2;

    width: 15px;

    height: 15px;

    margin: 0 auto 8px;

    border-radius: 50%;

    background: #252a36;

    border: 2px solid #3b4252;

}


.step.active {

    color: #9eafff;

}


.step.active .step-circle {

    background: #718cff;

    border-color: #9badff;

    box-shadow:
        0 0 14px rgba(113,140,255,.45);

}


/*
|--------------------------------------------------------------------------
| SÉCURITÉ
|--------------------------------------------------------------------------
*/

.security {

    margin-top: 30px;

    display: flex;

    align-items: center;

    justify-content: center;

    gap: 8px;

    color: #697183;

    font-size: 11px;

}


.security i {

    color: #6e87ed;

}


/*
|--------------------------------------------------------------------------
| BOUTON SECOURS
|--------------------------------------------------------------------------
*/

.manual-link {

    display: none;

    margin-top: 20px;

    color: #9eafff;

    font-size: 13px;

    text-decoration: none;

}


.manual-link.show {

    display: inline-block;

}


.manual-link:hover {

    text-decoration: underline;

}


</style>

</head>


<body>


<main class="loading-page">


    <section class="loading-card">


        <!-- ICON -->

        <div class="payment-icon">

            <div class="spinner"></div>

            <i class="fa-solid fa-lock"></i>

        </div>


        <!-- TITRE -->

        <h1
            class="loading-title"
            id="loadingTitle"
        >
            Préparation du paiement
        </h1>


        <!-- MESSAGE -->

        <p
            class="loading-message"
            id="loadingMessage"
        >
            Connexion sécurisée au service de paiement...
        </p>


        <!-- MONTANT -->

        <div class="amount-box">

            <span class="amount-label">
                Montant du dépôt
            </span>

            <strong class="amount">

                <?= htmlspecialchars(
                    $formattedAmount
                ) ?>

                FCFA

            </strong>

        </div>


        <!-- ÉTAPES -->

        <div class="steps">


            <div
                class="step active"
                id="step1"
            >

                <div class="step-circle"></div>

                Préparation

            </div>


            <div
                class="step"
                id="step2"
            >

                <div class="step-circle"></div>

                Sécurisation

            </div>


            <div
                class="step"
                id="step3"
            >

                <div class="step-circle"></div>

                Paiement

            </div>


        </div>


        <!-- SÉCURITÉ -->

        <div class="security">

            <i class="fa-solid fa-shield-halved"></i>

            Redirection sécurisée

        </div>


        <!-- SECOURS -->

        <a
            href="<?= htmlspecialchars(
                $checkoutUrl,
                ENT_QUOTES,
                'UTF-8'
            ) ?>"
            id="manualLink"
            class="manual-link"
        >
            Continuer vers le paiement
        </a>


    </section>

</main>


<script>

/*
|--------------------------------------------------------------------------
| DONNÉES
|--------------------------------------------------------------------------
*/

const checkoutUrl =
    <?= json_encode(
        $checkoutUrl,
        JSON_UNESCAPED_SLASHES |
        JSON_UNESCAPED_UNICODE
    ) ?>;


/*
|--------------------------------------------------------------------------
| ÉLÉMENTS
|--------------------------------------------------------------------------
*/

const title =
    document.getElementById(
        "loadingTitle"
    );

const message =
    document.getElementById(
        "loadingMessage"
    );

const step1 =
    document.getElementById(
        "step1"
    );

const step2 =
    document.getElementById(
        "step2"
    );

const step3 =
    document.getElementById(
        "step3"
    );

const manualLink =
    document.getElementById(
        "manualLink"
    );


/*
|--------------------------------------------------------------------------
| MESSAGE
|--------------------------------------------------------------------------
*/

function updateMessage(
    newTitle,
    newMessage
) {

    if (title) {

        title.textContent =
            newTitle;

    }

    if (message) {

        message.textContent =
            newMessage;

    }

}


/*
|--------------------------------------------------------------------------
| ÉTAPE
|--------------------------------------------------------------------------
*/

function activateStep(step) {

    if (!step) {
        return;
    }

    step.classList.add(
        "active"
    );

}


/*
|--------------------------------------------------------------------------
| ANIMATION
|--------------------------------------------------------------------------
*/

setTimeout(() => {

    activateStep(step2);

    updateMessage(
        "Sécurisation du paiement",
        "Nous préparons votre session de paiement..."
    );

}, 1100);


setTimeout(() => {

    activateStep(step3);

    updateMessage(
        "Redirection vers le paiement",
        "Vous allez être redirigé vers l’interface sécurisée."
    );

}, 2200);


/*
|--------------------------------------------------------------------------
| REDIRECTION
|--------------------------------------------------------------------------
|
| Délai volontairement court :
| l'utilisateur voit l'animation,
| puis arrive automatiquement chez Chariow.
|
*/

setTimeout(() => {

    if (
        typeof checkoutUrl !== "string"
        ||
        checkoutUrl.trim() === ""
    ) {

        updateMessage(
            "Erreur",
            "Le lien de paiement est indisponible."
        );

        if (manualLink) {

            manualLink.classList.add(
                "show"
            );

        }

        return;

    }


    window.location.replace(
        checkoutUrl
    );

}, 3000);

</script>


</body>

</html>
