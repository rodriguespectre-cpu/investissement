<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

/*
|--------------------------------------------------------------------------
| Configuration de la base de données
|--------------------------------------------------------------------------
| Local :
|   DB_HOST=localhost
|   DB_PORT=3306
|   DB_NAME=investment_db
|   DB_USER=root
|   DB_PASSWORD=
|
| Production / Render :
|   Les mêmes variables sont définies dans Render.
|--------------------------------------------------------------------------
*/

$host = $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: 'localhost';
$port = $_ENV['DB_PORT'] ?? getenv('DB_PORT') ?: '3306';
$dbname = $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?: 'investment_db';
$username = $_ENV['DB_USER'] ?? getenv('DB_USER') ?: 'root';
$password = $_ENV['DB_PASSWORD'] ?? getenv('DB_PASSWORD') ?: '';

/*
|--------------------------------------------------------------------------
| PDO
|--------------------------------------------------------------------------
*/

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

$dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";

/*
|--------------------------------------------------------------------------
| SSL Aiven
|--------------------------------------------------------------------------
| DB_SSL=true sera activé sur Render.
| DB_SSL_CA peut contenir le chemin vers le certificat CA.
|--------------------------------------------------------------------------
*/

$sslEnabled = filter_var(
    $_ENV['DB_SSL'] ?? getenv('DB_SSL') ?: 'false',
    FILTER_VALIDATE_BOOLEAN
);

if ($sslEnabled) {
    $caFile = $_ENV['DB_SSL_CA'] ?? getenv('DB_SSL_CA') ?: '';

    if ($caFile !== '' && file_exists($caFile)) {
        $options[Pdo\Mysql::ATTR_SSL_CA] = $caFile;
        $options[Pdo\Mysql::ATTR_SSL_VERIFY_SERVER_CERT] = true;
    } else {
        /*
         * Aiven exige TLS. Si aucun CA n'est encore installé,
         * on utilise TLS sans validation locale du certificat.
         *
         * Nous configurerons le CA Aiven proprement avant la mise
         * en production finale.
         */
        $options[Pdo\Mysql::ATTR_SSL_VERIFY_SERVER_CERT] = false;
    }
}

/*
|--------------------------------------------------------------------------
| Connexion PDO
|--------------------------------------------------------------------------
*/

try {
    $pdo = new PDO(
        $dsn,
        $username,
        $password,
        $options
    );

    $pdo->query("SELECT 1");

} catch (PDOException $e) {

    error_log(
        'Database connection error: ' . $e->getMessage()
    );

    /*
     * Ne jamais afficher les identifiants de connexion en production.
     */

    if (
        (isset($_SERVER['SERVER_NAME']) && $_SERVER['SERVER_NAME'] === 'localhost')
        ||
        (isset($_SERVER['SERVER_ADDR']) && $_SERVER['SERVER_ADDR'] === '127.0.0.1')
    ) {
        die(
            "❌ Erreur de connexion à la base de données : "
            . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8')
        );
    }

    die("❌ Service indisponible. Veuillez réessayer plus tard.");
}

// =============================================
// FONCTIONS UTILITAIRES POUR LA BASE DE DONNÉES
// =============================================

/**
 * Vérifie si l'utilisateur est connecté
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Vérifie si l'utilisateur est administrateur
 */
function isAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

/**
 * Récupère les informations de l'utilisateur connecté
 */
function getCurrentUser() {
    global $pdo;
    if (!isLoggedIn()) return null;
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

/**
 * Récupère le solde de l'utilisateur
 */
function getUserBalance($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT balance, bonus_balance FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch();
    return $result ? $result : ['balance' => 0, 'bonus_balance' => 0];
}

/**
 * Ajoute une notification pour un utilisateur
 */
function addNotification($user_id, $title, $message, $type = 'info', $link = null) {
    global $pdo;
    $stmt = $pdo->prepare("
        INSERT INTO notifications (user_id, type, title, message, link) 
        VALUES (?, ?, ?, ?, ?)
    ");
    return $stmt->execute([$user_id, $type, $title, $message, $link]);
}

/**
 * Ajoute un mouvement dans le solde (balances)
 */
function addBalanceEntry($user_id, $amount, $type, $source, $reference_id = null, $description = '') {
    global $pdo;
    $stmt = $pdo->prepare("
        INSERT INTO balances (user_id, amount, type, source, reference_id, description) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    return $stmt->execute([$user_id, abs($amount), $type, $source, $reference_id, $description]);
}

/**
 * Met à jour le solde principal de l'utilisateur
 */
function updateUserBalance($user_id, $amount, $type, $source, $reference_id = null, $description = '') {
    global $pdo;
    
    $pdo->beginTransaction();
    try {
        // Ajouter l'entrée dans balances
        $stmt = $pdo->prepare("
            INSERT INTO balances (user_id, amount, type, source, reference_id, description) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$user_id, abs($amount), $type, $source, $reference_id, $description]);
        
        // Mettre à jour le solde de l'utilisateur
        $sign = ($type === 'credit') ? '+' : '-';
        $stmt = $pdo->prepare("UPDATE users SET balance = balance $sign ? WHERE id = ?");
        $stmt->execute([abs($amount), $user_id]);
        
        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Met à jour le solde des bonus de l'utilisateur
 */
function updateBonusBalance($user_id, $amount, $source, $reference_id = null, $description = '') {
    global $pdo;
    
    $pdo->beginTransaction();
    try {
        // Ajouter l'entrée dans balances
        $stmt = $pdo->prepare("
            INSERT INTO balances (user_id, amount, type, source, reference_id, description) 
            VALUES (?, ?, 'credit', ?, ?, ?)
        ");
        $stmt->execute([$user_id, abs($amount), $source, $reference_id, $description]);
        
        // Mettre à jour le solde des bonus
        $stmt = $pdo->prepare("UPDATE users SET bonus_balance = bonus_balance + ? WHERE id = ?");
        $stmt->execute([abs($amount), $user_id]);
        
        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Récupère les investissements actifs d'un utilisateur
 */
function getActiveInvestments($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT * FROM investments 
        WHERE user_id = ? AND status = 'active' 
        ORDER BY created_at DESC
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll();
}

/**
 * Récupère les dernières transactions d'un utilisateur
 */
function getRecentTransactions($user_id, $limit = 10) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT * FROM transactions 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT ?
    ");
    $stmt->execute([$user_id, $limit]);
    return $stmt->fetchAll();
}

/**
 * Récupère les notifications non lues d'un utilisateur
 */
function getUnreadNotifications($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT * FROM notifications 
        WHERE user_id = ? AND is_read = 0 
        ORDER BY created_at DESC
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll();
}

/**
 * Marque une notification comme lue
 */
function markNotificationAsRead($notification_id, $user_id) {
    global $pdo;
    $stmt = $pdo->prepare("
        UPDATE notifications 
        SET is_read = 1 
        WHERE id = ? AND user_id = ?
    ");
    return $stmt->execute([$notification_id, $user_id]);
}

/**
 * Récupère les paramètres du site
 */
function getSettings() {
    global $pdo;
    $stmt = $pdo->query("SELECT key_name, value FROM settings");
    $settings = [];
    while ($row = $stmt->fetch()) {
        $settings[$row['key_name']] = $row['value'];
    }
    return $settings;
}

/**
 * Récupère un paramètre spécifique
 */
function getSetting($key) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT value FROM settings WHERE key_name = ?");
    $stmt->execute([$key]);
    $result = $stmt->fetch();
    return $result ? $result['value'] : null;
}

/**
 * Vérifie si l'email est déjà utilisé
 */
function isEmailExists($email) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    return $stmt->rowCount() > 0;
}

/**
 * Vérifie si le nom d'utilisateur est déjà utilisé
 */
function isUsernameExists($username) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    return $stmt->rowCount() > 0;
}

/**
 * Génère un token CSRF sécurisé
 */
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Vérifie le token CSRF
 */
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Nettoie une entrée utilisateur
 */
function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Valide un mot de passe fort
 */
function isStrongPassword($password) {
    return strlen($password) >= 8 &&
           preg_match('/[A-Z]/', $password) &&
           preg_match('/[a-z]/', $password) &&
           preg_match('/[0-9]/', $password) &&
           preg_match('/[^a-zA-Z0-9]/', $password);
}

/**
 * Crée une session sécurisée après connexion
 */
function createSecureSession($user_id, $username, $role) {
    session_regenerate_id(true);
    
    $_SESSION['user_id'] = $user_id;
    $_SESSION['username'] = $username;
    $_SESSION['user_role'] = $role;
    $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'];
    $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
    $_SESSION['created_at'] = time();
}

/**
 * Détruit la session et déconnecte l'utilisateur
 */
function destroySession() {
    $_SESSION = [];
    
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }
    
    session_destroy();
}

