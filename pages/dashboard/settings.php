<?php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$userId = (int) $_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT id, username, email, phone, country_code, currency_code
    FROM users
    WHERE id = ?
    LIMIT 1
");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    session_destroy();
    header("Location: ../login.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Paramètres - InvestPro</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: #f5f7fb;
            color: #1f2937;
            transition: background .3s ease, color .3s ease;
        }

        .settings-page {
            max-width: 900px;
            margin: 0 auto;
            padding: 25px 18px 50px;
        }

        .settings-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 25px;
        }

        .settings-header h1 {
            margin: 0;
            font-size: 28px;
        }

        .back-btn {
            text-decoration: none;
            color: #667eea;
            font-weight: 600;
        }

        .settings-card {
            background: #ffffff;
            border-radius: 18px;
            padding: 24px;
            margin-bottom: 20px;
            box-shadow: 0 8px 30px rgba(0,0,0,.06);
            border: 1px solid #e5e7eb;
            transition: background .3s ease, border .3s ease;
        }

        .settings-card h2 {
            margin-top: 0;
            margin-bottom: 7px;
            font-size: 19px;
        }

        .settings-card p.description {
            margin-top: 0;
            color: #6b7280;
            font-size: 14px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            font-size: 14px;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 13px 15px;
            border-radius: 11px;
            border: 1px solid #d1d5db;
            background: #fff;
            color: #111827;
            outline: none;
            font-size: 15px;
            transition: .2s;
        }

        .form-group input:focus,
        .form-group select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102,126,234,.12);
        }

        .save-btn {
            width: 100%;
            border: none;
            padding: 14px;
            border-radius: 12px;
            background: #667eea;
            color: white;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: .2s;
        }

        .save-btn:hover {
            transform: translateY(-1px);
            opacity: .92;
        }

        /* =========================
           THÈME
        ========================= */

        .theme-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
        }

        .theme-info strong {
            display: block;
            margin-bottom: 5px;
        }

        .theme-info span {
            color: #6b7280;
            font-size: 13px;
        }

        .theme-switch {
            position: relative;
            width: 58px;
            height: 31px;
            flex-shrink: 0;
        }

        .theme-switch input {
            display: none;
        }

        .slider {
            position: absolute;
            inset: 0;
            background: #d1d5db;
            border-radius: 50px;
            cursor: pointer;
            transition: .3s;
        }

        .slider::before {
            content: "☀️";
            position: absolute;
            width: 25px;
            height: 25px;
            left: 3px;
            top: 3px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            transition: .3s;
        }

        .theme-switch input:checked + .slider {
            background: #667eea;
        }

        .theme-switch input:checked + .slider::before {
            transform: translateX(27px);
            content: "🌙";
        }

        /* =========================
           DARK MODE
        ========================= */

        body.dark {
            background: #0f172a;
            color: #f8fafc;
        }

        body.dark .settings-card {
            background: #172033;
            border-color: #273449;
            box-shadow: 0 10px 35px rgba(0,0,0,.25);
        }

        body.dark .settings-card p.description,
        body.dark .theme-info span {
            color: #94a3b8;
        }

        body.dark .form-group input,
        body.dark .form-group select {
            background: #0f172a;
            color: #f8fafc;
            border-color: #334155;
        }

        body.dark .form-group input::placeholder {
            color: #64748b;
        }

        body.dark .back-btn {
            color: #a5b4fc;
        }

        /* =========================
           MOBILE
        ========================= */

        @media (max-width: 600px) {

            .settings-page {
                padding: 18px 14px 40px;
            }

            .settings-header h1 {
                font-size: 23px;
            }

            .settings-card {
                padding: 19px;
                border-radius: 15px;
            }

            .theme-row {
                align-items: center;
            }
        }
    </style>

    <script>
        /*
         * Appliquer le thème AVANT l'affichage
         * pour éviter un flash blanc.
         */
        (function () {
            const savedTheme = localStorage.getItem('investpro_theme');

            if (savedTheme === 'dark') {
                document.documentElement.classList.add('dark-preload');
            }
        })();
    </script>

    <style>
        html.dark-preload body {
            background: #0f172a;
            color: #f8fafc;
        }
    </style>
</head>

<body>

<div class="settings-page">

    <div class="settings-header">
        <h1>⚙️ Paramètres</h1>

        <a href="index.php" class="back-btn">
            ← Retour
        </a>
    </div>


    <!-- =========================
         APPARENCE
    ========================== -->

    <div class="settings-card">

        <h2>🎨 Apparence</h2>

        <p class="description">
            Personnalisez l'apparence de votre espace InvestPro.
        </p>

        <div class="theme-row">

            <div class="theme-info">
                <strong>🌙 Mode sombre</strong>

                <span>
                    Activer ou désactiver le thème sombre.
                </span>
            </div>

            <label class="theme-switch">

                <input
                    type="checkbox"
                    id="darkModeToggle"
                >

                <span class="slider"></span>

            </label>

        </div>

    </div>


    <!-- =========================
         INFORMATIONS DU COMPTE
    ========================== -->

    <div class="settings-card">

        <h2>👤 Informations personnelles</h2>

        <p class="description">
            Modifiez vos informations personnelles.
        </p>

        <form method="POST" action="update_profile.php">

            <div class="form-group">

                <label for="username">
                    Nom d'utilisateur
                </label>

                <input
                    type="text"
                    id="username"
                    name="username"
                    value="<?= htmlspecialchars($user['username'] ?? '') ?>"
                    required
                >

            </div>


            <div class="form-group">

                <label for="email">
                    Adresse email
                </label>

                <input
                    type="email"
                    id="email"
                    name="email"
                    value="<?= htmlspecialchars($user['email'] ?? '') ?>"
                    required
                >

            </div>


            <div class="form-group">

                <label for="phone">
                    Numéro de téléphone
                </label>

                <input
                    type="tel"
                    id="phone"
                    name="phone"
                    value="<?= htmlspecialchars($user['phone'] ?? '') ?>"
                    placeholder="Ex : 687790232"
                >

            </div>


            <div class="form-group">

                <label for="country_code">
                    Pays
                </label>

                <select
                    id="country_code"
                    name="country_code"
                >

                    <option value="CM"
                        <?= ($user['country_code'] ?? '') === 'CM' ? 'selected' : '' ?>>
                        🇨🇲 Cameroun
                    </option>

                    <option value="FR"
                        <?= ($user['country_code'] ?? '') === 'FR' ? 'selected' : '' ?>>
                        🇫🇷 France
                    </option>

                    <option value="US"
                        <?= ($user['country_code'] ?? '') === 'US' ? 'selected' : '' ?>>
                        🇺🇸 États-Unis
                    </option>

                    <option value="CA"
                        <?= ($user['country_code'] ?? '') === 'CA' ? 'selected' : '' ?>>
                        🇨🇦 Canada
                    </option>

                    <option value="BE"
                        <?= ($user['country_code'] ?? '') === 'BE' ? 'selected' : '' ?>>
                        🇧🇪 Belgique
                    </option>

                    <option value="CH"
                        <?= ($user['country_code'] ?? '') === 'CH' ? 'selected' : '' ?>>
                        🇨🇭 Suisse
                    </option>

                </select>

            </div>


            <button
                type="submit"
                class="save-btn"
            >
                💾 Enregistrer les modifications
            </button>

        </form>

    </div>

</div>


<script>
document.addEventListener('DOMContentLoaded', function () {

    const toggle = document.getElementById('darkModeToggle');

    /*
     * Charger le thème sauvegardé
     */
    const savedTheme = localStorage.getItem('investpro_theme');

    if (savedTheme === 'dark') {
        document.body.classList.add('dark');
        toggle.checked = true;
    }


    /*
     * Changement du thème
     */
    toggle.addEventListener('change', function () {

        if (this.checked) {

            document.body.classList.add('dark');

            localStorage.setItem(
                'investpro_theme',
                'dark'
            );

        } else {

            document.body.classList.remove('dark');

            localStorage.setItem(
                'investpro_theme',
                'light'
            );
        }

    });

});
</script>

</body>
</html>
