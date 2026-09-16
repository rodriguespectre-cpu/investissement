<?php
session_start();
require_once '../config/database.php';

$error = '';
$success = '';
$form_data = [];

$referralCode = trim($_GET['ref'] ?? '');

if ($referralCode !== '') {
    $_SESSION['referral_code'] = $referralCode;
}
// Générer un token CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérification CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Erreur de sécurité. Veuillez réessayer.";
    } else {
        // Nettoyage des données
        $username = trim(htmlspecialchars($_POST['username']));
        $email    = trim(filter_var($_POST['email'], FILTER_SANITIZE_EMAIL));
        $password = $_POST['password'];
        $confirm  = $_POST['confirm_password'];
        $terms    = isset($_POST['terms']);

        $errors = [];

        // --- Validation du nom d'utilisateur ---
        if (empty($username)) {
            $errors[] = "Le nom d'utilisateur est requis.";
        } elseif (strlen($username) < 3) {
            $errors[] = "Le nom d'utilisateur doit contenir au moins 3 caractères.";
        } elseif (!preg_match('/^[a-zA-Z0-9_ ]+$/', $username)) {
            $errors[] = "Le nom d'utilisateur ne peut contenir que des lettres, chiffres, underscores et espaces.";
        }

        // --- Validation de l'email ---
        if (empty($email)) {
            $errors[] = "L'email est requis.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Veuillez entrer une adresse email valide.";
        }

        // --- Validation du mot de passe ---
        if (strlen($password) < 8) {
            $errors[] = "Le mot de passe doit faire au moins 8 caractères.";
        }
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = "Le mot de passe doit contenir au moins une majuscule.";
        }
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = "Le mot de passe doit contenir au moins une minuscule.";
        }
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = "Le mot de passe doit contenir au moins un chiffre.";
        }
        if (!preg_match('/[^a-zA-Z0-9]/', $password)) {
            $errors[] = "Le mot de passe doit contenir au moins un caractère spécial.";
        }

        if ($password !== $confirm) {
            $errors[] = "Les mots de passe ne correspondent pas.";
        }

        if (!$terms) {
            $errors[] = "Vous devez accepter les Conditions Générales d'Utilisation.";
        }

        // --- Vérifier l'unicité ---
        if (empty($errors)) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
            $stmt->execute([$email, $username]);
            if ($stmt->rowCount() > 0) {
                $errors[] = "Cet email ou ce nom d'utilisateur est déjà utilisé.";
            }
        }

        // --- Si tout est bon, on stocke en session et on redirige ---
        if (empty($errors)) {
            $_SESSION['temp_user'] = [
                'username' => $username,
                'nom_complet' => $username, // Pour compatibilité avec number.php
                'email' => $email,
                'password' => $password
            ];

            // Rediriger vers la page suivante
            header("Location: number.php");
            exit;
        }

        if (!empty($errors)) {
            $error = implode("<br>", $errors);
            $form_data = ['username' => $username, 'email' => $email];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription - InvestPro</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/register.css">
</head>
<body>
    <div class="register-page">
        <div class="register-container">
            <div class="register-form-wrapper">
                <div class="register-header">
                    <div class="step-indicator">
                        <span class="step active">1</span>
                        <span class="step-line"></span>
                        <span class="step">2</span>
                        <span class="step-line"></span>
                        <span class="step">3</span>
                    </div>
                    <h1>Créer un compte</h1>
                    <p>Vos informations personnelles</p>
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

                <form method="POST" action="" class="register-form">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                    <!-- Nom d'utilisateur -->
                    <div class="form-group">
                        <label for="username">Nom d'utilisateur</label>
                        <div class="input-wrapper">
                            <span class="input-icon">👤</span>
                            <input
                                type="text"
                                id="username"
                                name="username"
                                placeholder="Ex: JeanPaul"
                                value="<?php echo htmlspecialchars($form_data['username'] ?? ''); ?>"
                                required
                                autofocus
                            >
                        </div>
                        <div class="field-hint">Choisissez un pseudo unique (3 caractères min.)</div>
                    </div>

                    <!-- Email -->
                    <div class="form-group">
                        <label for="email">Email</label>
                        <div class="input-wrapper">
                            <span class="input-icon">✉️</span>
                            <input
                                type="email"
                                id="email"
                                name="email"
                                placeholder="Ex: jean@gmail.com"
                                value="<?php echo htmlspecialchars($form_data['email'] ?? ''); ?>"
                                required
                            >
                        </div>
                    </div>

                    <!-- Mot de passe -->
                    <div class="form-group">
                        <label for="password">Mot de passe</label>
                        <div class="input-wrapper">
                            <span class="input-icon">🔒</span>
                            <input
                                type="password"
                                id="password"
                                name="password"
                                placeholder="Mot de passe"
                                required
                                minlength="8"
                            >
                            <button type="button" class="toggle-password" onclick="togglePassword('password')">👁️</button>
                        </div>
                        <div class="password-strength">
                            <div class="strength-bar">
                                <div id="strengthProgress" style="width:0%;"></div>
                            </div>
                            <span id="strengthText">Force du mot de passe</span>
                        </div>
                    </div>

                    <!-- Confirmation -->
                    <div class="form-group">
                        <label for="confirm">Confirmer le mot de passe</label>
                        <div class="input-wrapper">
                            <span class="input-icon">🔐</span>
                            <input
                                type="password"
                                id="confirm"
                                name="confirm_password"
                                placeholder="Confirmer"
                                required
                            >
                        </div>
                        <div id="passwordMatch" style="font-size:0.9rem; min-height:20px;"></div>
                    </div>

                    <!-- CGU -->
                    <div class="form-group terms-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="terms" required>
                            <span class="checkmark"></span>
                            J'ai lu et accepte les<a href="politique.php" target="_blank">conditions</a>
                        </label>
                    </div>

                    <button type="submit" class="btn-submit" id="submitBtn">
                        <span>Suivant</span>
                        <span class="btn-arrow">→</span>
                    </button>

                    <div class="register-footer">
                        <p>Déjà un compte ? <a href="../login.php">Connexion</a></p>
                    </div>
                </form>
            </div>

            <!-- Illustration droite -->
            <div class="register-illustration">
                <div class="illustration-content">
                    <div class="illustration-icon">📈</div>
                    <h2>Investissez dans votre avenir</h2>
                    <p>Rejoignez InvestPro et commencez à générer des revenus passifs dès aujourd'hui.</p>
                    <div class="illustration-features">
                        <div class="feature-item"><span>✓</span> Sécurité maximale</div>
                        <div class="feature-item"><span>✓</span> Retraits rapides</div>
                        <div class="feature-item"><span>✓</span> Support 24/7</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Fonction pour afficher/masquer le mot de passe
        function togglePassword(id) {
            const input = document.getElementById(id);
            input.type = input.type === 'password' ? 'text' : 'password';
        }

        // Indicateur de force du mot de passe
        document.getElementById('password').addEventListener('input', function() {
            const p = this.value;
            let strength = 0;
            const rules = {
                length: p.length >= 8,
                uppercase: /[A-Z]/.test(p),
                lowercase: /[a-z]/.test(p),
                number: /[0-9]/.test(p),
                special: /[^a-zA-Z0-9]/.test(p)
            };
            for (let r in rules) if (rules[r]) strength++;
            const progress = document.getElementById('strengthProgress');
            const text = document.getElementById('strengthText');
            const percent = (strength / 5) * 100;
            progress.style.width = percent + '%';
            if (percent < 40) { progress.style.background = '#dc3545'; text.textContent = 'Faible'; }
            else if (percent < 60) { progress.style.background = '#ffc107'; text.textContent = 'Moyen'; }
            else if (percent < 80) { progress.style.background = '#4caf50'; text.textContent = 'Fort'; }
            else { progress.style.background = '#28a745'; text.textContent = 'Très fort'; }
        });

        // Vérification de la correspondance des mots de passe
        document.getElementById('confirm').addEventListener('input', function() {
            const match = document.getElementById('passwordMatch');
            const pwd = document.getElementById('password').value;
            if (this.value.length === 0) { match.textContent = ''; return; }
            if (pwd === this.value) {
                match.textContent = '✅ Les mots de passe correspondent';
                match.style.color = '#28a745';
            } else {
                match.textContent = '❌ Les mots de passe ne correspondent pas';
                match.style.color = '#dc3545';
            }
        });
    </script>
</body>
</html>
