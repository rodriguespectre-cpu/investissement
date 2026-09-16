<?php
session_start();
require_once '../config/database.php';

// Vérifier si l'utilisateur vient de number.php

if (
    !isset($_SESSION['temp_user']) ||
    !is_array($_SESSION['temp_user'])
) {
    header("Location: register.php");
    exit;
}

if (
    !isset($_SESSION['temp_user']['phone']) ||
    trim((string) $_SESSION['temp_user']['phone']) === ''
) {
    header("Location: number.php");
    exit;
}

$error = '';
$success = '';
$user_data = $_SESSION['temp_user'];

// Générer un token CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// =============================================
// CONFIGURATION SMTP (Vos identifiants)
// =============================================
$smtp_config = [
    'host' => 'pro.eu.turbo-smtp.com',
    'port' => 587,
    'username' => '9a20efcdc1b845ed7399',
    'password' => '06xmoUd7fqtNPQV9J1Tu',
    'from_email' => 'rodriguespectre@gmail.com',
    'from_name' => 'SpectreACADEMI'
];

// =============================================
// FONCTIONS
// =============================================

function generateConfirmationCode($length = 6) {
    return strtoupper(substr(str_shuffle('0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, $length));
}

function generateReferralCode($length = 8) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $code = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= $characters[random_int(0, strlen($characters) - 1)];
    }
    return $code;
}

// =============================================
// ENVOI D'EMAIL AVEC SMTP (PHPMailer alternatif)
// =============================================
function sendEmailSMTP($to, $name, $code, $smtp_config) {
    $smtp_host = $smtp_config['host'];
    $smtp_port = $smtp_config['port'];
    $smtp_username = $smtp_config['username'];
    $smtp_password = $smtp_config['password'];
    $from_email = $smtp_config['from_email'];
    $from_name = $smtp_config['from_name'];
    
    $subject = '🔐 Code de confirmation - SpectreACADEMI';
    
    // Message HTML
    $message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; background-color: #f4f4f4; padding: 20px; }
            .container { max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
            .header { text-align: center; border-bottom: 2px solid #667eea; padding-bottom: 20px; }
            .logo { font-size: 28px; font-weight: bold; color: #667eea; }
            .code-box { background: #f7fafc; padding: 20px; text-align: center; margin: 20px 0; border-radius: 8px; border: 2px dashed #667eea; }
            .code { font-size: 32px; font-weight: bold; color: #2d3748; letter-spacing: 8px; }
            .spam-alert { background: #fff5f5; border-left: 4px solid #fc8181; padding: 12px 16px; margin: 15px 0; border-radius: 6px; color: #c53030; font-size: 14px; }
            .footer { text-align: center; margin-top: 30px; font-size: 12px; color: #a0aec0; border-top: 1px solid #e2e8f0; padding-top: 20px; }
            .highlight { color: #667eea; font-weight: bold; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <div class='logo'>📈 InvestPro</div>
                <p style='color: #718096;'>Votre plateforme d'investissement</p>
            </div>
            <h2>Bonjour <span class='highlight'>$name</span>,</h2>
            <p>Merci de vous être inscrit sur SpectreACADEMI. Pour finaliser votre inscription, veuillez utiliser le code de confirmation ci-dessous :</p>
            <div class='code-box'>
                <div class='code'>$code</div>
            </div>
            <p style='text-align: center; color: #718096;'>Ce code est valable <strong>15 minutes</strong>.</p>
            <div class='spam-alert'>
                <strong>📬 Vous ne trouvez pas notre email ?</strong><br>
                Pensez à vérifier votre dossier <strong>SPAM</strong> ou <strong>COURRIERS INDÉSIRABLES</strong>. 
                Ajoutez <strong>$from_email</strong> à vos contacts.
            </div>
            <p style='text-align: center;'>Si vous n'avez pas demandé cette inscription, ignorez simplement cet email.</p>
            <div class='footer'>
                <p>© 2026 InvestPro. Tous droits réservés.</p>
                <p>Cet email a été envoyé automatiquement, merci de ne pas y répondre.</p>
            </div>
        </div>
    </body>
    </html>
    ";

    // Headers
    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=utf-8',
        'From: ' . $from_name . ' <' . $from_email . '>',
        'Reply-To: ' . $from_email,
        'X-Mailer: PHP/' . phpversion()
    ];

    // =============================================
    // MÉTHODE 1: fsockopen avec TLS (Port 587)
    // =============================================
    try {
        $fp = fsockopen('tcp://' . $smtp_host, $smtp_port, $errno, $errstr, 30);
        if (!$fp) {
            throw new Exception("Connection failed: $errstr ($errno)");
        }

        // Lecture du banner
        $response = fgets($fp, 1024);
        if (substr($response, 0, 3) != '220') {
            throw new Exception("SMTP Error: $response");
        }

        // EHLO
        fputs($fp, "EHLO " . gethostname() . "\r\n");
        $response = fgets($fp, 1024);
        while (substr($response, 3, 1) == '-') {
            $response = fgets($fp, 1024);
        }

        // STARTTLS
        fputs($fp, "STARTTLS\r\n");
        $response = fgets($fp, 1024);
        if (substr($response, 0, 3) != '220') {
            throw new Exception("STARTTLS Error: $response");
        }

        // Activer TLS
        if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            throw new Exception("TLS handshake failed");
        }

        // EHLO après TLS
        fputs($fp, "EHLO " . gethostname() . "\r\n");
        $response = fgets($fp, 1024);
        while (substr($response, 3, 1) == '-') {
            $response = fgets($fp, 1024);
        }

        // AUTH LOGIN
        fputs($fp, "AUTH LOGIN\r\n");
        $response = fgets($fp, 1024);
        if (substr($response, 0, 3) != '334') {
            throw new Exception("AUTH LOGIN Error: $response");
        }

        // Username (base64)
        fputs($fp, base64_encode($smtp_username) . "\r\n");
        $response = fgets($fp, 1024);
        if (substr($response, 0, 3) != '334') {
            throw new Exception("Username Error: $response");
        }

        // Password (base64)
        fputs($fp, base64_encode($smtp_password) . "\r\n");
        $response = fgets($fp, 1024);
        if (substr($response, 0, 3) != '235') {
            throw new Exception("Password Error: $response");
        }

        // MAIL FROM
        fputs($fp, "MAIL FROM: <$from_email>\r\n");
        $response = fgets($fp, 1024);
        if (substr($response, 0, 3) != '250') {
            throw new Exception("MAIL FROM Error: $response");
        }

        // RCPT TO
        fputs($fp, "RCPT TO: <$to>\r\n");
        $response = fgets($fp, 1024);
        if (substr($response, 0, 3) != '250') {
            throw new Exception("RCPT TO Error: $response");
        }

        // DATA
        fputs($fp, "DATA\r\n");
        $response = fgets($fp, 1024);
        if (substr($response, 0, 3) != '354') {
            throw new Exception("DATA Error: $response");
        }

        // Envoi du contenu
        $headers_str = implode("\r\n", $headers);
        fputs($fp, "Subject: $subject\r\n");
        fputs($fp, "$headers_str\r\n");
        fputs($fp, "\r\n");
        fputs($fp, $message);
        fputs($fp, "\r\n.\r\n");

        $response = fgets($fp, 1024);
        if (substr($response, 0, 3) != '250') {
            throw new Exception("Message Error: $response");
        }

        // QUIT
        fputs($fp, "QUIT\r\n");
        fclose($fp);
        
        error_log("✅ Email envoyé avec succès à $to via SMTP TLS");
        return true;

    } catch (Exception $e) {
        error_log("❌ SMTP Error: " . $e->getMessage());
        
        // Fallback: Méthode alternative
        return sendEmailFallback($to, $name, $code, $smtp_config);
    }
}

// =============================================
// MÉTHODE FALLBACK: mail() avec encodage
// =============================================
function sendEmailFallback($to, $name, $code, $smtp_config) {
    $from_email = $smtp_config['from_email'];
    $from_name = $smtp_config['from_name'];
    
    $subject = '🔐 Code de confirmation - SpectreACADEMI';
    
    $message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; }
            .code { font-size: 24px; font-weight: bold; color: #667eea; }
        </style>
    </head>
    <body>
        <h2>Bonjour $name,</h2>
        <p>Votre code de confirmation est :</p>
        <h1 class='code'>$code</h1>
        <p>Ce code est valable 15 minutes.</p>
        <p>Si vous n'avez pas demandé cette inscription, ignorez cet email.</p>
        <p>© 2026 SpectreACADEMI</p>
    </body>
    </html>
    ";

    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=utf-8',
        'From: ' . $from_name . ' <' . $from_email . '>'
    ];

    $headers_str = implode("\r\n", $headers);
    
    error_log("🔄 Tentative d'envoi via mail() vers $to");
    
    if (mail($to, $subject, $message, $headers_str)) {
        error_log("✅ Email envoyé via mail() vers $to");
        return true;
    } else {
        error_log("❌ Échec via mail() vers $to");
        return false;
    }
}

// =============================================
// FONCTION PRINCIPALE D'ENVOI
// =============================================
function sendConfirmationEmail($to, $name, $code, $smtp_config) {
    // Log
    error_log("=== TENTATIVE D'ENVOI D'EMAIL ===");
    error_log("Destinataire: $to");
    error_log("Nom: $name");
    error_log("Code: $code");
    error_log("Méthode: SMTP TLS");
    
    return sendEmailSMTP($to, $name, $code, $smtp_config);
}

// =============================================
// GÉNÉRATION DU CODE
// =============================================

if (!isset($_SESSION['temp_user']['confirmation_code']) || 
    !isset($_SESSION['temp_user']['code_sent_at']) ||
    (time() - $_SESSION['temp_user']['code_sent_at']) > 900) {
    
    $confirmation_code = generateConfirmationCode();
    $_SESSION['temp_user']['confirmation_code'] = $confirmation_code;
    $_SESSION['temp_user']['code_sent_at'] = time();
    
    $email = $user_data['email'];
    $nom = $user_data['username'] ?? $user_data['nom_complet'];
    
    $email_sent = sendConfirmationEmail($email, $nom, $confirmation_code, $smtp_config);
    
    if ($email_sent) {
        $_SESSION['temp_user']['code_sent'] = true;
        $success = "✅ Un code de confirmation a été envoyé à votre adresse email.";
    } else {
        $_SESSION['temp_user']['code_sent'] = false;
        $error = "⚠️ L'email de confirmation n'a pas pu être envoyé. Vérifiez votre adresse email.";
    }
}

$referral_code = generateReferralCode();
$default_sponsor = 'InvestPro';

// Liste des pays
function getCountries() {
    return [
        'CM' => ['name' => 'Cameroun', 'code' => '+237', 'flag' => '🇨🇲'],
        'FR' => ['name' => 'France', 'code' => '+33', 'flag' => '🇫🇷'],
        'BE' => ['name' => 'Belgique', 'code' => '+32', 'flag' => '🇧🇪'],
        'CH' => ['name' => 'Suisse', 'code' => '+41', 'flag' => '🇨🇭'],
        'CA' => ['name' => 'Canada', 'code' => '+1', 'flag' => '🇨🇦'],
        'US' => ['name' => 'États-Unis', 'code' => '+1', 'flag' => '🇺🇸'],
        'GB' => ['name' => 'Royaume-Uni', 'code' => '+44', 'flag' => '🇬🇧'],
        'DE' => ['name' => 'Allemagne', 'code' => '+49', 'flag' => '🇩🇪'],
        'IT' => ['name' => 'Italie', 'code' => '+39', 'flag' => '🇮🇹'],
        'ES' => ['name' => 'Espagne', 'code' => '+34', 'flag' => '🇪🇸'],
        'PT' => ['name' => 'Portugal', 'code' => '+351', 'flag' => '🇵🇹'],
        'NL' => ['name' => 'Pays-Bas', 'code' => '+31', 'flag' => '🇳🇱'],
        'LU' => ['name' => 'Luxembourg', 'code' => '+352', 'flag' => '🇱🇺'],
        'SN' => ['name' => 'Sénégal', 'code' => '+221', 'flag' => '🇸🇳'],
        'CI' => ['name' => 'Côte d\'Ivoire', 'code' => '+225', 'flag' => '🇨🇮'],
        'BF' => ['name' => 'Burkina Faso', 'code' => '+226', 'flag' => '🇧🇫'],
        'ML' => ['name' => 'Mali', 'code' => '+223', 'flag' => '🇲🇱'],
        'NE' => ['name' => 'Niger', 'code' => '+227', 'flag' => '🇳🇪'],
        'TG' => ['name' => 'Togo', 'code' => '+228', 'flag' => '🇹🇬'],
        'BJ' => ['name' => 'Bénin', 'code' => '+229', 'flag' => '🇧🇯'],
        'GA' => ['name' => 'Gabon', 'code' => '+241', 'flag' => '🇬🇦'],
        'CG' => ['name' => 'Congo', 'code' => '+242', 'flag' => '🇨🇬'],
        'CD' => ['name' => 'RDC', 'code' => '+243', 'flag' => '🇨🇩'],
        'AO' => ['name' => 'Angola', 'code' => '+244', 'flag' => '🇦🇴'],
        'GH' => ['name' => 'Ghana', 'code' => '+233', 'flag' => '🇬🇭'],
        'NG' => ['name' => 'Nigeria', 'code' => '+234', 'flag' => '🇳🇬'],
        'ZA' => ['name' => 'Afrique du Sud', 'code' => '+27', 'flag' => '🇿🇦'],
        'MA' => ['name' => 'Maroc', 'code' => '+212', 'flag' => '🇲🇦'],
        'DZ' => ['name' => 'Algérie', 'code' => '+213', 'flag' => '🇩🇿'],
        'TN' => ['name' => 'Tunisie', 'code' => '+216', 'flag' => '🇹🇳'],
    ];
}

$countries = getCountries();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /*
    |--------------------------------------------------------------------------
    | CSRF
    |--------------------------------------------------------------------------
    */

    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals(
            $_SESSION['csrf_token'] ?? '',
            (string) $_POST['csrf_token']
        )
    ) {

        $error = "Erreur de sécurité. Veuillez réessayer.";

    } else {

        $confirmationCodeInput = strtoupper(
            trim($_POST['confirmation_code'] ?? '')
        );

        $terms = isset($_POST['terms']);

        $errors = [];

        /*
        |--------------------------------------------------------------------------
        | CODE DE CONFIRMATION
        |--------------------------------------------------------------------------
        */

        $sessionCode =
            $_SESSION['temp_user']['confirmation_code'] ?? '';

        if ($confirmationCodeInput === '') {

            $errors[] =
                "Veuillez entrer le code de confirmation.";

        } elseif (
            $sessionCode === '' ||
            !hash_equals(
                (string) $sessionCode,
                $confirmationCodeInput
            )
        ) {

            $errors[] =
                "❌ Code de confirmation incorrect.";

        }

        /*
        |--------------------------------------------------------------------------
        | EXPIRATION
        |--------------------------------------------------------------------------
        */

        if (
            empty($errors) &&
            isset($_SESSION['temp_user']['code_sent_at'])
        ) {

            $timeElapsed =
                time() -
                (int) $_SESSION['temp_user']['code_sent_at'];

            if ($timeElapsed > 900) {

                $errors[] =
                    "⏰ Le code a expiré. Veuillez demander un nouveau code.";

                unset(
                    $_SESSION['temp_user']['confirmation_code'],
                    $_SESSION['temp_user']['code_sent_at']
                );
            }
        }

        /*
        |--------------------------------------------------------------------------
        | CONDITIONS
        |--------------------------------------------------------------------------
        */

        if (!$terms) {

            $errors[] =
                "Vous devez accepter les conditions d'utilisation.";

        }

        /*
        |--------------------------------------------------------------------------
        | CRÉATION DU COMPTE
        |--------------------------------------------------------------------------
        */

        if (empty($errors)) {

            try {

                $nomComplet =
                    trim(
                        $user_data['nom_complet']
                        ?? $user_data['username']
                        ?? ''
                    );

                $email =
                    trim(
                        $user_data['email']
                        ?? ''
                    );

                $password =
                    $user_data['password']
                    ?? '';

                /*
                |--------------------------------------------------------------------------
                | NUMÉRO WHATSAPP
                |--------------------------------------------------------------------------
                |
                | number.php doit enregistrer "whatsapp".
                | On accepte également "phone" pour compatibilité.
                |
                */

                $phone =
                    trim(
                        (string) (
                            $user_data['whatsapp']
                            ?? $user_data['phone']
                            ?? ''
                        )
                    );

                $countryCode =
                    strtoupper(
                        trim(
                            (string) (
                                $user_data['country_code']
                                ?? ''
                            )
                        )
                    );

                /*
                |--------------------------------------------------------------------------
                | VALIDATION
                |--------------------------------------------------------------------------
                */

                if ($nomComplet === '') {

                    $errors[] =
                        "Le nom est manquant.";

                }

                if (
                    $email === '' ||
                    !filter_var($email, FILTER_VALIDATE_EMAIL)
                ) {

                    $errors[] =
                        "L'adresse email est invalide.";

                }

                if ($password === '') {

                    $errors[] =
                        "Le mot de passe est manquant.";

                }

                if ($phone === '') {

                    $errors[] =
                        "Le numéro WhatsApp est manquant.";

                }

                if (
                    $countryCode === '' ||
                    !isset($countries[$countryCode])
                ) {

                    $errors[] =
                        "Le pays est invalide.";

                }

                /*
                |--------------------------------------------------------------------------
                | EMAIL DÉJÀ UTILISÉ
                |--------------------------------------------------------------------------
                */

                if (empty($errors)) {

                    $stmt = $pdo->prepare("
                        SELECT id
                        FROM users
                        WHERE email = ?
                        LIMIT 1
                    ");

                    $stmt->execute([
                        $email
                    ]);

                    if ($stmt->fetchColumn()) {

                        $errors[] =
                            "Cet email est déjà utilisé.";

                    }

                }

                /*
                |--------------------------------------------------------------------------
                | CRÉATION
                |--------------------------------------------------------------------------
                */

                if (empty($errors)) {

                    $hashedPassword =
                        password_hash(
                            $password,
                            PASSWORD_BCRYPT,
                            ['cost' => 12]
                        );

                    $verificationToken =
                        bin2hex(
                            random_bytes(32)
                        );

                    /*
                    |--------------------------------------------------------------------------
                    | PARRAINAGE
                    |--------------------------------------------------------------------------
                    */

                    $referralCode =
                        trim(
                            $_SESSION['referral_code']
                            ?? ''
                        );

                    $referrerId = null;

                    if ($referralCode !== '') {

                        /*
                        | Cette requête est exécutée seulement
                        | si un code de parrainage existe.
                        */

                        $stmt = $pdo->prepare("
                            SELECT id
                            FROM users
                            WHERE referral_code = ?
                            LIMIT 1
                        ");

                        $stmt->execute([
                            $referralCode
                        ]);

                        $foundReferrer =
                            $stmt->fetchColumn();

                        if ($foundReferrer !== false) {

                            $referrerId =
                                (int) $foundReferrer;

                        }

                    }

                    /*
                    |--------------------------------------------------------------------------
                    | TRANSACTION
                    |--------------------------------------------------------------------------
                    */

                    $pdo->beginTransaction();

                    /*
                    |--------------------------------------------------------------------------
                    | GÉNÉRER LE CODE DE PARRAINAGE
                    |--------------------------------------------------------------------------
                    */

                    do {

                        $newReferralCode =
                            generateReferralCode();

                        $stmt = $pdo->prepare("
                            SELECT id
                            FROM users
                            WHERE referral_code = ?
                            LIMIT 1
                        ");

                        $stmt->execute([
                            $newReferralCode
                        ]);

                        $codeExists =
                            $stmt->fetchColumn();

                    } while ($codeExists);

                    /*
                    |--------------------------------------------------------------------------
                    | INSERT USER
                    |--------------------------------------------------------------------------
                    */

                    $stmt = $pdo->prepare("
                        INSERT INTO users
                        (
                            username,
                            email,
                            country_code,
                            phone,
                            currency_code,
                            language,
                            language_code,
                            password,
                            referral_code,
                            referred_by,
                            verification_token,
                            role,
                            email_verified,
                            is_active,
                            created_at
                        )
                        VALUES
                        (
                            ?,
                            ?,
                            ?,
                            ?,
                            'XAF',
                            'fr',
                            'fr',
                            ?,
                            ?,
                            ?,
                            ?,
                            'user',
                            1,
                            1,
                            NOW()
                        )
                    ");

                    $stmt->execute([
                        $nomComplet,
                        $email,
                        $countryCode,
                        $phone,
                        $hashedPassword,
                        $newReferralCode,
                        $referrerId,
                        $verificationToken
                    ]);

                    $userId =
                        (int) $pdo->lastInsertId();

                    /*
                    |--------------------------------------------------------------------------
                    | NOTIFICATION
                    |--------------------------------------------------------------------------
                    */

                    $stmt = $pdo->prepare("
                        INSERT INTO notifications
                        (
                            user_id,
                            type,
                            title,
                            message,
                            is_read
                        )
                        VALUES
                        (
                            ?,
                            'success',
                            ?,
                            ?,
                            0
                        )
                    ");

                    $stmt->execute([
                        $userId,
                        'Bienvenue sur InvestPro',
                        'Votre compte a été créé avec succès. Bienvenue sur InvestPro !'
                    ]);

                    /*
                    |--------------------------------------------------------------------------
                    | COMMIT
                    |--------------------------------------------------------------------------
                    */

                    $pdo->commit();

                    /*
                    |--------------------------------------------------------------------------
                    | SESSION
                    |--------------------------------------------------------------------------
                    */

                    unset(
                        $_SESSION['temp_user'],
                        $_SESSION['referral_code']
                    );

                    $_SESSION['user_id'] =
                        $userId;

                    $_SESSION['username'] =
                        $nomComplet;

                    $_SESSION['user_role'] =
                        'user';

                    $_SESSION['email'] =
                        $email;

                    /*
                    |--------------------------------------------------------------------------
                    | REDIRECTION
                    |--------------------------------------------------------------------------
                    */

                    header(
                        "Location: dashboard/index.php"
                    );

                    exit;
                }

                /*
                | Si une erreur de validation est apparue
                | après le début de la transaction.
                */

                if (
                    !empty($errors) &&
                    $pdo->inTransaction()
                ) {

                    $pdo->rollBack();

                }

            } catch (Throwable $e) {

                if ($pdo->inTransaction()) {

                    $pdo->rollBack();

                }

                error_log(
                    "Erreur création compte : " .
                    $e->getMessage()
                );

                $errors[] =
                    "❌ Une erreur technique est survenue. Veuillez réessayer.";
            }
        }

        /*
        |--------------------------------------------------------------------------
        | AFFICHAGE DES ERREURS
        |--------------------------------------------------------------------------
        */

        if (!empty($errors)) {

            $error =
                implode(
                    "<br>",
                    $errors
                );

        }
    }
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmation - SpectreACADEMI</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/register.css">
    <style>
        .confirmation-info {
            background: #f7fafc;
            padding: 15px 20px;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            margin-bottom: 15px;
        }
        .confirmation-info p {
            margin: 5px 0;
            color: #4a5568;
            font-size: 0.95rem;
        }
        .confirmation-info strong {
            color: #667eea;
        }

        .spam-notification {
            background: #fef9e7;
            border: 2px solid #f39c12;
            border-radius: 12px;
            padding: 16px 20px;
            margin: 15px 0;
            display: flex;
            align-items: flex-start;
            gap: 14px;
            animation: pulse-border 2s ease-in-out infinite;
        }
        @keyframes pulse-border {
            0%, 100% { border-color: #f39c12; }
            50% { border-color: #e67e22; }
        }
        .spam-notification .spam-icon { font-size: 2rem; flex-shrink: 0; margin-top: 2px; }
        .spam-notification .spam-content { flex: 1; }
        .spam-notification .spam-content h4 {
            margin: 0 0 6px 0;
            color: #e67e22;
            font-size: 1rem;
            font-weight: 700;
        }
        .spam-notification .spam-content p {
            margin: 4px 0;
            color: #7f8c8d;
            font-size: 0.92rem;
            line-height: 1.5;
        }
        .spam-notification .spam-content .spam-highlight {
            background: #f1c40f;
            color: #2c3e50;
            padding: 2px 8px;
            border-radius: 4px;
            font-weight: 700;
            font-size: 0.85rem;
        }
        .spam-notification .spam-content .email-highlight {
            color: #2980b9;
            font-weight: 600;
            font-family: monospace;
        }

        .code-input {
            text-align: center;
            font-size: 2rem !important;
            letter-spacing: 10px;
            font-weight: 700;
            padding: 15px !important;
        }

        .resend-link {
            display: inline-block;
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            margin-top: 10px;
            transition: color 0.3s ease;
        }
        .resend-link:hover {
            color: #764ba2;
            text-decoration: underline;
        }

        .btn-group {
            display: flex;
            gap: 15px;
            margin-top: 10px;
        }
        .btn-group .btn-back {
            background: #e2e8f0;
            color: #4a5568;
            border: none;
            padding: 16px 35px;
            border-radius: 50px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            flex: 1;
            text-decoration: none;
            text-align: center;
        }
        .btn-group .btn-back:hover {
            background: #cbd5e0;
            transform: translateY(-2px);
        }
        .btn-group .btn-submit { flex: 2; }

        .step-indicator {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
        }
        .step-indicator .step {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background: #e2e8f0;
            color: #a0aec0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.9rem;
        }
        .step-indicator .step.active {
            background: #667eea;
            color: white;
        }
        .step-indicator .step.completed {
            background: #48bb78;
            color: white;
        }
        .step-indicator .step-line {
            flex: 1;
            height: 2px;
            background: #e2e8f0;
        }
        .step-indicator .step-line.active {
            background: #667eea;
        }

        .referral-info {
            background: #f7fafc;
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 0.95rem;
            color: #4a5568;
            border: 1px solid #e2e8f0;
        }
        .referral-info strong {
            color: #667eea;
        }

        .debug-info {
            background: #2d3748;
            color: #68d391;
            padding: 12px 16px;
            border-radius: 8px;
            font-family: monospace;
            font-size: 0.85rem;
            margin: 10px 0;
            display: none;
        }

        @media (max-width: 768px) {
            .btn-group { flex-direction: column; }
            .spam-notification { flex-direction: column; align-items: center; text-align: center; }
        }
    </style>
</head>
<body>
    <div class="register-page">
        <div class="register-container">
            <div class="register-form-wrapper">
                <div class="register-header">
                    <div class="step-indicator">
                        <span class="step completed">✓</span>
                        <span class="step-line active"></span>
                        <span class="step completed">✓</span>
                        <span class="step-line active"></span>
                        <span class="step active">3</span>
                    </div>
                    <h1>Créer un compte</h1>
                    <p>Confirmation et parrainage</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <span class="alert-icon">⚠️</span>
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <span class="alert-icon">✅</span>
                        <?php echo $success; ?>
                    </div>
                <?php endif; ?>

                <div class="spam-notification">
                    <div class="spam-icon">📬</div>
                    <div class="spam-content">
                        <h4>🔍 Vous ne trouvez pas notre email ?</h4>
                        <p>
                            Le code de confirmation a été envoyé à 
                            <span class="email-highlight"><?php echo htmlspecialchars($user_data['email']); ?></span>
                        </p>
                        <p>
                            <span class="spam-highlight">📌 ASTUCE</span> 
                            Pensez à vérifier votre dossier 
                            <strong>« SPAM »</strong> ou 
                            <strong>« COURRIERS INDÉSIRABLES »</strong> 
                            dans votre boîte Gmail.
                        </p>
                        <p style="font-size: 0.85rem; color: #95a5a6; margin-top: 6px;">
                            💡 Ajoutez <strong>rodriguespectre@gmail.com</strong> à vos contacts 
                            pour recevoir nos emails dans votre boîte de réception.
                        </p>
                    </div>
                </div>

                <div class="confirmation-info">
                    <p>📧 Un code a été envoyé à : <strong><?php echo htmlspecialchars($user_data['email']); ?></strong></p>
                    <p>⏱️ Valable <strong>15 minutes</strong></p>
                    <?php if (isset($_SESSION['temp_user']['code_sent']) && $_SESSION['temp_user']['code_sent'] === false): ?>
                        <p style="color: #ed8936;">⚠️ L'email n'a pas pu être envoyé, mais vous pouvez continuer avec le code ci-dessous pour le test :</p>
                        <p style="color: #667eea; font-weight: bold; font-size: 1.2rem;">
                            Code de test : <?php echo $_SESSION['temp_user']['confirmation_code']; ?>
                        </p>
                    <?php endif; ?>
                </div>

                
                <form method="POST" action="" class="register-form">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                    <div class="form-group">
                        <label for="confirmation_code">Code de confirmation</label>
                        <div class="input-wrapper">
                            <input 
                                type="text" 
                                id="confirmation_code" 
                                name="confirmation_code" 
                                placeholder="Ex: A1B2C3" 
                                class="code-input"
                                required
                                maxlength="6"
                                autocomplete="off"
                            >
                        </div>
                        <a href="confirm_code_send.php?resend=1" class="resend-link">🔄 Renvoyer le code</a>
                    </div>

                    <div class="form-group">
                        <label for="referral_code">Code parrain</label>
                        <input 
                            type="text" 
                            id="referral_code" 
                            name="referral_code" 
                            value="<?php echo htmlspecialchars($referral_code); ?>"
                            class="referral-input"
                            readonly
                            style="width: 100%; padding: 13px 16px; border: 2px solid #e2e8f0; border-radius: 12px; font-size: 1rem; background: #f7fafc; color: #2d3748; cursor: not-allowed; opacity: 0.8;"
                        >
                        <div class="referral-info">
                            👤 Parrain: <strong><?php echo $default_sponsor; ?></strong>
                        </div>
                    </div>

                    <div class="form-group terms-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="terms" id="terms" required>
                            <span class="checkmark"></span>
                            J'accepte les conditions d'utilisation
                        </label>
                    </div>

                    <div class="btn-group">
                        <a href="number.php" class="btn-back">← Précédent</a>
                        <button type="submit" class="btn-submit" id="submitBtn">S'inscrire</button>
                    </div>

                    <div class="register-footer">
                        <p>Déjà un compte ? <a href="../login.php">Connexion</a></p>
                    </div>
                </form>
            </div>

            <div class="register-illustration">
                <div class="illustration-content">
                    <div class="illustration-icon">✅</div>
                    <h2>Étape 3 sur 3</h2>
                    <p>Finalisez votre inscription en confirmant votre adresse email.</p>
                    <div class="illustration-features">
                        <div class="feature-item"><span>✓</span> Confirmation par email</div>
                        <div class="feature-item"><span>✓</span> Code de parrainage</div>
                        <div class="feature-item"><span>✓</span> Sécurité renforcée</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const codeInput = document.getElementById('confirmation_code');
            
            codeInput.addEventListener('input', function() {
                this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
                if (this.value.length > 6) {
                    this.value = this.value.slice(0, 6);
                }
            });

            codeInput.addEventListener('blur', function() {
                if (this.value.length > 0 && this.value.length < 6) {
                    this.style.borderColor = '#fc8181';
                    this.style.background = '#fff5f5';
                } else if (this.value.length === 6) {
                    this.style.borderColor = '#68d391';
                    this.style.background = '#f0fff4';
                } else {
                    this.style.borderColor = '';
                    this.style.background = '';
                }
            });

            setTimeout(() => {
                codeInput.focus();
            }, 500);
        });
    </script>
</body>
</html>
