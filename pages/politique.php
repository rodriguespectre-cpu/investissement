<?php
declare(strict_types=1);

session_start();
?><!DOCTYPE html><html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Politique & Conditions — InvestPro</title>

<meta
    name="description"
    content="Politique de confidentialité et conditions d'utilisation d'InvestPro."
>

<link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css"
>

<style>
    * {
        box-sizing: border-box;
        margin: 0;
        padding: 0;
    }

    body {
        font-family:
            Inter,
            -apple-system,
            BlinkMacSystemFont,
            "Segoe UI",
            sans-serif;

        background: #f6f8fb;
        color: #172033;
        line-height: 1.7;
    }

    .page {
        min-height: 100vh;
    }

    /* HEADER */

    .header {
        background: #ffffff;
        border-bottom: 1px solid #e9edf3;
        position: sticky;
        top: 0;
        z-index: 20;
    }

    .header-inner {
        max-width: 1100px;
        margin: auto;
        padding: 18px 22px;

        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 20px;
    }

    .brand {
        display: flex;
        align-items: center;
        gap: 11px;

        text-decoration: none;
        color: #111827;
        font-weight: 800;
        font-size: 20px;
    }

    .brand-icon {
        width: 40px;
        height: 40px;

        display: flex;
        align-items: center;
        justify-content: center;

        border-radius: 12px;
        background: linear-gradient(
            135deg,
            #2563eb,
            #4f46e5
        );

        color: #fff;
    }

    .back-link {
        display: inline-flex;
        align-items: center;
        gap: 8px;

        text-decoration: none;
        color: #4b5563;
        font-size: 14px;
        font-weight: 600;

        padding: 9px 13px;
        border-radius: 10px;

        transition: .2s ease;
    }

    .back-link:hover {
        background: #f1f5f9;
        color: #2563eb;
    }

    /* CONTENT */

    .content {
        max-width: 900px;
        margin: 45px auto;
        padding: 0 20px;
    }

    .hero {
        background: #ffffff;
        border: 1px solid #e8edf4;
        border-radius: 22px;
        padding: 42px;

        box-shadow:
            0 12px 35px rgba(15, 23, 42, .06);

        margin-bottom: 22px;
    }

    .hero-badge {
        display: inline-flex;
        align-items: center;
        gap: 8px;

        color: #2563eb;
        background: #eff6ff;

        padding: 7px 12px;
        border-radius: 999px;

        font-size: 12px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: .04em;

        margin-bottom: 17px;
    }

    .hero h1 {
        font-size: clamp(28px, 5vw, 42px);
        line-height: 1.15;
        color: #111827;
        margin-bottom: 14px;
    }

    .hero p {
        color: #64748b;
        max-width: 720px;
    }

    .updated {
        margin-top: 20px;
        font-size: 13px;
        color: #94a3b8;
    }

    /* SECTIONS */

    .policy-card {
        background: #ffffff;
        border: 1px solid #e8edf4;
        border-radius: 18px;
        padding: 30px;

        margin-bottom: 16px;

        box-shadow:
            0 8px 25px rgba(15, 23, 42, .04);
    }

    .section-title {
        display: flex;
        align-items: center;
        gap: 13px;

        margin-bottom: 17px;
    }

    .section-number {
        min-width: 34px;
        height: 34px;

        display: flex;
        align-items: center;
        justify-content: center;

        border-radius: 10px;

        background: #eff6ff;
        color: #2563eb;

        font-size: 13px;
        font-weight: 800;
    }

    .section-title h2 {
        font-size: 20px;
        color: #111827;
    }

    .policy-card p {
        color: #5f6b7a;
        margin-bottom: 12px;
    }

    .policy-card p:last-child {
        margin-bottom: 0;
    }

    .policy-card ul {
        padding-left: 20px;
        color: #5f6b7a;
    }

    .policy-card li {
        margin-bottom: 8px;
    }

    .important {
        margin-top: 18px;
        padding: 16px 18px;

        background: #fff7ed;
        border: 1px solid #fed7aa;

        border-radius: 13px;
        color: #9a3412;

        display: flex;
        gap: 11px;
    }

    .important i {
        margin-top: 4px;
    }

    .success-box {
        margin-top: 18px;
        padding: 16px 18px;

        background: #f0fdf4;
        border: 1px solid #bbf7d0;

        border-radius: 13px;
        color: #166534;

        display: flex;
        gap: 11px;
    }

    /* FOOTER */

    footer {
        text-align: center;
        padding: 35px 20px;
        color: #94a3b8;
        font-size: 13px;
    }

    footer strong {
        color: #64748b;
    }

    @media (max-width: 600px) {

        .header-inner {
            padding: 14px 16px;
        }

        .brand {
            font-size: 18px;
        }

        .brand-icon {
            width: 36px;
            height: 36px;
        }

        .content {
            margin: 25px auto;
            padding: 0 14px;
        }

        .hero {
            padding: 27px 21px;
            border-radius: 18px;
        }

        .policy-card {
            padding: 23px 19px;
            border-radius: 16px;
        }

        .section-title h2 {
            font-size: 18px;
        }
    }
</style>

</head><body><div class="page"><header class="header">

    <div class="header-inner">

        <a href="login.php" class="brand">

            <span class="brand-icon">
                <i class="fas fa-chart-line"></i>
            </span>

            InvestPro

        </a>

        <a href="javascript:history.back()" class="back-link">
            <i class="fas fa-arrow-left"></i>
            Retour
        </a>

    </div>

</header>


<main class="content">

    <section class="hero">

        <div class="hero-badge">
            <i class="fas fa-shield-halved"></i>
            Document officiel
        </div>

        <h1>
            Politique & Conditions
        </h1>

        <p>
            Découvrez les règles qui encadrent l'utilisation
            de la plateforme InvestPro ainsi que les principes
            appliqués à vos données, paiements et opérations.
        </p>

        <div class="updated">
            Dernière mise à jour :
            <strong>11 août 2026</strong>
        </div>

    </section>


    <!-- 01 -->

    <section class="policy-card">

        <div class="section-title">

            <span class="section-number">01</span>

            <h2>Acceptation des conditions</h2>

        </div>

        <p>
            En créant un compte et en utilisant InvestPro,
            vous reconnaissez avoir lu, compris et accepté
            les présentes conditions.
        </p>

        <p>
            Si vous n'acceptez pas ces conditions, vous ne
            devez pas créer de compte ou utiliser les services
            proposés par la plateforme.
        </p>

    </section>


    <!-- 02 -->

    <section class="policy-card">

        <div class="section-title">

            <span class="section-number">02</span>

            <h2>Création du compte</h2>

        </div>

        <p>
            Chaque utilisateur doit fournir des informations
            exactes lors de son inscription.
        </p>

        <ul>

            <li>
                Une adresse e-mail valide est requise.
            </li>

            <li>
                Les informations personnelles doivent être
                exactes et à jour.
            </li>

            <li>
                Un compte ne doit pas être utilisé pour
                usurper l'identité d'une autre personne.
            </li>

            <li>
                L'utilisateur est responsable de la
                confidentialité de ses identifiants.
            </li>

        </ul>

    </section>


    <!-- 03 -->

    <section class="policy-card">

        <div class="section-title">

            <span class="section-number">03</span>

            <h2>Solde et opérations financières</h2>

        </div>

        <p>
            Les soldes affichés sur InvestPro correspondent
            aux opérations enregistrées sur le compte de
            l'utilisateur.
        </p>

        <p>
            Toute opération financière peut être soumise à
            une vérification avant sa validation définitive.
        </p>

        <div class="important">

            <i class="fas fa-triangle-exclamation"></i>

            <span>
                Vérifiez toujours les informations affichées
                avant de confirmer une opération.
            </span>

        </div>

    </section>


    <!-- 04 -->

    <section class="policy-card">

        <div class="section-title">

            <span class="section-number">04</span>

            <h2>Retraits</h2>

        </div>

        <p>
            Les demandes de retrait sont traitées conformément
            aux règles affichées dans l'espace portefeuille.
        </p>

        <ul>

            <li>
                Un montant minimum peut être exigé.
            </li>

            <li>
                Des frais peuvent s'appliquer selon le montant
                et le moyen de paiement choisi.
            </li>

            <li>
                Les informations du portefeuille doivent être
                correctement renseignées.
            </li>

            <li>
                Une demande peut rester en attente pendant
                sa vérification.
            </li>

        </ul>

    </section>


    <!-- 05 -->

    <section class="policy-card">

        <div class="section-title">

            <span class="section-number">05</span>

            <h2>Parrainage et récompenses</h2>

        </div>

        <p>
            InvestPro peut proposer un programme de parrainage
            permettant aux utilisateurs de recevoir des
            récompenses lorsqu'un nouveau membre s'inscrit
            via leur lien de parrainage.
        </p>

        <p>
            Les récompenses sont attribuées uniquement lorsque
            les conditions du programme sont remplies.
        </p>

        <div class="success-box">

            <i class="fas fa-gift"></i>

            <span>
                Toute tentative de fraude, d'auto-parrainage
                ou de création de comptes artificiels peut
                entraîner l'annulation des récompenses.
            </span>

        </div>

    </section>


    <!-- 06 -->

    <section class="policy-card">

        <div class="section-title">

            <span class="section-number">06</span>

            <h2>Jeux et promotions</h2>

        </div>

        <p>
            Certaines fonctionnalités peuvent proposer des
            jeux, tirages, concours ou promotions.
        </p>

        <p>
            Les règles spécifiques applicables à chaque
            activité sont affichées avant ou pendant la
            participation.
        </p>

        <p>
            La participation à une activité ne garantit pas
            l'obtention d'une récompense.
        </p>

    </section>


    <!-- 07 -->

    <section class="policy-card">

        <div class="section-title">

            <span class="section-number">07</span>

            <h2>Protection des données</h2>

        </div>

        <p>
            InvestPro collecte uniquement les informations
            nécessaires au fonctionnement du compte et des
            services proposés.
        </p>

        <ul>

            <li>
                Informations d'inscription.
            </li>

            <li>
                Informations nécessaires aux paiements
                et retraits.
            </li>

            <li>
                Informations relatives aux opérations
                effectuées sur la plateforme.
            </li>

        </ul>

        <p>
            Les informations personnelles ne doivent pas être
            utilisées à des fins frauduleuses ou illégales.
        </p>

    </section>


    <!-- 08 -->

    <section class="policy-card">

        <div class="section-title">

            <span class="section-number">08</span>

            <h2>Sécurité du compte</h2>

        </div>

        <p>
            L'utilisateur doit conserver ses identifiants
            de connexion de manière confidentielle.
        </p>

        <p>
            En cas d'activité suspecte, l'utilisateur doit
            contacter rapidement l'administration d'InvestPro.
        </p>

    </section>


    <!-- 09 -->

    <section class="policy-card">

        <div class="section-title">

            <span class="section-number">09</span>

            <h2>Utilisation interdite</h2>

        </div>

        <p>
            Il est interdit d'utiliser InvestPro pour :
        </p>

        <ul>

            <li>
                effectuer des activités frauduleuses ;
            </li>

            <li>
                créer de faux comptes ;
            </li>

            <li>
                manipuler ou tenter de contourner les systèmes
                de sécurité ;
            </li>

            <li>
                exploiter volontairement une faille technique ;
            </li>

            <li>
                utiliser la plateforme à des fins illégales.
            </li>

        </ul>

    </section>


    <!-- 10 -->

    <section class="policy-card">

        <div class="section-title">

            <span class="section-number">10</span>

            <h2>Modification des conditions</h2>

        </div>

        <p>
            InvestPro peut modifier ces conditions lorsque cela
            est nécessaire pour améliorer ses services, renforcer
            la sécurité ou respecter les obligations applicables.
        </p>

        <p>
            La version publiée sur cette page constitue la
            version actuellement applicable.
        </p>

    </section>


    <!-- 11 -->

    <section class="policy-card">

        <div class="section-title">

            <span class="section-number">11</span>

            <h2>Contact</h2>

        </div>

        <p>
            Pour toute question concernant votre compte,
            vos opérations ou les présentes conditions,
            veuillez utiliser les moyens de contact officiels
            mis à disposition par InvestPro.
        </p>

    </section>

</main>


<footer>

    <strong>InvestPro</strong>

    <br>

    © <?= date('Y') ?> — Tous droits réservés.

</footer>

</div></body>
</html>
