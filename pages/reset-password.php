<?php

session_start();

require_once __DIR__ . '/../config/database.php';

if (
    empty($_SESSION['password_reset']) ||
    empty($_SESSION['password_reset']['verified']) ||
    empty($_SESSION['password_reset']['user_id'])
) {
    header("Location: forgot-password.php");
    exit;
}

$reset = $_SESSION['password_reset'];

if (time() > $reset['expires_at']) {

    unset($_SESSION['password_reset']);

    header("Location: forgot-password.php?expired=1");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $password = $_POST['password'] ?? '';
    $passwordConfirmation = $_POST['password_confirmation'] ?? '';

    if (strlen($password) < 8) {

        $error =
            "Le mot de passe doit contenir au moins 8 caractères.";

    } elseif ($password !== $passwordConfirmation) {

        $error =
            "Les deux mots de passe ne correspondent pas.";

    } else {

        $hash = password_hash(
            $password,
            PASSWORD_DEFAULT
        );

        $stmt = $pdo->prepare("
            UPDATE users
            SET password = ?,
                updated_at = NOW()
            WHERE id = ?
            LIMIT 1
        ");

        $stmt->execute([
            $hash,
            (int)$reset['user_id']
        ]);

        if ($stmt->rowCount() !== 1) {

            $error =
                "Impossible de modifier le mot de passe.";

        } else {

            /*
             * Le code de réinitialisation ne doit plus être
             * réutilisable.
             */
            unset($_SESSION['password_reset']);

            header("Location: login.php?reset=success");
            exit;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">

<title>Nouveau mot de passe - InvestPro</title>

<style>

*{
box-sizing:border-box;
}

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
padding:14px;
margin:8px 0 15px;
border:1px solid #dbe1ea;
border-radius:10px;
font-size:16px;
}

button{
width:100%;
padding:14px;
border:0;
border-radius:10px;
background:#2563eb;
color:#fff;
font-size:16px;
cursor:pointer;
}

.error{
padding:12px;
margin-bottom:15px;
background:#fee2e2;
color:#991b1b;
border-radius:10px;
}

label{
font-weight:bold;
}

</style>

</head>

<body>

<div class="card">

<h1>Nouveau mot de passe</h1>

<p>
Choisissez votre nouveau mot de passe.
</p>

<?php if ($error): ?>

<div class="error">
<?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
</div>

<?php endif; ?>

<form method="POST">

<label>
Nouveau mot de passe
</label>

<input
type="password"
name="password"
minlength="8"
autocomplete="new-password"
required
>

<label>
Confirmer le mot de passe
</label>

<input
type="password"
name="password_confirmation"
minlength="8"
autocomplete="new-password"
required
>

<button type="submit">
Changer le mot de passe
</button>

</form>

</div>

</body>

</html>
