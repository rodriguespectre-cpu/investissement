<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

$config = require __DIR__ . '/../config/elite_game.php';

date_default_timezone_set('UTC');


/*
|--------------------------------------------------------------------------
| PROCHAINE SÉANCE
|--------------------------------------------------------------------------
*/

$now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

$next = $now->modify('next Wednesday');

$next = $next->setTime(
    (int) $config['draw_hour'],
    (int) $config['draw_minute'],
    0
);


/*
|--------------------------------------------------------------------------
| SI NOUS SOMMES AVANT LA SÉANCE DE CETTE SEMAINE
|--------------------------------------------------------------------------
*/

$currentWednesday = $now->modify('Wednesday this week');

$currentWednesday = $currentWednesday->setTime(
    (int) $config['draw_hour'],
    (int) $config['draw_minute'],
    0
);

if ($now < $currentWednesday) {
    $next = $currentWednesday;
}


$scheduledAt =
    $next->format('Y-m-d H:i:s');


/*
|--------------------------------------------------------------------------
| CRÉATION
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    INSERT IGNORE INTO elite_game_sessions
    (
        scheduled_at,
        prize_amount,
        status
    )
    VALUES
    (
        ?,
        ?,
        'open'
    )
");

$stmt->execute([

    $scheduledAt,

    (float) $config['prize_amount']

]);


echo "Prochaine séance : {$scheduledAt} UTC\n";
