<?php

session_start();

require_once '../../config/database.php';

if (!isLoggedIn()) {
    header("Location: ../login.php");
    exit;
}

$user = getCurrentUser();

if (!$user) {
    session_destroy();
    header("Location: ../login.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| PRODUITS CHARIOW UTILISÉS POUR LES DÉPÔTS
|--------------------------------------------------------------------------
*/

$depositPlans = [

    [
        'id' => 'prd_j3tlbau7',
        'name' => 'Paye4',
        'amount' => 4000,
        'description' => 'Pack Starter',
        'icon' => 'fa-bolt',
        'class' => 'starter'
    ],

    [
        'id' => 'prd_qip2y4',
        'name' => 'Prime V',
        'amount' => 5000,
        'description' => 'Pack Essentiel',
        'icon' => 'fa-star',
        'class' => 'essential'
    ],

    [
        'id' => 'prd_ubsw2b8q',
        'name' => 'Paye8',
        'amount' => 8000,
        'description' => 'Pack Silver',
        'icon' => 'fa-gem',
        'class' => 'silver'
    ],

    [
        'id' => 'prd_701hymm6',
        'name' => 'BuyTci',
        'amount' => 10000,
        'description' => 'Pack Gold',
        'icon' => 'fa-crown',
        'class' => 'gold'
    ],

    [
        'id' => 'prd_v28utfkz',
        'name' => 'Paye15',
        'amount' => 15000,
        'description' => 'Pack Premium',
        'icon' => 'fa-fire',
        'class' => 'premium'
    ],

    [
        'id' => 'prd_d0dp630n',
        'name' => 'Paye20',
        'amount' => 20000,
        'description' => 'Pack Business',
        'icon' => 'fa-chart-line',
        'class' => 'business'
    ],

    [
        'id' => 'prd_kxrgg539',
        'name' => 'Host orange',
        'amount' => 30000,
        'description' => 'Pack Elite',
        'icon' => 'fa-shield-halved',
        'class' => 'elite'
    ],

    [
        'id' => 'prd_l7qwk6',
        'name' => "Le secret de l'intimité",
        'amount' => 50000,
        'description' => 'Pack VIP',
        'icon' => 'fa-diamond',
        'class' => 'vip'
    ],

    [
        'id' => 'prd_tijd2g',
        'name' => 'Health and wellness',
        'amount' => 100000,
        'description' => 'Pack Ultimate',
        'icon' => 'fa-infinity',
        'class' => 'ultimate'
    ]

];

?><!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Dépôt | InvestPro</title>

<link
    rel="stylesheet"
    href="../../assets/deposit.css?v=10"
>

<link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
>

</head><body><div class="deposit-page"><div class="deposit-wrapper">

    <!-- HEADER -->

    <header class="deposit-header">

        <div class="header-icon">
            <i class="fas fa-wallet"></i>
        </div>

        <div class="header-content">

            <span class="eyebrow">
                INVESTPRO • FINANCEMENT
            </span>

            <h1>
                Déposer des fonds
            </h1>

            <p>
                Sélectionnez simplement le montant que vous
                souhaitez créditer sur votre compte.
            </p>

        </div>

        <div class="secure-label">

            <i class="fas fa-shield-halved"></i>

            <span>
                Paiement sécurisé
            </span>

        </div>

    </header>


    <!-- INFO -->

    <div class="deposit-info">

        <div class="info-item">

            <i class="fas fa-circle-check"></i>

            <span>
                Montants fixes
            </span>

        </div>

        <div class="info-item">

            <i class="fas fa-mobile-screen-button"></i>

            <span>
                Mobile Money
            </span>

        </div>

        <div class="info-item">

            <i class="fas fa-lock"></i>

            <span>
                Checkout Chariow
            </span>

        </div>

    </div>


    <!-- TABLEAU -->

    <section class="plans-section">

        <div class="section-heading">

            <div>

                <span class="section-label">
                    CHOISIR UN PACK
                </span>

                <h2>
                    Montants disponibles
                </h2>

            </div>

            <span class="plans-count">
                <?= count($depositPlans) ?> options
            </span>

        </div>


        <div class="deposit-grid">

            <?php foreach ($depositPlans as $index => $plan): ?>

                <article
                    class="deposit-plan <?= htmlspecialchars($plan['class']) ?>"
                    data-product-id="<?= htmlspecialchars($plan['id']) ?>"
                    data-amount="<?= (int)$plan['amount'] ?>"
                >

                    <?php if ($plan['amount'] === 10000): ?>

                        <div class="popular-badge">
                            <i class="fas fa-fire"></i>
                            Populaire
                        </div>

                    <?php endif; ?>


                    <div class="plan-top">

                        <div class="plan-icon">

                            <i class="fas <?= htmlspecialchars($plan['icon']) ?>"></i>

                        </div>

                        <span class="plan-number">
                            <?= str_pad((string)($index + 1), 2, '0', STR_PAD_LEFT) ?>
                        </span>

                    </div>


                    <div class="plan-body">

                        <span class="plan-name">
                            <?= htmlspecialchars($plan['name']) ?>
                        </span>

                        <div class="plan-price">

                            <strong>
                                <?= number_format($plan['amount'], 0, ',', ' ') ?>
                            </strong>

                            <span>
                                FCFA
                            </span>

                        </div>

                        <p>
                            <?= htmlspecialchars($plan['description']) ?>
                        </p>

                    </div>


                    <button
                        type="button"
                        class="deposit-btn"
                        data-product-id="<?= htmlspecialchars($plan['id']) ?>"
                        data-amount="<?= (int)$plan['amount'] ?>"
                    >

                        <span>
                            Continuer
                        </span>

                        <i class="fas fa-arrow-right"></i>

                    </button>

                </article>

            <?php endforeach; ?>

        </div>

    </section>


    <!-- PAIEMENT -->

    <div class="payment-footer">

        <div class="payment-footer-icon">

            <i class="fas fa-credit-card"></i>

        </div>

        <div>

            <strong>
                Comment fonctionne le dépôt ?
            </strong>

            <p>
                Choisissez un montant, nous préparons votre
                commande puis vous serez redirigé vers
                l'interface sécurisée de paiement Chariow.
            </p>

        </div>

    </div>


    <!-- LOADING OVERLAY -->

    <div
        id="depositLoading"
        class="deposit-loading"
        aria-hidden="true"
    >

        <div class="loading-box">

            <div class="loading-ring">

                <div class="ring-core">
                    <i class="fas fa-wallet"></i>
                </div>

            </div>

            <span class="loading-label">
                INVESTPRO
            </span>

            <h3 id="loadingTitle">
                Préparation du paiement
            </h3>

            <p id="loadingMessage">
                Connexion sécurisée à Chariow...
            </p>

            <div class="loading-progress">

                <span id="loadingProgress"></span>

            </div>

            <small id="loadingAmount">
                Veuillez patienter
            </small>

        </div>

    </div>


    <!-- MESSAGE ERREUR -->

    <div
        id="depositError"
        class="deposit-error"
        role="alert"
    >

        <div class="error-icon">
            <i class="fas fa-triangle-exclamation"></i>
        </div>

        <div>

            <strong>
                Paiement impossible
            </strong>

            <p id="depositErrorMessage">
                Une erreur est survenue.
            </p>

        </div>

        <button
            type="button"
            id="closeDepositError"
        >
            <i class="fas fa-xmark"></i>
        </button>

    </div>

</div>

</div><script src="../../assets/deposit.js?v=10"></script></body></html>
