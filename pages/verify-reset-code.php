<?php

session_start();

$error = '';

if (
    empty($_SESSION['password_reset']) ||
    empty($_SESSION['password_reset']['user_id'])
) {
    header("Location: forgot-password.php");
    exit;
}

$reset = &$_SESSION['password_reset'];

if (time() > $reset['expires_at']) {
    unset($_SESSION['password_reset']);

    header("Location: forgot-password.php?expired=1");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $code = trim($_POST['code'] ?? '');

    if (!preg_match('/^\d{6}$/', $code)) {

        $error = "Le code doit contenir 6 chiffres.";

    } elseif ($reset['attempts'] >= 5) {

        unset($_SESSION['password_reset']);

        $error =
            "Trop de tentatives. "
            . "Veuillez recommencer la procédure.";

    } else {

        $reset['attempts']++;

        if (password_verify($code, $reset['code_hash'])) {

            $_SESSION['password_reset']['verified'] = true;

            header("Location: reset-password.php");
            exit;

        } else {

            $remaining = 5 - $reset['attempts'];

            $error =
                "Code incorrect. "
                . "Tentatives restantes : {$remaining}.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">

<title>Vérification - InvestPro</title>

<style>
*{box-sizing:border-box}

body{
margin:0;
min-height:100vh;
display:flex;
align-items:center;
justify-content:center;
font-family:Arial,sans-serif;
background:#f5f7fb;
}

.card{
width:100%;
max-width:430px;
margin:20px;
padding:35px;
background:#fff;
border-radius:20px;
box-shadow:0 15px 45px rgba(0,0,0,.08);
}

input{
width:100%;
padding:16px;
margin:15px 0;
text-align:center;
font-size:25px;
letter-spacing:8px;
border:1px solid #dbe1ea;
border-radius:10px;
}

button{
width:100%;
padding:14px;
border:0;
border-radius:10px;
background:#2563eb;
color:#fff;
font-size:16px;
}

.error{
padding:12px;
background:#fee2e2;
color:#991b1b;
border-radius:10px;
}
</style>
</head>

<body>

<div class="card">

<h1>Vérification</h1>

<p>
Entrez le code à 6 chiffres reçu par email.
</p>

<?php if ($error): ?>
<div class="error">
<?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
</div>
<?php endif; ?>

<form method="POST">

<input
type="text"
name="code"
maxlength="6"
inputmode="numeric"
pattern="[0-9]{6}"
autocomplete="one-time-code"
required
>

<button type="submit">
Vérifier le code
</button>

</form>

</div>

</body>
</html>
