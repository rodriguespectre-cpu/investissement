<?php

session_start();

require_once '../../config/database.php';

/*
|--------------------------------------------------------------------------
| AUTHENTIFICATION
|--------------------------------------------------------------------------
*/

if (!function_exists('isLoggedIn') || !isLoggedIn()) {
    header('Location: ../auth/login.php');
    exit;
}

$user = getCurrentUser();

if (!$user) {
    header('Location: ../auth/login.php');
    exit;
}

$userId = (int) $user['id'];


/*
|--------------------------------------------------------------------------
| CONFIGURATION DU JEU
|--------------------------------------------------------------------------
*/

$elitePrize = 200000;

$eliteColors = [

    [
        'id' => 'red',
        'name' => 'Rouge',
        'hex' => '#ef4444'
    ],

    [
        'id' => 'blue',
        'name' => 'Bleu',
        'hex' => '#3b82f6'
    ],

    [
        'id' => 'green',
        'name' => 'Vert',
        'hex' => '#22c55e'
    ],

    [
        'id' => 'yellow',
        'name' => 'Jaune',
        'hex' => '#facc15'
    ],

    [
        'id' => 'purple',
        'name' => 'Violet',
        'hex' => '#8b5cf6'
    ],

    [
        'id' => 'orange',
        'name' => 'Orange',
        'hex' => '#f97316'
    ]

];


/*
|--------------------------------------------------------------------------
| DATE ACTUELLE GMT
|--------------------------------------------------------------------------
*/

$now = new DateTimeImmutable(
    'now',
    new DateTimeZone('UTC')
);


/*
|--------------------------------------------------------------------------
| CALCUL DE LA PROCHAINE SÉANCE
|--------------------------------------------------------------------------
|
| Mercredi à 19:00 GMT
|
*/

$nextRound = $now;

$daysUntilWednesday =
    (3 - (int) $now->format('N') + 7) % 7;


/*
|--------------------------------------------------------------------------
| Si nous sommes mercredi avant 19h,
| la séance est aujourd'hui.
|--------------------------------------------------------------------------
*/

if (
    (int) $now->format('N') === 3
    &&
    $now->format('H:i:s') < '19:00:00'
) {

    $daysUntilWednesday = 0;

}


$nextRound = $now->modify(
    '+' . $daysUntilWednesday . ' days'
);

$nextRound = new DateTimeImmutable(
    $nextRound->format('Y-m-d') . ' 19:00:00',
    new DateTimeZone('UTC')
);


/*
|--------------------------------------------------------------------------
| IDENTIFIANT UNIQUE DE LA SÉANCE
|--------------------------------------------------------------------------
*/

$roundKey = $nextRound->format('Y-m-d');


/*
|--------------------------------------------------------------------------
| CRÉATION / RÉCUPÉRATION DE LA SÉANCE
|--------------------------------------------------------------------------
*/

try {

    $stmt = $pdo->prepare("
        SELECT *
        FROM elite_game_rounds
        WHERE round_key = ?
        LIMIT 1
    ");

    $stmt->execute([
        $roundKey
    ]);

    $round = $stmt->fetch(PDO::FETCH_ASSOC);


    if (!$round) {

        $stmt = $pdo->prepare("
            INSERT INTO elite_game_rounds
            (
                round_key,
                scheduled_at,
                prize,
                status
            )
            VALUES
            (
                ?,
                ?,
                ?,
                'scheduled'
            )
        ");

        $stmt->execute([
            $roundKey,
            $nextRound->format('Y-m-d H:i:s'),
            $elitePrize
        ]);


        $roundId = (int) $pdo->lastInsertId();


        $stmt = $pdo->prepare("
            SELECT *
            FROM elite_game_rounds
            WHERE id = ?
            LIMIT 1
        ");

        $stmt->execute([
            $roundId
        ]);

        $round = $stmt->fetch(PDO::FETCH_ASSOC);

    }


} catch (Throwable $e) {

    $round = null;

}


/*
|--------------------------------------------------------------------------
| VALEURS PAR DÉFAUT
|--------------------------------------------------------------------------
*/

$roundId = $round
    ? (int) $round['id']
    : 0;

$eliteAlreadyPlayed = false;

$eliteSelectedColor = null;


/*
|--------------------------------------------------------------------------
| VÉRIFIER PARTICIPATION
|--------------------------------------------------------------------------
*/

if ($roundId > 0) {

    try {

        $stmt = $pdo->prepare("
            SELECT
                color_id,
                color_name
            FROM elite_game_entries
            WHERE round_id = ?
            AND user_id = ?
            LIMIT 1
        ");

        $stmt->execute([
            $roundId,
            $userId
        ]);

        $entry = $stmt->fetch(PDO::FETCH_ASSOC);


        if ($entry) {

            $eliteAlreadyPlayed = true;

            $eliteSelectedColor =
                $entry['color_name'];

        }

    } catch (Throwable $e) {

        $eliteAlreadyPlayed = false;

    }

}


/*
|--------------------------------------------------------------------------
| FORMATAGE DATE
|--------------------------------------------------------------------------
*/

$nextRoundDisplay =
    $nextRound
        ->setTimezone(new DateTimeZone('UTC'))
        ->format('d/m/Y à 19:00');


/*
|--------------------------------------------------------------------------
| PRÉPARATION JAVASCRIPT
|--------------------------------------------------------------------------
*/

$nextRoundTimestamp =
    $nextRound->getTimestamp() * 1000;

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
    Elite Money — InvestPro
</title>

<link
    rel="stylesheet"
    href="assets/index.css"
>

<link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
>


<style>

/*
|--------------------------------------------------------------------------
| ELITE GAME
|--------------------------------------------------------------------------
*/

.elite-game-page {

    min-height: 100vh;

    padding: 35px 20px 70px;

    background:
        radial-gradient(
            circle at top right,
            rgba(37,99,235,.12),
            transparent 35%
        ),
        linear-gradient(
            135deg,
            #f8fafc,
            #eef2ff
        );

}


.elite-game-container {

    width: min(100%, 1000px);

    margin: auto;

}


/*
|--------------------------------------------------------------------------
| BACK
|--------------------------------------------------------------------------
*/

.elite-back {

    display: inline-flex;

    align-items: center;

    gap: 9px;

    color: #334155;

    text-decoration: none;

    font-weight: 700;

    margin-bottom: 25px;

    transition: .2s;

}


.elite-back:hover {

    color: #2563eb;

    transform: translateX(-3px);

}


/*
|--------------------------------------------------------------------------
| HEADER
|--------------------------------------------------------------------------
*/

.elite-game-header {

    text-align: center;

    margin-bottom: 30px;

}


.elite-game-badge {

    display: inline-flex;

    align-items: center;

    gap: 8px;

    padding: 9px 16px;

    border-radius: 999px;

    background:
        linear-gradient(
            135deg,
            #111827,
            #334155
        );

    color: white;

    font-size: 12px;

    font-weight: 800;

    letter-spacing: 1px;

    box-shadow:
        0 10px 30px
        rgba(15,23,42,.15);

}


.elite-game-header h1 {

    margin: 18px 0 10px;

    font-size: clamp(
        32px,
        6vw,
        56px
    );

    color: #0f172a;

    line-height: 1.05;

}


.elite-game-header p {

    margin: auto;

    max-width: 650px;

    color: #64748b;

    font-size: 16px;

    line-height: 1.7;

}


/*
|--------------------------------------------------------------------------
| PRIZE
|--------------------------------------------------------------------------
*/

.elite-prize-card {

    position: relative;

    overflow: hidden;

    display: flex;

    align-items: center;

    gap: 22px;

    padding: 25px;

    border-radius: 24px;

    background:
        linear-gradient(
            135deg,
            #111827,
            #1e293b
        );

    color: white;

    box-shadow:
        0 25px 60px
        rgba(15,23,42,.20);

    margin-bottom: 20px;

}


.elite-prize-card::after {

    content: "";

    position: absolute;

    width: 180px;

    height: 180px;

    right: -50px;

    top: -80px;

    border-radius: 50%;

    background:
        rgba(255,255,255,.08);

}


.prize-icon {

    width: 65px;

    height: 65px;

    display: grid;

    place-items: center;

    flex-shrink: 0;

    border-radius: 20px;

    background:
        rgba(255,255,255,.10);

    font-size: 28px;

    color: #facc15;

}


.prize-content {

    display: flex;

    flex-direction: column;

    gap: 5px;

}


.prize-content span {

    color: #cbd5e1;

    font-size: 11px;

    font-weight: 800;

    letter-spacing: 1px;

}


.prize-content strong {

    font-size: clamp(
        28px,
        6vw,
        42px
    );

    line-height: 1;

}


/* =========================================================
   ELITE MONEY — COUNTDOWN
========================================================= */

.elite-countdown-card {
    position: relative;
    width: 100%;
    margin: 28px 0;
    padding: 28px;
    overflow: hidden;

    border-radius: 28px;

    background:
        radial-gradient(
            circle at 85% 15%,
            rgba(245, 158, 11, .16),
            transparent 35%
        ),
        linear-gradient(
            135deg,
            #111827 0%,
            #172033 55%,
            #0f172a 100%
        );

    border: 1px solid rgba(255,255,255,.08);

    box-shadow:
        0 20px 55px rgba(0,0,0,.18),
        inset 0 1px 0 rgba(255,255,255,.05);

    color: #fff;

    box-sizing: border-box;
}


/* Effet lumineux */

.elite-countdown-card::before {
    content: "";

    position: absolute;

    width: 180px;
    height: 180px;

    right: -80px;
    top: -90px;

    border-radius: 50%;

    background: rgba(245,158,11,.12);

    filter: blur(15px);

    pointer-events: none;
}


/* =========================================================
   HEADER
========================================================= */

.elite-countdown-top {
    position: relative;

    display: flex;
    align-items: center;

    gap: 17px;

    margin-bottom: 25px;
}


.elite-countdown-icon {
    width: 58px;
    height: 58px;

    flex-shrink: 0;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 18px;

    background:
        linear-gradient(
            135deg,
            #f59e0b,
            #f97316
        );

    color: #fff;

    font-size: 22px;

    box-shadow:
        0 10px 28px rgba(245,158,11,.28);
}


.elite-countdown-heading {
    min-width: 0;
}


.elite-countdown-eyebrow {
    display: block;

    margin-bottom: 4px;

    color: #fbbf24;

    font-size: 10px;

    font-weight: 900;

    letter-spacing: 2px;
}


.elite-countdown-heading h3 {
    margin: 0;

    color: #fff;

    font-size: 22px;

    font-weight: 900;
}


.elite-countdown-heading p {
    margin: 5px 0 0;

    color: #9ca3af;

    font-size: 13px;
}


/* =========================================================
   COMPTEUR
========================================================= */

.elite-countdown {
    position: relative;

    display: flex;

    align-items: center;
    justify-content: center;

    gap: 9px;

    width: 100%;

    margin-top: 10px;

    box-sizing: border-box;
}


.elite-time-box {
    position: relative;

    min-width: 90px;

    padding: 17px 12px 14px;

    text-align: center;

    border-radius: 18px;

    background:
        linear-gradient(
            145deg,
            rgba(255,255,255,.10),
            rgba(255,255,255,.035)
        );

    border: 1px solid rgba(255,255,255,.09);

    box-shadow:
        inset 0 1px 0 rgba(255,255,255,.05);

    backdrop-filter: blur(10px);

    transition:
        transform .25s ease,
        border-color .25s ease;
}


.elite-time-box:hover {
    transform: translateY(-3px);

    border-color:
        rgba(245,158,11,.45);
}


.elite-time-box strong {
    display: block;

    color: #fff;

    font-size: 32px;

    line-height: 1;

    font-weight: 900;

    letter-spacing: 1px;

    font-variant-numeric: tabular-nums;
}


.elite-time-box span {
    display: block;

    margin-top: 8px;

    color: #9ca3af;

    font-size: 9px;

    font-weight: 800;

    letter-spacing: 1.2px;
}


/* =========================================================
   SEPARATEURS
========================================================= */

.elite-time-separator {
    color: #f59e0b;

    font-size: 27px;

    font-weight: 900;

    margin-top: -8px;

    opacity: .8;
}


/* =========================================================
   SECONDES
========================================================= */

.elite-seconds-box strong {
    color: #fbbf24;
}


/* =========================================================
   STATUS
========================================================= */

.elite-countdown-status {
    position: relative;

    display: flex;

    align-items: center;

    justify-content: center;

    gap: 8px;

    margin-top: 22px;

    padding-top: 16px;

    border-top:
        1px solid rgba(255,255,255,.07);

    color: #9ca3af;

    font-size: 12px;
}


.elite-status-dot {
    width: 8px;
    height: 8px;

    flex-shrink: 0;

    border-radius: 50%;

    background: #22c55e;

    box-shadow:
        0 0 0 5px rgba(34,197,94,.10),
        0 0 15px rgba(34,197,94,.55);

    animation:
        elitePulse 1.8s infinite;
}


@keyframes elitePulse {

    0%,
    100% {
        opacity: 1;
        transform: scale(1);
    }

    50% {
        opacity: .55;
        transform: scale(.82);
    }

}


/* =========================================================
   ANIMATION SECONDES
========================================================= */

.elite-seconds-box.tick strong {
    animation:
        eliteSecondTick .35s ease;
}


@keyframes eliteSecondTick {

    0% {
        transform: scale(1);
    }

    45% {
        transform: scale(1.12);
        color: #fbbf24;
    }

    100% {
        transform: scale(1);
    }

}


/* =========================================================
   MOBILE
========================================================= */

@media (max-width: 650px) {

    .elite-countdown-card {
        padding: 21px 15px;

        border-radius: 23px;
    }


    .elite-countdown-top {
        gap: 13px;

        margin-bottom: 20px;
    }


    .elite-countdown-icon {
        width: 48px;
        height: 48px;

        border-radius: 15px;

        font-size: 18px;
    }


    .elite-countdown-heading h3 {
        font-size: 18px;
    }


    .elite-countdown-heading p {
        font-size: 11px;
    }


    .elite-countdown {
        gap: 4px;
    }


    .elite-time-box {
        min-width: 0;

        flex: 1;

        padding:
            14px
            5px
            12px;

        border-radius: 14px;
    }


    .elite-time-box strong {
        font-size: 22px;
    }


    .elite-time-box span {
        font-size: 7px;

        letter-spacing: .7px;
    }


    .elite-time-separator {
        font-size: 18px;
    }


    .elite-countdown-status {
        font-size: 10px;
    }

}


/* =========================================================
   TRÈS PETITS ÉCRANS
========================================================= */

@media (max-width: 380px) {

    .elite-countdown-card {
        padding: 18px 10px;
    }


    .elite-time-box strong {
        font-size: 18px;
    }


    .elite-time-box span {
        font-size: 6px;
    }


    .elite-time-separator {
        font-size: 15px;
    }

}


/*
|--------------------------------------------------------------------------
| CHOICE TITLE
|--------------------------------------------------------------------------
*/

.elite-choice-title {

    display: flex;

    align-items: center;

    gap: 15px;

    margin-bottom: 20px;

}


.elite-choice-title > span {

    width: 42px;

    height: 42px;

    display: grid;

    place-items: center;

    border-radius: 13px;

    background: #eff6ff;

    color: #2563eb;

    font-weight: 900;

}


.elite-choice-title strong {

    display: block;

    color: #0f172a;

    font-size: 18px;

}


.elite-choice-title small {

    color: #64748b;

}


/*
|--------------------------------------------------------------------------
| COLORS
|--------------------------------------------------------------------------
*/

.elite-colors-grid {

    display: grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap: 15px;

}


.elite-color-card {

    position: relative;

    border: 2px solid #e2e8f0;

    background: white;

    border-radius: 22px;

    padding: 25px 15px;

    cursor: pointer;

    transition:
        transform .2s,
        border-color .2s,
        box-shadow .2s;

}


.elite-color-card:hover {

    transform: translateY(-4px);

    border-color: var(
        --elite-color
    );

    box-shadow:
        0 15px 35px
        rgba(15,23,42,.10);

}


.elite-color-card.selected {

    border-color:
        var(--elite-color);

    box-shadow:
        0 0 0 4px
        color-mix(
            in srgb,
            var(--elite-color) 15%,
            transparent
        );

    transform: translateY(-3px);

}


.elite-color-circle {

    display: block;

    width: 70px;

    height: 70px;

    margin: auto;

    border-radius: 50%;

    background:
        var(--elite-color);

    box-shadow:
        inset 0 0 0 6px
        rgba(255,255,255,.35),
        0 10px 25px
        rgba(15,23,42,.15);

}


.elite-color-name {

    display: block;

    margin-top: 15px;

    font-weight: 800;

    color: #0f172a;

}


.elite-color-check {

    position: absolute;

    top: 12px;

    right: 12px;

    width: 27px;

    height: 27px;

    display: grid;

    place-items: center;

    border-radius: 50%;

    background:
        var(--elite-color);

    color: white;

    opacity: 0;

    transform: scale(.7);

    transition: .2s;

}


.elite-color-card.selected
.elite-color-check {

    opacity: 1;

    transform: scale(1);

}


/*
|--------------------------------------------------------------------------
| BUTTON
|--------------------------------------------------------------------------
*/

.elite-confirm-button {

    width: 100%;

    border: 0;

    margin-top: 22px;

    padding: 17px 20px;

    border-radius: 16px;

    background:
        linear-gradient(
            135deg,
            #2563eb,
            #4f46e5
        );

    color: white;

    font-size: 15px;

    font-weight: 900;

    cursor: pointer;

    box-shadow:
        0 15px 30px
        rgba(37,99,235,.20);

    transition: .2s;

}


.elite-confirm-button:hover:not(:disabled) {

    transform: translateY(-2px);

}


.elite-confirm-button:disabled {

    opacity: .45;

    cursor: not-allowed;

    box-shadow: none;

}


/*
|--------------------------------------------------------------------------
| PARTICIPATION STATUS
|--------------------------------------------------------------------------
*/

.elite-participation-status {

    display: flex;

    align-items: center;

    gap: 16px;

    padding: 22px;

    border-radius: 20px;

    background: #f0fdf4;

    border: 1px solid #bbf7d0;

    margin-bottom: 25px;

}


.status-check {

    width: 48px;

    height: 48px;

    display: grid;

    place-items: center;

    flex-shrink: 0;

    border-radius: 50%;

    background: #22c55e;

    color: white;

}


.elite-participation-status strong {

    color: #166534;

}


.elite-participation-status p {

    margin: 5px 0 0;

    color: #475569;

}


/*
|--------------------------------------------------------------------------
| RULES
|--------------------------------------------------------------------------
*/

.elite-rules {

    display: grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap: 14px;

    margin-top: 30px;

}


.elite-rule {

    display: flex;

    gap: 13px;

    padding: 18px;

    background: white;

    border: 1px solid #e2e8f0;

    border-radius: 18px;

}


.elite-rule > i {

    color: #2563eb;

    font-size: 20px;

}


.elite-rule strong {

    display: block;

    color: #0f172a;

    font-size: 14px;

}


.elite-rule span {

    display: block;

    margin-top: 5px;

    color: #64748b;

    font-size: 12px;

    line-height: 1.5;

}


/*
|--------------------------------------------------------------------------
| MODAL
|--------------------------------------------------------------------------
*/

.elite-result-overlay {

    position: fixed;

    inset: 0;

    z-index: 9999;

    display: none;

    align-items: center;

    justify-content: center;

    padding: 20px;

    background:
        rgba(15,23,42,.70);

    backdrop-filter:
        blur(8px);

}


.elite-result-overlay.show {

    display: flex;

}


.elite-result-modal {

    width: min(
        100%,
        450px
    );

    background: white;

    border-radius: 28px;

    padding: 35px 25px;

    text-align: center;

    position: relative;

    animation:
        eliteModal .35s ease;

}


@keyframes eliteModal {

    from {

        opacity: 0;

        transform:
            translateY(20px)
            scale(.95);

    }

    to {

        opacity: 1;

        transform:
            translateY(0)
            scale(1);

    }

}


.elite-result-close {

    position: absolute;

    top: 15px;

    right: 15px;

    border: 0;

    background: #f1f5f9;

    width: 35px;

    height: 35px;

    border-radius: 50%;

    cursor: pointer;

}


.elite-result-icon {

    width: 75px;

    height: 75px;

    display: grid;

    place-items: center;

    margin: 5px auto 20px;

    border-radius: 50%;

    background: #fef3c7;

    color: #d97706;

    font-size: 32px;

}


.elite-result-modal h2 {

    color: #0f172a;

    margin-bottom: 10px;

}


.elite-result-modal p {

    color: #64748b;

    line-height: 1.6;

}


.elite-result-button {

    margin-top: 20px;

    border: 0;

    padding: 13px 25px;

    border-radius: 13px;

    background: #0f172a;

    color: white;

    font-weight: 800;

    cursor: pointer;

}


/*
|--------------------------------------------------------------------------
| CONFETTIS
|--------------------------------------------------------------------------
*/

.elite-confetti-container {

    position: fixed;

    inset: 0;

    z-index: 10000;

    pointer-events: none;

    overflow: hidden;

}


.elite-confetti {

    position: absolute;

    width: 9px;

    height: 14px;

    top: -20px;

    animation:
        eliteConfettiFall
        linear forwards;

}


@keyframes eliteConfettiFall {

    to {

        transform:
            translateY(110vh)
            rotate(720deg);

        opacity: 0;

    }

}


/*
|--------------------------------------------------------------------------
| MOBILE
|--------------------------------------------------------------------------
*/

@media (max-width: 700px) {

    .elite-colors-grid {

        grid-template-columns:
            repeat(2, 1fr);

    }

    .elite-rules {

        grid-template-columns: 1fr;

    }

}


@media (max-width: 450px) {

    .elite-game-page {

        padding:
            25px 13px 50px;

    }

    .elite-prize-card {

        padding: 20px;

    }

    .prize-icon {

        width: 52px;

        height: 52px;

    }

    .elite-color-card {

        padding: 20px 10px;

    }

    .elite-color-circle {

        width: 58px;

        height: 58px;

    }

}


/*
|--------------------------------------------------------------------------
| LOADING
|--------------------------------------------------------------------------
*/

.elite-confirm-button.loading {

    pointer-events: none;

    opacity: .7;

}


</style>

</head>


<body>


<main class="elite-game-page">

<div class="elite-game-container">


<a
    href="elite.php"
    class="elite-back"
>
    <i class="fas fa-arrow-left"></i>
    Retour à Elite
</a>


<header class="elite-game-header">

    <div class="elite-game-badge">

        <i class="fas fa-crown"></i>

        ELITE MONEY

    </div>


    <h1>
        Tentez votre chance
    </h1>


    <p>

        Choisissez une couleur avant la fermeture
        des participations et participez au prochain
        tirage Elite Money.

    </p>

</header>


<!-- PRIZE -->

<div class="elite-prize-card">

    <div class="prize-icon">

        <i class="fas fa-coins"></i>

    </div>


    <div class="prize-content">

        <span>
            JACKPOT DE LA SÉANCE
        </span>

        <strong>

            <?= number_format(
                $elitePrize,
                0,
                ',',
                ' '
            ) ?>

            FCFA

        </strong>

    </div>

</div>


<!-- =========================================================
     ELITE MONEY — COUNTDOWN
========================================================= -->

<section class="elite-countdown-card" id="eliteCountdownCard">

    <div class="elite-countdown-top">

        <div class="elite-countdown-icon">
            <i class="fas fa-clock"></i>
        </div>

        <div class="elite-countdown-heading">

            <span class="elite-countdown-eyebrow">
                ELITE MONEY
            </span>

            <h3>
                Prochaine séance
            </h3>

            <p>
                Chaque mercredi à 19:00 GMT
            </p>

        </div>

    </div>


    <div
        class="elite-countdown"
        id="eliteCountdown"
        aria-live="polite"
    >

        <div class="elite-time-box">

            <strong id="eliteDays">
                00
            </strong>

            <span>
                JOURS
            </span>

        </div>


        <div class="elite-time-separator">
            :
        </div>


        <div class="elite-time-box">

            <strong id="eliteHours">
                00
            </strong>

            <span>
                HEURES
            </span>

        </div>


        <div class="elite-time-separator">
            :
        </div>


        <div class="elite-time-box">

            <strong id="eliteMinutes">
                00
            </strong>

            <span>
                MIN
            </span>

        </div>


        <div class="elite-time-separator">
            :
        </div>


        <div class="elite-time-box elite-seconds-box">

            <strong id="eliteSeconds">
                00
            </strong>

            <span>
                SEC
            </span>

        </div>

    </div>


    <div class="elite-countdown-status">

        <span class="elite-status-dot"></span>

        <span id="eliteCountdownStatus">
            Prochaine séance en préparation
        </span>

    </div>

</section>

<?php if ($eliteAlreadyPlayed): ?>


<!-- PARTICIPATION EXISTANTE -->

<div class="elite-participation-status">

    <div class="status-check">

        <i class="fas fa-check"></i>

    </div>


    <div>

        <strong>

            Participation enregistrée

        </strong>


        <p>

            Vous avez choisi la couleur

            <b>
                <?= htmlspecialchars(
                    $eliteSelectedColor
                ) ?>
            </b>.

        </p>

    </div>

</div>


<?php else: ?>


<!-- CHOIX -->

<div class="elite-choice-title">

    <span>
        01
    </span>


    <div>

        <strong>
            Choisissez votre couleur
        </strong>

        <small>
            Une seule sélection par séance
        </small>

    </div>

</div>


<div
    class="elite-colors-grid"
    id="eliteColorsGrid"
>

<?php foreach ($eliteColors as $color): ?>

<button
    type="button"
    class="elite-color-card"
    data-color="<?= htmlspecialchars(
        $color['id']
    ) ?>"
    data-color-name="<?= htmlspecialchars(
        $color['name']
    ) ?>"
    style="
        --elite-color:
        <?= htmlspecialchars(
            $color['hex']
        ) ?>;
    "
>

    <span
        class="elite-color-circle"
    ></span>


    <span
        class="elite-color-name"
    >

        <?= htmlspecialchars(
            $color['name']
        ) ?>

    </span>


    <span
        class="elite-color-check"
    >

        <i class="fas fa-check"></i>

    </span>

</button>

<?php endforeach; ?>

</div>


<button
    type="button"
    id="eliteConfirmButton"
    class="elite-confirm-button"
    disabled
>

    <i class="fas fa-dice"></i>

    Participer au tirage

</button>


<?php endif; ?>


<!-- RULES -->

<div class="elite-rules">


<div class="elite-rule">

    <i class="fas fa-shield-halved"></i>

    <div>

        <strong>
            Tirage sécurisé
        </strong>

        <span>
            Le résultat est déterminé côté serveur.
        </span>

    </div>

</div>


<div class="elite-rule">

    <i class="fas fa-user-check"></i>

    <div>

        <strong>
            Une participation
        </strong>

        <span>
            Une seule couleur peut être choisie
            par utilisateur et par séance.
        </span>

    </div>

</div>


<div class="elite-rule">

    <i class="fas fa-trophy"></i>

    <div>

        <strong>
            Un seul gagnant
        </strong>

        <span>
            Le gagnant sera sélectionné parmi
            les participants de la couleur gagnante.
        </span>

    </div>

</div>


</div>

</div>

</main>


<!-- RESULT MODAL -->

<div
    class="elite-result-overlay"
    id="eliteResultOverlay"
>

<div class="elite-result-modal">


<button
    type="button"
    class="elite-result-close"
    id="eliteResultClose"
>

    <i class="fas fa-xmark"></i>

</button>


<div
    id="eliteResultIcon"
    class="elite-result-icon"
>

    <i class="fas fa-trophy"></i>

</div>


<h2 id="eliteResultTitle">
    Résultat
</h2>


<p id="eliteResultMessage">
    Le résultat sera affiché ici.
</p>


<button
    type="button"
    class="elite-result-button"
    id="eliteResultButton"
>

    Continuer

</button>


</div>

</div>


<div
    id="eliteConfetti"
    class="elite-confetti-container"
></div>


<script>

/*
|--------------------------------------------------------------------------
| DONNÉES
|--------------------------------------------------------------------------
*/

const eliteRoundId =
    <?= (int) $roundId ?>;

const eliteRoundTimestamp =
    <?= (int) $nextRoundTimestamp ?>;


/*
|--------------------------------------------------------------------------
| COUNTDOWN
|--------------------------------------------------------------------------
*/


(function () {

    "use strict";


    /*
    |--------------------------------------------------------------------------
    | ELEMENTS
    |--------------------------------------------------------------------------
    */

    const daysElement =
        document.getElementById("eliteDays");

    const hoursElement =
        document.getElementById("eliteHours");

    const minutesElement =
        document.getElementById("eliteMinutes");

    const secondsElement =
        document.getElementById("eliteSeconds");

    const statusElement =
        document.getElementById("eliteCountdownStatus");

    const secondsBox =
        document.querySelector(".elite-seconds-box");


    /*
    |--------------------------------------------------------------------------
    | VERIFICATION
    |--------------------------------------------------------------------------
    */

    if (
        !daysElement ||
        !hoursElement ||
        !minutesElement ||
        !secondsElement
    ) {
        return;
    }


    /*
    |--------------------------------------------------------------------------
    | FORMAT
    |--------------------------------------------------------------------------
    */

    function pad(number) {

        return String(number).padStart(2, "0");

    }


    /*
    |--------------------------------------------------------------------------
    | PROCHAINE SÉANCE
    |--------------------------------------------------------------------------
    |
    | Mercredi à 19:00 GMT.
    |
    */

    function getNextSession() {

        const now = new Date();

        const target =
            new Date(
                now.getTime()
            );


        /*
        | JavaScript getUTCDay()
        |
        | Dimanche = 0
        | Lundi    = 1
        | Mardi    = 2
        | Mercredi = 3
        */

        const currentDay =
            now.getUTCDay();


        let daysUntil =
            (3 - currentDay + 7) % 7;


        /*
        |--------------------------------------------------------------------------
        | Cette semaine
        |--------------------------------------------------------------------------
        */

        target.setUTCDate(
            now.getUTCDate() + daysUntil
        );


        target.setUTCHours(
            19,
            0,
            0,
            0
        );


        /*
        |--------------------------------------------------------------------------
        | Si la séance est déjà passée
        |--------------------------------------------------------------------------
        */

        if (
            target.getTime()
            <=
            now.getTime()
        ) {

            target.setUTCDate(
                target.getUTCDate() + 7
            );

        }


        return target;

    }


    /*
    |--------------------------------------------------------------------------
    | AFFICHAGE
    |--------------------------------------------------------------------------
    */

    function updateCountdown() {

        const now =
            new Date();

        const target =
            getNextSession();


        let difference =
            target.getTime()
            -
            now.getTime();


        if (difference < 0) {

            difference = 0;

        }


        const totalSeconds =
            Math.floor(
                difference / 1000
            );


        const days =
            Math.floor(
                totalSeconds / 86400
            );


        const hours =
            Math.floor(
                (totalSeconds % 86400)
                / 3600
            );


        const minutes =
            Math.floor(
                (totalSeconds % 3600)
                / 60
            );


        const seconds =
            totalSeconds % 60;


        /*
        |--------------------------------------------------------------------------
        | DOM
        |--------------------------------------------------------------------------
        */

        daysElement.textContent =
            pad(days);

        hoursElement.textContent =
            pad(hours);

        minutesElement.textContent =
            pad(minutes);

        secondsElement.textContent =
            pad(seconds);


        /*
        |--------------------------------------------------------------------------
        | Animation seconde
        |--------------------------------------------------------------------------
        */

        if (secondsBox) {

            secondsBox.classList.remove("tick");

            /*
            | Force le navigateur à recalculer
            | l'animation.
            */

            void secondsBox.offsetWidth;

            secondsBox.classList.add("tick");

        }


        /*
        |--------------------------------------------------------------------------
        | MESSAGE
        |--------------------------------------------------------------------------
        */

        if (statusElement) {

            if (days === 0 && hours === 0) {

                statusElement.textContent =
                    "La séance Elite Money approche...";

            } else {

                statusElement.textContent =
                    "Prochaine séance mercredi à 19:00 GMT";

            }

        }

    }


    /*
    |--------------------------------------------------------------------------
    | INITIALISATION
    |--------------------------------------------------------------------------
    */

    updateCountdown();


    /*
    |--------------------------------------------------------------------------
    | ACTUALISATION
    |--------------------------------------------------------------------------
    */

    setInterval(
        updateCountdown,
        1000
    );


})();


/*
|--------------------------------------------------------------------------
| SÉLECTION COULEUR
|--------------------------------------------------------------------------
*/

const colorCards =
    document.querySelectorAll(
        '.elite-color-card'
    );


const confirmButton =
    document.getElementById(
        'eliteConfirmButton'
    );


let selectedColor = null;

let selectedColorName = null;


colorCards.forEach(
    card => {

        card.addEventListener(
            'click',
            function () {

                colorCards.forEach(
                    item => {

                        item.classList.remove(
                            'selected'
                        );

                    }
                );


                this.classList.add(
                    'selected'
                );


                selectedColor =
                    this.dataset.color;


                selectedColorName =
                    this.dataset.colorName;


                if (confirmButton) {

                    confirmButton.disabled =
                        false;

                }

            }
        );

    }
);


/*
|--------------------------------------------------------------------------
| MODAL
|--------------------------------------------------------------------------
*/

const overlay =
    document.getElementById(
        'eliteResultOverlay'
    );


const title =
    document.getElementById(
        'eliteResultTitle'
    );


const message =
    document.getElementById(
        'eliteResultMessage'
    );


const icon =
    document.getElementById(
        'eliteResultIcon'
    );


function showResult(
    resultTitle,
    resultMessage,
    won
) {

    title.textContent =
        resultTitle;

    message.textContent =
        resultMessage;


    if (won) {

        icon.innerHTML =
            '<i class="fas fa-trophy"></i>';

        createConfetti();

    } else {

        icon.innerHTML =
            '<i class="fas fa-heart"></i>';

    }


    overlay.classList.add(
        'show'
    );

}


function closeResult() {

    overlay.classList.remove(
        'show'
    );

}


document
    .getElementById(
        'eliteResultClose'
    )
    .addEventListener(
        'click',
        closeResult
    );


document
    .getElementById(
        'eliteResultButton'
    )
    .addEventListener(
        'click',
        closeResult
    );


/*
|--------------------------------------------------------------------------
| CONFETTIS
|--------------------------------------------------------------------------
*/

function createConfetti() {

    const container =
        document.getElementById(
            'eliteConfetti'
        );


    container.innerHTML = '';


    const colors = [
        '#ef4444',
        '#3b82f6',
        '#22c55e',
        '#facc15',
        '#8b5cf6',
        '#f97316'
    ];


    for (
        let i = 0;
        i < 100;
        i++
    ) {

        const confetti =
            document.createElement(
                'span'
            );


        confetti.className =
            'elite-confetti';


        confetti.style.left =
            Math.random() * 100 + '%';


        confetti.style.background =
            colors[
                Math.floor(
                    Math.random()
                    * colors.length
                )
            ];


        confetti.style.animationDuration =
            (2 + Math.random() * 3)
            + 's';


        confetti.style.animationDelay =
            (Math.random() * .5)
            + 's';


        container.appendChild(
            confetti
        );

    }


    setTimeout(
        () => {

            container.innerHTML = '';

        },
        6000
    );

}


/*
|--------------------------------------------------------------------------
| PARTICIPATION
|--------------------------------------------------------------------------
*/

if (confirmButton) {

    confirmButton.addEventListener(
        'click',
        async function () {

            if (!selectedColor) {

                return;

            }


            if (!eliteRoundId) {

                showResult(
                    'Séance indisponible',
                    'Impossible de charger la séance actuelle. Veuillez réessayer.',
                    false
                );

                return;

            }


            const confirmation =
                confirm(
                    'Confirmer votre choix : '
                    + selectedColorName
                    + ' ?'
                );


            if (!confirmation) {

                return;

            }


            confirmButton.disabled =
                true;


            confirmButton.classList.add(
                'loading'
            );


            confirmButton.innerHTML =
                '<i class="fas fa-spinner fa-spin"></i> Enregistrement...';


            try {

                const formData =
                    new FormData();


                formData.append(
                    'round_id',
                    eliteRoundId
                );


                formData.append(
                    'color_id',
                    selectedColor
                );


                formData.append(
                    'color_name',
                    selectedColorName
                );


                const response =
                    await fetch(
                        '../../api/elite/join.php',
                        {

                            method: 'POST',

                            body: formData,

                            credentials: 'same-origin'

                        }
                    );


                const data =
                    await response.json();


                if (!data.success) {

                    throw new Error(
                        data.message
                        || 'Participation impossible.'
                    );

                }


                showResult(
                    'Participation enregistrée',
                    'Votre choix « '
                    + selectedColorName
                    + ' » a bien été enregistré pour cette séance.',
                    false
                );


                setTimeout(
                    () => {

                        window.location.reload();

                    },
                    1800
                );


            } catch (error) {

                showResult(
                    'Participation impossible',
                    error.message,
                    false
                );


                confirmButton.disabled =
                    false;


                confirmButton.classList.remove(
                    'loading'
                );


                confirmButton.innerHTML =
                    '<i class="fas fa-dice"></i> Participer au tirage';

            }

        }
    );

}



</script>

</body>

</html>
