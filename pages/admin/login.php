<?php

session_start();

require_once '../../config/database.php';

/*
|--------------------------------------------------------------------------
| Déjà connecté ?
|--------------------------------------------------------------------------
*/

if (
    isset($_SESSION['admin_id']) &&
    isset($_SESSION['admin_role']) &&
    in_array($_SESSION['admin_role'], ['admin', 'super_admin'], true)
) {
    header('Location: index.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| CSRF
|--------------------------------------------------------------------------
*/

if (empty($_SESSION['admin_login_csrf'])) {
    $_SESSION['admin_login_csrf'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['admin_login_csrf'];

$error = '';

/*
|--------------------------------------------------------------------------
| CONNEXION
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (
        empty($_POST['csrf_token']) ||
        !hash_equals(
            $_SESSION['admin_login_csrf'] ?? '',
            (string) $_POST['csrf_token']
        )
    ) {
        $error = 'Session expirée. Veuillez réessayer.';
    } else {

        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($email === '' || $password === '') {

            $error = 'Veuillez remplir tous les champs.';

        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

            $error = 'Adresse e-mail invalide.';

        } else {

            $stmt = $pdo->prepare("
                SELECT
                    id,
                    username,
                    email,
                    password,
                    role,
                    is_active
                FROM users
                WHERE email = ?
                LIMIT 1
            ");

            $stmt->execute([$email]);

            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            /*
            |--------------------------------------------------------------------------
            | Vérification admin
            |--------------------------------------------------------------------------
            */

            if (
                !$user ||
                !in_array(
                    $user['role'],
                    ['admin', 'super_admin'],
                    true
                ) ||
                (int) $user['is_active'] !== 1 ||
                !password_verify($password, $user['password'])
            ) {

                $error = 'Identifiants incorrects.';

            } else {

                /*
                |--------------------------------------------------------------------------
                | Nouvelle session
                |--------------------------------------------------------------------------
                */

                session_regenerate_id(true);

                $_SESSION['admin_id'] = (int) $user['id'];
                $_SESSION['admin_username'] = $user['username'];
                $_SESSION['admin_email'] = $user['email'];
                $_SESSION['admin_role'] = $user['role'];

                /*
                |--------------------------------------------------------------------------
                | Mise à jour dernière connexion
                |--------------------------------------------------------------------------
                */

                $update = $pdo->prepare("
                    UPDATE users
                    SET last_login = NOW()
                    WHERE id = ?
                ");

                $update->execute([
                    $user['id']
                ]);

                /*
                |--------------------------------------------------------------------------
                | Redirection
                |--------------------------------------------------------------------------
                */

                header('Location: index.php');
                exit;
            }
        }
    }
}

?>
<!DOCTYPE html>
<html lang="fr">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>InvestPro — Administration</title>

    <style>

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            min-height: 100vh;
            font-family:
                Inter,
                -apple-system,
                BlinkMacSystemFont,
                "Segoe UI",
                sans-serif;

            background:
                linear-gradient(
                    135deg,
                    #f5f7fb 0%,
                    #eef2f7 100%
                );

            display: flex;
            align-items: center;
            justify-content: center;

            padding: 20px;

            color: #172033;
        }

        .login-wrapper {
            width: 100%;
            max-width: 430px;
        }

        .brand {
            text-align: center;
            margin-bottom: 22px;
        }

        .brand-logo {
            width: 62px;
            height: 62px;

            margin: 0 auto 14px;

            border-radius: 18px;

            display: flex;
            align-items: center;
            justify-content: center;

            background: #111827;

            color: white;

            font-size: 25px;
            font-weight: 800;

            box-shadow:
                0 12px 30px rgba(17, 24, 39, .18);
        }

        .brand h1 {
            font-size: 25px;
            font-weight: 800;
            letter-spacing: -.5px;
        }

        .brand p {
            margin-top: 5px;
            color: #7b8495;
            font-size: 14px;
        }

        .card {
            background: rgba(255,255,255,.96);

            border: 1px solid #e7eaf0;

            border-radius: 22px;

            padding: 30px;

            box-shadow:
                0 20px 60px rgba(15,23,42,.08);
        }

        .card-title {
            margin-bottom: 22px;
        }

        .card-title h2 {
            font-size: 20px;
            font-weight: 750;
        }

        .card-title p {
            margin-top: 5px;
            font-size: 13px;
            color: #8a93a3;
        }

        .field {
            margin-bottom: 17px;
        }

        label {
            display: block;

            margin-bottom: 7px;

            font-size: 13px;
            font-weight: 650;

            color: #30394a;
        }

        input {
            width: 100%;

            height: 50px;

            border: 1px solid #dfe4ec;

            border-radius: 12px;

            padding: 0 15px;

            font-size: 15px;

            outline: none;

            background: #fbfcfe;

            transition: .2s;
        }

        input:focus {
            border-color: #111827;

            background: white;

            box-shadow:
                0 0 0 3px rgba(17,24,39,.06);
        }

        .password-box {
            position: relative;
        }

        .password-box input {
            padding-right: 55px;
        }

        .toggle-password {
            position: absolute;

            right: 12px;
            top: 50%;

            transform: translateY(-50%);

            border: 0;
            background: transparent;

            cursor: pointer;

            font-size: 17px;

            color: #7b8495;
        }

        .error {
            background: #fff1f2;

            border: 1px solid #fecdd3;

            color: #be123c;

            padding: 12px 13px;

            border-radius: 11px;

            font-size: 13px;

            margin-bottom: 18px;
        }

        button[type="submit"] {
            width: 100%;

            height: 51px;

            border: 0;

            border-radius: 12px;

            background: #111827;

            color: white;

            font-size: 15px;

            font-weight: 700;

            cursor: pointer;

            transition: .2s;
        }

        button[type="submit"]:hover {
            background: #1f2937;

            transform: translateY(-1px);
        }

        .security {
            margin-top: 19px;

            text-align: center;

            font-size: 12px;

            color: #929aaa;
        }

        .security span {
            display: inline-flex;

            align-items: center;

            gap: 5px;
        }

        .footer {
            text-align: center;

            margin-top: 20px;

            font-size: 12px;

            color: #9aa1af;
        }

        @media (max-width: 480px) {

            body {
                padding: 15px;
            }

            .card {
                padding: 23px 19px;
                border-radius: 18px;
            }

            .brand h1 {
                font-size: 22px;
            }
        }

    </style>

</head>

<body>

<div class="login-wrapper">

    <div class="brand">

        <div class="brand-logo">
            IP
        </div>

        <h1>InvestPro</h1>

        <p>Administration sécurisée</p>

    </div>

    <div class="card">

        <div class="card-title">

            <h2>Connexion administrateur</h2>

            <p>
                Accédez à votre espace de gestion.
            </p>

        </div>

        <?php if ($error !== ''): ?>

            <div class="error">
                <?= htmlspecialchars(
                    $error,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </div>

        <?php endif; ?>

        <form
            method="POST"
            autocomplete="off"
        >

            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars(
                    $csrfToken,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
            >

            <div class="field">

                <label for="email">
                    Adresse e-mail
                </label>

                <input
                    type="email"
                    id="email"
                    name="email"
                    placeholder="admin123aze@.com"
                    autocomplete="username"
                    required
                >

            </div>

            <div class="field">

                <label for="password">
                    Mot de passe
                </label>

                <div class="password-box">

                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="Votre mot de passe"
                        autocomplete="current-password"
                        required
                    >

                    <button
                        type="button"
                        class="toggle-password"
                        id="togglePassword"
                        aria-label="Afficher le mot de passe"
                    >
                        👁
                    </button>

                </div>

            </div>

            <button type="submit">
                Se connecter
            </button>

        </form>

        <div class="security">

            <span>
                🔒 Connexion protégée
            </span>

        </div>

    </div>

    <div class="footer">
        InvestPro Administration
    </div>

</div>

<script>

const passwordInput =
    document.getElementById('password');

const togglePassword =
    document.getElementById('togglePassword');

togglePassword.addEventListener(
    'click',
    function () {

        const visible =
            passwordInput.type === 'text';

        passwordInput.type =
            visible ? 'password' : 'text';

        this.textContent =
            visible ? '👁' : '🙈';
    }
);

</script>

</body>

</html>
