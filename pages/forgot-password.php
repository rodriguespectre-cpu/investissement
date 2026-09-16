<?php

session_start();

require_once __DIR__ . '/../config/database.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Veuillez entrer une adresse email valide.";
    } else {

        $stmt = $pdo->prepare("
            SELECT id, username, email
            FROM users
            WHERE email = ?
            LIMIT 1
        ");

        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        /*
         * Message volontairement générique :
         * cela évite de révéler si un email existe dans la base.
         */
        $genericMessage =
            "Si cette adresse correspond à un compte, "
            . "un code de vérification vient d'être envoyé.";

        if (!$user) {
            $success = $genericMessage;
        } else {

            $code = (string)random_int(100000, 999999);

            $_SESSION['password_reset'] = [
                'user_id' => (int)$user['id'],
                'email' => $user['email'],
                'code_hash' => password_hash($code, PASSWORD_DEFAULT),
                'expires_at' => time() + 600,
                'attempts' => 0,
            ];

            $smtp = require __DIR__ . '/../config/smtp.php';

            try {

                $fp = fsockopen(
                    'tcp://' . $smtp['host'],
                    $smtp['port'],
                    $errno,
                    $errstr,
                    30
                );

                if (!$fp) {
                    throw new Exception("Connexion SMTP impossible.");
                }

                $read = function () use ($fp) {
                    $response = '';
                    while (($line = fgets($fp, 515)) !== false) {
                        $response .= $line;
                        if (isset($line[3]) && $line[3] === ' ') {
                            break;
                        }
                    }
                    return $response;
                };

                $write = function ($command) use ($fp) {
                    fwrite($fp, $command . "\r\n");
                };

                $read();

                $write("EHLO localhost");
                $read();

                $write("STARTTLS");
                $response = $read();

                if (strpos($response, '220') !== 0) {
                    throw new Exception("STARTTLS refusé.");
                }

                if (!stream_socket_enable_crypto(
                    $fp,
                    true,
                    STREAM_CRYPTO_METHOD_TLS_CLIENT
                )) {
                    throw new Exception("TLS impossible.");
                }

                $write("EHLO localhost");
                $read();

                $write("AUTH LOGIN");
                $read();

                $write(base64_encode($smtp['username']));
                $read();

                $write(base64_encode($smtp['password']));
                $response = $read();

                if (strpos($response, '235') !== 0) {
                    throw new Exception("Authentification SMTP refusée.");
                }

                $write("MAIL FROM:<{$smtp['from_email']}>");
                $read();

                $write("RCPT TO:<{$user['email']}>");
                $read();

                $write("DATA");
                $read();

                $subject = "Code de réinitialisation - InvestPro";

                $body = "
<html>
<body style=\"font-family:Arial,sans-serif;\">
<h2>Réinitialisation du mot de passe</h2>

<p>Bonjour " .
htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8') .
",</p>

<p>Voici votre code de vérification :</p>

<div style=\"
font-size:32px;
font-weight:bold;
letter-spacing:8px;
padding:20px;
background:#f3f4f6;
display:inline-block;
\">
{$code}
</div>

<p>Ce code expire dans <strong>10 minutes</strong>.</p>

<p>Si vous n'êtes pas à l'origine de cette demande,
ignorez simplement cet email.</p>

<p>InvestPro</p>
</body>
</html>
";

                $headers =
                    "From: {$smtp['from_name']} <{$smtp['from_email']}>\r\n" .
                    "To: {$user['email']}\r\n" .
                    "Subject: {$subject}\r\n" .
                    "MIME-Version: 1.0\r\n" .
                    "Content-Type: text/html; charset=UTF-8\r\n";

                $write($headers . "\r\n" . $body . "\r\n.");

                $response = $read();

                if (strpos($response, '250') !== 0) {
                    throw new Exception("Email non accepté par le serveur SMTP.");
                }

                $write("QUIT");
                fclose($fp);

                $_SESSION['password_reset']['sent_at'] = time();

                header("Location: verify-reset-code.php");
                exit;

            } catch (Throwable $e) {

                unset($_SESSION['password_reset']);

                error_log(
                    "Password reset SMTP error: " . $e->getMessage()
                );

                $error =
                    "Impossible d'envoyer le code pour le moment. "
                    . "Veuillez réessayer.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mot de passe oublié - InvestPro</title>

<style>
* {
    box-sizing:border-box;
}

body {
    margin:0;
    min-height:100vh;
    display:flex;
    align-items:center;
    justify-content:center;
    font-family:Arial,sans-serif;
    background:#f5f7fb;
}

.card {
    width:100%;
    max-width:430px;
    margin:20px;
    padding:35px;
    background:#fff;
    border-radius:20px;
    box-shadow:0 15px 45px rgba(0,0,0,.08);
}

h1 {
    margin-top:0;
}

p {
    color:#64748b;
}

input {
    width:100%;
    padding:14px;
    margin:10px 0 15px;
    border:1px solid #dbe1ea;
    border-radius:10px;
    font-size:16px;
}

button {
    width:100%;
    padding:14px;
    border:0;
    border-radius:10px;
    background:#2563eb;
    color:white;
    font-size:16px;
    cursor:pointer;
}

.error {
    padding:12px;
    background:#fee2e2;
    color:#991b1b;
    border-radius:10px;
    margin-bottom:15px;
}

.success {
    padding:12px;
    background:#dcfce7;
    color:#166534;
    border-radius:10px;
    margin-bottom:15px;
}

a {
    display:block;
    margin-top:20px;
    text-align:center;
    color:#2563eb;
    text-decoration:none;
}
</style>
</head>

<body>

<div class="card">

    <h1>Mot de passe oublié ?</h1>

    <p>
        Entrez l'adresse email associée à votre compte.
        Nous vous enverrons un code de vérification.
    </p>

    <?php if ($error): ?>
        <div class="error">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="success">
            <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <form method="POST">

        <input
            type="email"
            name="email"
            placeholder="Votre adresse email"
            required
            autocomplete="email"
        >

        <button type="submit">
            Envoyer le code
        </button>

    </form>

    <a href="login.php">
        ← Retour à la connexion
    </a>

</div>

</body>
</html>
