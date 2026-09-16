<?php

session_start();

require_once '../../config/database.php';

if (!isLoggedIn()) {
    http_response_code(401);
    exit('Non autorisé');
}

$user = getCurrentUser();

if (!$user) {
    http_response_code(401);
    exit('Utilisateur introuvable');
}

$userId = (int) $user['id'];

$action = $_POST['action'] ?? '';
$notificationId = filter_input(
    INPUT_POST,
    'notification_id',
    FILTER_VALIDATE_INT
);

try {

    switch ($action) {

        /*
        |--------------------------------------------------------------------------
        | MARQUER UNE NOTIFICATION COMME LUE
        |--------------------------------------------------------------------------
        */

        case 'read':

            if (!$notificationId) {
                throw new Exception('Notification invalide.');
            }

            $stmt = $pdo->prepare("
                UPDATE notifications
                SET is_read = 1
                WHERE id = ?
                  AND user_id = ?
            ");

            $stmt->execute([
                $notificationId,
                $userId
            ]);

            break;


        /*
        |--------------------------------------------------------------------------
        | TOUT MARQUER COMME LU
        |--------------------------------------------------------------------------
        */

        case 'read_all':

            $stmt = $pdo->prepare("
                UPDATE notifications
                SET is_read = 1
                WHERE user_id = ?
                  AND is_read = 0
            ");

            $stmt->execute([
                $userId
            ]);

            break;


        /*
        |--------------------------------------------------------------------------
        | SUPPRIMER UNE NOTIFICATION
        |--------------------------------------------------------------------------
        */

        case 'delete':

            if (!$notificationId) {
                throw new Exception('Notification invalide.');
            }

            $stmt = $pdo->prepare("
                DELETE FROM notifications
                WHERE id = ?
                  AND user_id = ?
            ");

            $stmt->execute([
                $notificationId,
                $userId
            ]);

            break;


        /*
        |--------------------------------------------------------------------------
        | SUPPRIMER TOUTES LES NOTIFICATIONS
        |--------------------------------------------------------------------------
        */

        case 'delete_all':

            $stmt = $pdo->prepare("
                DELETE FROM notifications
                WHERE user_id = ?
            ");

            $stmt->execute([
                $userId
            ]);

            break;


        default:

            throw new Exception(
                'Action de notification inconnue.'
            );
    }

    header('Location: index.php');
    exit;

} catch (Throwable $e) {

    http_response_code(500);

    echo 'Erreur : ' .
        htmlspecialchars($e->getMessage());

    exit;
}
