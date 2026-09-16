<?php

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

$user = getCurrentUser();

if (!$user) {
    session_destroy();
    header('Location: ../login.php');
    exit;
}

$userId = (int) $user['id'];


/*
|--------------------------------------------------------------------------
| CONFIGURATION
|--------------------------------------------------------------------------
*/

$referralReward = 100.00;


/*
|--------------------------------------------------------------------------
| GÉNÉRATION DU CODE DE PARRAINAGE
|--------------------------------------------------------------------------
*/

if (empty($user['referral_code'])) {

    try {

        $newReferralCode =
            'IP' .
            strtoupper(
                substr(
                    bin2hex(random_bytes(5)),
                    0,
                    8
                )
            );

    } catch (Throwable $e) {

        $newReferralCode =
            'IP' .
            strtoupper(
                substr(
                    md5(
                        $userId .
                        $user['username'] .
                        microtime(true)
                    ),
                    0,
                    8
                )
            );
    }


    /*
    |--------------------------------------------------------------------------
    | Vérifier collision
    |--------------------------------------------------------------------------
    */

    $check = $pdo->prepare("
        SELECT id
        FROM users
        WHERE referral_code = ?
        LIMIT 1
    ");

    $check->execute([
        $newReferralCode
    ]);


    if ($check->fetch()) {

        $newReferralCode =
            'IP' .
            strtoupper(
                substr(
                    md5(
                        $userId .
                        microtime(true) .
                        random_int(1, 999999)
                    ),
                    0,
                    8
                )
            );
    }


    /*
    |--------------------------------------------------------------------------
    | Enregistrer
    |--------------------------------------------------------------------------
    */

    $update = $pdo->prepare("
        UPDATE users
        SET referral_code = ?
        WHERE id = ?
    ");

    $update->execute([
        $newReferralCode,
        $userId
    ]);

    $user['referral_code'] = $newReferralCode;
}


/*
|--------------------------------------------------------------------------
| CODE DE PARRAINAGE
|--------------------------------------------------------------------------
*/

$referralCode =
    $user['referral_code'];


/*
|--------------------------------------------------------------------------
| URL DE PARRAINAGE
|--------------------------------------------------------------------------
|
| On récupère automatiquement l'URL actuelle.
|
*/

$protocol =
    (
        (!empty($_SERVER['HTTPS']) &&
        $_SERVER['HTTPS'] !== 'off')
        ||
        (
            isset($_SERVER['SERVER_PORT']) &&
            $_SERVER['SERVER_PORT'] == 443
        )
    )
    ? 'https://'
    : 'http://';


$host =
    $_SERVER['HTTP_HOST']
    ?? '127.0.0.1:8000';


$referralUrl =
    $protocol .
    $host .
    '/investissement/pages/auth/register.php?ref=' .
    urlencode($referralCode);


/*
|--------------------------------------------------------------------------
| STATISTIQUES
|--------------------------------------------------------------------------
*/


/*
| Nombre de filleuls
*/

$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM users
    WHERE referred_by = ?
");

$stmt->execute([
    $userId
]);

$totalReferrals =
    (int) $stmt->fetchColumn();


/*
| Filleuls vérifiés
*/

$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM users
    WHERE referred_by = ?
    AND email_verified = 1
");

$stmt->execute([
    $userId
]);

$verifiedReferrals =
    (int) $stmt->fetchColumn();


/*
| Total commissions
*/

$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(amount), 0)
    FROM transactions
    WHERE user_id = ?
    AND type = 'bonus'
    AND payment_method = 'Referral'
    AND status = 'completed'
");

$stmt->execute([
    $userId
]);

$totalReferralEarnings =
    (float) $stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| FILLEULS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        username,
        email,
        email_verified,
        created_at,
        referral_rewarded
    FROM users
    WHERE referred_by = ?
    ORDER BY created_at DESC
");

$stmt->execute([
    $userId
]);

$referrals =
    $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| SOLDE BONUS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT bonus_balance
    FROM users
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([
    $userId
]);

$bonusBalance =
    (float) $stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| LETTRE AVATAR
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

<title>
    Tâches rémunérées — InvestPro
</title>


<link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
>


<style>

/*
|--------------------------------------------------------------------------
| RESET
|--------------------------------------------------------------------------
*/

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

    background:
        linear-gradient(
            135deg,
            #f6f8fc,
            #eef2f7
        );

    color: #172033;

    min-height: 100vh;
}


/*
|--------------------------------------------------------------------------
| CONTAINER
|--------------------------------------------------------------------------
*/

.tasks-page {

    width: min(
        1180px,
        calc(100% - 32px)
    );

    margin: 0 auto;

    padding:
        35px 0
        70px;
}


/*
|--------------------------------------------------------------------------
| TOPBAR
|--------------------------------------------------------------------------
*/

.tasks-topbar {

    display: flex;

    align-items: center;

    justify-content: space-between;

    margin-bottom: 30px;
}


.tasks-brand {

    display: flex;

    align-items: center;

    gap: 12px;
}


.tasks-logo {

    width: 48px;
    height: 48px;

    border-radius: 15px;

    display: flex;

    align-items: center;
    justify-content: center;

    background:
        linear-gradient(
            135deg,
            #111827,
            #334155
        );

    color: white;

    box-shadow:
        0 10px 25px
        rgba(15,23,42,.15);
}


.tasks-brand strong {

    display: block;

    font-size: 17px;
}


.tasks-brand span {

    color: #64748b;

    font-size: 12px;
}


.tasks-avatar {

    width: 44px;
    height: 44px;

    border-radius: 50%;

    display: flex;

    align-items: center;
    justify-content: center;

    background:
        linear-gradient(
            135deg,
            #f59e0b,
            #f97316
        );

    color: white;

    font-weight: 800;
}


/*
|--------------------------------------------------------------------------
| HERO
|--------------------------------------------------------------------------
*/

.tasks-hero {

    position: relative;

    overflow: hidden;

    padding: 42px;

    border-radius: 28px;

    background:
        linear-gradient(
            135deg,
            #111827,
            #1e293b 60%,
            #312e81
        );

    color: white;

    box-shadow:
        0 25px 60px
        rgba(15,23,42,.18);

    margin-bottom: 25px;
}


.tasks-hero::after {

    content: "";

    position: absolute;

    width: 300px;
    height: 300px;

    right: -80px;
    top: -120px;

    border-radius: 50%;

    background:
        rgba(255,255,255,.07);
}


.tasks-badge {

    display: inline-flex;

    align-items: center;

    gap: 8px;

    padding: 8px 13px;

    border-radius: 30px;

    background:
        rgba(255,255,255,.1);

    border:
        1px solid
        rgba(255,255,255,.15);

    font-size: 12px;

    font-weight: 700;

    letter-spacing: .5px;

    margin-bottom: 18px;
}


.tasks-hero h1 {

    font-size:
        clamp(
            30px,
            5vw,
            48px
        );

    line-height: 1.05;

    margin-bottom: 14px;
}


.tasks-hero p {

    max-width: 690px;

    color:
        rgba(255,255,255,.72);

    line-height: 1.7;

    font-size: 15px;
}


/*
|--------------------------------------------------------------------------
| STATS
|--------------------------------------------------------------------------
*/

.tasks-stats {

    display: grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap: 18px;

    margin-bottom: 25px;
}


.task-stat {

    background: white;

    border:
        1px solid
        #e8edf4;

    border-radius: 22px;

    padding: 24px;

    box-shadow:
        0 10px 30px
        rgba(15,23,42,.05);
}


.task-stat-icon {

    width: 45px;
    height: 45px;

    border-radius: 14px;

    display: flex;

    align-items: center;
    justify-content: center;

    background: #f1f5f9;

    color: #4f46e5;

    margin-bottom: 18px;
}


.task-stat strong {

    display: block;

    font-size: 27px;

    margin-bottom: 5px;
}


.task-stat span {

    color: #64748b;

    font-size: 13px;
}


/*
|--------------------------------------------------------------------------
| REFERRAL CARD
|--------------------------------------------------------------------------
*/

.referral-card {

    background: white;

    border:
        1px solid
        #e8edf4;

    border-radius: 26px;

    padding: 30px;

    box-shadow:
        0 12px 35px
        rgba(15,23,42,.06);

    margin-bottom: 25px;
}


.section-label {

    color: #6366f1;

    font-size: 11px;

    font-weight: 800;

    letter-spacing: 1.5px;

    text-transform: uppercase;

    margin-bottom: 8px;
}


.referral-card h2 {

    font-size: 24px;

    margin-bottom: 8px;
}


.referral-card > p {

    color: #64748b;

    font-size: 14px;

    line-height: 1.6;

    margin-bottom: 24px;
}


.referral-link-box {

    display: flex;

    align-items: center;

    gap: 10px;

    padding: 7px;

    background: #f8fafc;

    border:
        1px solid
        #e2e8f0;

    border-radius: 16px;
}


.referral-link {

    flex: 1;

    min-width: 0;

    overflow: hidden;

    white-space: nowrap;

    text-overflow: ellipsis;

    padding: 10px 13px;

    color: #334155;

    font-size: 13px;

    font-weight: 600;
}


.copy-btn {

    border: 0;

    cursor: pointer;

    padding: 12px 17px;

    border-radius: 12px;

    background: #111827;

    color: white;

    font-weight: 700;

    display: flex;

    align-items: center;

    gap: 8px;

    transition: .2s;
}


.copy-btn:hover {

    transform: translateY(-2px);

    background: #312e81;
}


.share-buttons {

    display: flex;

    gap: 10px;

    margin-top: 14px;
}


.share-btn {

    flex: 1;

    text-decoration: none;

    padding: 13px;

    border-radius: 13px;

    text-align: center;

    font-weight: 700;

    font-size: 13px;

    transition: .2s;
}


.share-whatsapp {

    background: #dcfce7;

    color: #15803d;
}


.share-system {

    background: #eef2ff;

    color: #4338ca;
}


/*
|--------------------------------------------------------------------------
| COMMISSION
|--------------------------------------------------------------------------
*/

.commission-card {

    display: grid;

    grid-template-columns:
        1fr auto;

    align-items: center;

    gap: 20px;

    padding: 28px;

    border-radius: 25px;

    background:
        linear-gradient(
            135deg,
            #fff7ed,
            #fffbeb
        );

    border:
        1px solid
        #fed7aa;

    margin-bottom: 25px;
}


.commission-card h3 {

    font-size: 19px;

    margin-bottom: 7px;
}


.commission-card p {

    color: #78716c;

    font-size: 13px;

    line-height: 1.5;
}


.commission-amount {

    font-size: 31px;

    font-weight: 900;

    color: #ea580c;

    white-space: nowrap;
}


/*
|--------------------------------------------------------------------------
| REFERRALS LIST
|--------------------------------------------------------------------------
*/

.referrals-card {

    background: white;

    border:
        1px solid
        #e8edf4;

    border-radius: 26px;

    overflow: hidden;

    box-shadow:
        0 12px 35px
        rgba(15,23,42,.05);
}


.referrals-header {

    padding: 25px 28px;

    border-bottom:
        1px solid
        #eef2f7;
}


.referrals-header h2 {

    font-size: 20px;
}


.referrals-header p {

    color: #64748b;

    font-size: 13px;

    margin-top: 5px;
}


.referral-row {

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 15px;

    padding: 18px 28px;

    border-bottom:
        1px solid
        #f1f5f9;
}


.referral-user {

    display: flex;

    align-items: center;

    gap: 13px;

    min-width: 0;
}


.referral-avatar {

    width: 42px;
    height: 42px;

    flex-shrink: 0;

    border-radius: 13px;

    display: flex;

    align-items: center;
    justify-content: center;

    background: #eef2ff;

    color: #4f46e5;

    font-weight: 800;
}


.referral-user strong {

    display: block;

    font-size: 14px;
}


.referral-user small {

    display: block;

    color: #94a3b8;

    margin-top: 3px;

    font-size: 11px;
}


.referral-status {

    padding: 7px 11px;

    border-radius: 20px;

    font-size: 11px;

    font-weight: 800;

    white-space: nowrap;
}


.status-paid {

    background: #dcfce7;

    color: #15803d;
}


.status-pending {

    background: #fef3c7;

    color: #a16207;
}


.empty-referrals {

    padding: 55px 20px;

    text-align: center;

    color: #94a3b8;
}


.empty-referrals i {

    font-size: 35px;

    margin-bottom: 15px;

    opacity: .5;
}


/*
|--------------------------------------------------------------------------
| RESPONSIVE
|--------------------------------------------------------------------------
*/

@media (max-width: 750px) {

    .tasks-page {

        width:
            min(
                100% - 20px,
                1180px
            );

        padding-top: 20px;
    }


    .tasks-hero {

        padding: 28px 22px;

        border-radius: 22px;
    }


    .tasks-stats {

        grid-template-columns: 1fr;

    }


    .referral-card {

        padding: 22px;

    }


    .referral-link-box {

        flex-direction: column;

        align-items: stretch;

    }


    .copy-btn {

        justify-content: center;

    }


    .share-buttons {

        flex-direction: column;

    }


    .commission-card {

        grid-template-columns: 1fr;

    }


    .commission-amount {

        font-size: 27px;

    }


    .referral-row {

        padding: 17px;

    }

}

</style>

</head>


<body>


<main class="tasks-page">


    <!-- TOPBAR -->

    <header class="tasks-topbar">

        <div class="tasks-brand">

            <div class="tasks-logo">

                <i class="fas fa-coins"></i>

            </div>

            <div>

                <strong>
                    INVESTPRO
                </strong>

                <span>
                    Tâches rémunérées
                </span>

            </div>

        </div>


        <div class="tasks-avatar">

            <?= htmlspecialchars($firstLetter) ?>

        </div>

    </header>



    <!-- HERO -->

    <section class="tasks-hero">

        <div class="tasks-badge">

            <i class="fas fa-bolt"></i>

            ELITE MONEY

        </div>


        <h1>
            Gagnez en partageant.
        </h1>


        <p>

            Partagez votre lien personnel InvestPro.
            Chaque nouvelle personne qui s'inscrit
            avec votre lien et valide son compte vous
            rapporte automatiquement

            <strong>
                100 FCFA
            </strong>.

        </p>

    </section>



    <!-- STATS -->

    <section class="tasks-stats">


        <div class="task-stat">

            <div class="task-stat-icon">

                <i class="fas fa-users"></i>

            </div>

            <strong>
                <?= number_format($totalReferrals, 0, ',', ' ') ?>
            </strong>

            <span>
                Filleuls inscrits
            </span>

        </div>



        <div class="task-stat">

            <div class="task-stat-icon">

                <i class="fas fa-user-check"></i>

            </div>

            <strong>
                <?= number_format($verifiedReferrals, 0, ',', ' ') ?>
            </strong>

            <span>
                Comptes validés
            </span>

        </div>



        <div class="task-stat">

            <div class="task-stat-icon">

                <i class="fas fa-coins"></i>

            </div>

            <strong>
                <?= number_format($totalReferralEarnings, 0, ',', ' ') ?>
                FCFA
            </strong>

            <span>
                Commissions gagnées
            </span>

        </div>

    </section>



    <!-- LINK -->

    <section class="referral-card">


        <div class="section-label">
            Votre mission
        </div>


        <h2>
            Invitez vos amis
        </h2>


        <p>

            Copiez votre lien personnel et partagez-le
            sur WhatsApp, Facebook, Telegram ou partout
            où vous souhaitez inviter de nouveaux membres.

        </p>


        <div class="referral-link-box">

            <div
                class="referral-link"
                id="referralLink"
            >
                <?= htmlspecialchars($referralUrl) ?>
            </div>


            <button
                type="button"
                class="copy-btn"
                id="copyReferral"
            >

                <i class="fas fa-copy"></i>

                Copier

            </button>

        </div>



        <div class="share-buttons">


            <a
                href="https://wa.me/?text=<?= urlencode(
                    "Rejoins-moi sur InvestPro 👑\n\nInscris-toi avec mon lien :\n" . $referralUrl
                ) ?>"
                target="_blank"
                rel="noopener noreferrer"
                class="share-btn share-whatsapp"
            >

                <i class="fab fa-whatsapp"></i>

                Partager sur WhatsApp

            </a>



            <button
                type="button"
                class="share-btn share-system"
                id="nativeShare"
            >

                <i class="fas fa-share-nodes"></i>

                Partager

            </button>

        </div>

    </section>



    <!-- COMMISSION -->

    <section class="commission-card">


        <div>

            <h3>

                💰 Votre récompense

            </h3>


            <p>

                Chaque inscription validée provenant
                de votre lien génère automatiquement
                une commission de 100 FCFA.

            </p>

        </div>


        <div class="commission-amount">

            +100 FCFA

        </div>

    </section>



    <!-- FILLEULS -->

    <section class="referrals-card">


        <div class="referrals-header">

            <h2>
                Mes filleuls
            </h2>

            <p>
                Les personnes inscrites avec votre lien.
            </p>

        </div>



        <?php if (!$referrals): ?>


            <div class="empty-referrals">

                <i class="fas fa-user-plus"></i>

                <p>
                    Vous n'avez encore aucun filleul.
                </p>

            </div>


        <?php else: ?>


            <?php foreach ($referrals as $referral): ?>


                <div class="referral-row">


                    <div class="referral-user">


                        <div class="referral-avatar">

                            <?= htmlspecialchars(
                                strtoupper(
                                    substr(
                                        $referral['username'],
                                        0,
                                        1
                                    )
                                )
                            ) ?>

                        </div>


                        <div>

                            <strong>

                                <?= htmlspecialchars(
                                    $referral['username']
                                ) ?>

                            </strong>


                            <small>

                                Inscrit le
                                <?= date(
                                    'd/m/Y',
                                    strtotime(
                                        $referral['created_at']
                                    )
                                ) ?>

                            </small>

                        </div>

                    </div>



                    <?php if (
                        (int)$referral['referral_rewarded'] === 1
                    ): ?>


                        <span class="referral-status status-paid">

                            <i class="fas fa-check"></i>

                            +100 FCFA

                        </span>


                    <?php elseif (
                        (int)$referral['email_verified'] === 1
                    ): ?>


                        <span class="referral-status status-paid">

                            Compte validé

                        </span>


                    <?php else: ?>


                        <span class="referral-status status-pending">

                            En attente

                        </span>


                    <?php endif; ?>


                </div>


            <?php endforeach; ?>


        <?php endif; ?>


    </section>


</main>



<script>

/*
|--------------------------------------------------------------------------
| LIEN DE PARRAINAGE
|--------------------------------------------------------------------------
*/

const referralLink =
    <?= json_encode($referralUrl) ?>;


/*
|--------------------------------------------------------------------------
| COPIER
|--------------------------------------------------------------------------
*/

const copyButton =
    document.getElementById('copyReferral');


copyButton.addEventListener(
    'click',
    async function () {

        try {

            await navigator.clipboard.writeText(
                referralLink
            );


            this.innerHTML =
                '<i class="fas fa-check"></i> Copié';


            setTimeout(() => {

                this.innerHTML =
                    '<i class="fas fa-copy"></i> Copier';

            }, 2000);


        } catch (error) {

            const textarea =
                document.createElement('textarea');

            textarea.value =
                referralLink;

            document.body.appendChild(
                textarea
            );

            textarea.select();

            document.execCommand(
                'copy'
            );

            textarea.remove();


            this.innerHTML =
                '<i class="fas fa-check"></i> Copié';


            setTimeout(() => {

                this.innerHTML =
                    '<i class="fas fa-copy"></i> Copier';

            }, 2000);

        }

    }
);


/*
|--------------------------------------------------------------------------
| PARTAGE NATIF
|--------------------------------------------------------------------------
*/

const nativeShare =
    document.getElementById('nativeShare');


if (
    navigator.share
) {

    nativeShare.addEventListener(
        'click',
        async function () {

            try {

                await navigator.share({

                    title:
                        'Rejoins InvestPro',

                    text:
                        'Rejoins-moi sur InvestPro 👑',

                    url:
                        referralLink

                });

            } catch (error) {

                /*
                | L'utilisateur peut simplement
                | fermer la fenêtre de partage.
                */

            }

        }
    );

} else {

    nativeShare.addEventListener(
        'click',
        async function () {

            try {

                await navigator.clipboard.writeText(
                    referralLink
                );

                alert(
                    'Votre lien de parrainage a été copié.'
                );

            } catch (error) {

                alert(
                    referralLink
                );

            }

        }
    );

}

</script>


</body>

</html>
