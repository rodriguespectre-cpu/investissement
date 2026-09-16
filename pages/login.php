<?php
session_start();
require_once '../config/database.php';

$error = '';
$email = '';
$success = '';

// Si déjà connecté, rediriger vers le dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard/index.php");
    exit;
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
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $remember = isset($_POST['remember']);

        if (empty($email) || empty($password)) {
            $error = "Veuillez remplir tous les champs.";
        } else {
            // Rechercher l'utilisateur par email ou username
            $stmt = $pdo->prepare("
                SELECT id, username, email, password, role, is_active, balance 
                FROM users 
                WHERE email = ? OR username = ?
            ");
            $stmt->execute([$email, $email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                // Vérifier si le compte est actif
                if (!$user['is_active']) {
                    $error = "Votre compte est désactivé. Veuillez contacter le support.";
                } else {
                    // Connexion réussie
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['user_role'] = $user['role'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['balance'] = $user['balance'];

                    // Mettre à jour la dernière connexion
                    $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                    $stmt->execute([$user['id']]);

                    // Ajouter une notification
                    $stmt = $pdo->prepare("
                        INSERT INTO notifications (user_id, type, title, message)
                        VALUES (?, 'success', 'Connexion réussie', 'Vous êtes connecté à votre compte.')
                    ");
                    $stmt->execute([$user['id']]);

                    // Redirection selon le rôle
                    if ($user['role'] === 'admin') {
                        header("Location: admin/dashboard.php");
                    } else {
                        header("Location: dashboard/index.php");
                    }
                    exit;
                }
            } else {
                $error = "Email ou mot de passe incorrect.";
                // Journaliser la tentative échouée (optionnel)
                error_log("Tentative de connexion échouée pour: " . $email);
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
    <title>Connexion - InvestPro</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/login.css">
</head>
<body>
    <div class="login-page">
        <div class="login-container">
            <!-- Colonne gauche : Formulaire -->
            <div class="login-form-wrapper">
                <div class="login-header">
                    <a href="dashboard/index.php" class="back-home">← Accedez au dashboard</a>
                    <h1>Connexion</h1>
                    <p>Connectez-vous à votre compte</p>
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

                <form method="POST" action="" class="login-form">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                    <!-- Email ou nom d'utilisateur -->
                    <div class="form-group">
                        <label for="email">Email ou nom d'utilisateur</label>
                        <div class="input-wrapper">
                            <span class="input-icon">✉️</span>
                            <input 
                                type="text" 
                                id="email" 
                                name="email" 
                                placeholder="Ex: jean@gmail.com" 
                                value="<?php echo htmlspecialchars($email); ?>"
                                required
                                autofocus
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
                            >
                            <button type="button" class="toggle-password" onclick="togglePassword('password')">
                                👁️
                            </button>
                        </div>
                    </div>

                    <!-- Options supplémentaires -->
                    <div class="form-options">
                        <label class="checkbox-label">
                            <input type="checkbox" name="remember" id="remember">
                            <span class="checkmark"></span>
                            Se souvenir de moi
                        </label>
                        <a href="forgot-password.php" class="forgot-link">Mot de passe oublié ?</a>
                    </div>

                    <!-- Bouton de connexion -->
                    <button type="submit" class="btn-submit" id="submitBtn">
                        <span>Se connecter</span>
                        <span class="btn-arrow">→</span>
                    </button>

                    <!-- Lien vers inscription -->
                    <div class="login-footer">
                        <p>Pas encore de compte ? <a href="register.php">Créer un compte</a></p>
                    </div>
                </form>
            </div>

            <!-- Colonne droite : Illustration -->
            <div class="login-illustration">
                <div class="illustration-content">
                    <div class="illustration-icon">🔑</div>
                    <h2>Content de vous revoir</h2>
                    <p>Connectez-vous pour gérer vos investissements et suivre vos gains en temps réel.</p>
                    <div class="illustration-features">
                        <div class="feature-item">
                            <span>✓</span> Sécurité maximale
                        </div>
                        <div class="feature-item">
                            <span>✓</span> Retraits rapides
                        </div>
                        <div class="feature-item">
                            <span>✓</span> Support 24/7
                        </div>
                    </div>
                    <div class="illustration-stats">
                        <div class="stat-item">
                            <strong>2 500+</strong>
                            <span>Investisseurs</span>
                        </div>
                        <div class="stat-item">
                            <strong>€15M+</strong>
                            <span>Investis</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Fonction pour afficher/masquer le mot de passe
        function togglePassword(id) {
            const input = document.getElementById(id);
            const button = input.parentElement.querySelector('.toggle-password');
            if (input.type === 'password') {
                input.type = 'text';
                button.textContent = '👁️‍🗨️';
            } else {
                input.type = 'password';
                button.textContent = '👁️';
            }
        }

        // Validation en temps réel de l'email
        document.addEventListener('DOMContentLoaded', function() {
            const emailInput = document.getElementById('email');
            emailInput.addEventListener('input', function() {
                // Pour l'email, on vérifie juste si c'est un format valide
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                const wrapper = this.closest('.input-wrapper');
                const icon = wrapper.querySelector('.input-icon');
                
                if (this.value.length > 0) {
                    if (emailRegex.test(this.value)) {
                        this.style.borderColor = '#68d391';
                        this.style.background = '#f0fff4';
                        icon.style.color = '#68d391';
                    } else {
                        this.style.borderColor = '#fc8181';
                        this.style.background = '#fff5f5';
                        icon.style.color = '#fc8181';
                    }
                } else {
                    this.style.borderColor = '';
                    this.style.background = '';
                    icon.style.color = '#a0aec0';
                }
            });

            // Effet de focus sur les champs
            document.querySelectorAll('.input-wrapper input').forEach(input => {
                input.addEventListener('focus', function() {
                    this.parentElement.style.transform = 'scale(1.01)';
                });
                input.addEventListener('blur', function() {
                    this.parentElement.style.transform = 'scale(1)';
                });
            });
        });
    </script>
</body>
</html>



