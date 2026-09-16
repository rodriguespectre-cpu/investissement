<?php
session_start();
require_once '../config/database.php';

// Vérifier si l'utilisateur vient de register.php
if (!isset($_SESSION['temp_user'])) {
    header("Location: register.php");
    exit;
}

$error = '';
$success = '';
$user_data = $_SESSION['temp_user'];

// Générer un token CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Liste des pays avec indicatifs téléphoniques
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
        'LY' => ['name' => 'Libye', 'code' => '+218', 'flag' => '🇱🇾'],
        'EG' => ['name' => 'Égypte', 'code' => '+20', 'flag' => '🇪🇬'],
    ];
}

$countries = getCountries();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérification CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Erreur de sécurité. Veuillez réessayer.";
    } else {
        $whatsapp = trim((string)($_POST['phone'] ?? ''));
        $country_code = trim((string)($_POST['country_code'] ?? ''));

        $errors = [];

        // Validation du numéro WhatsApp
        if (empty($whatsapp)) {
            $errors[] = "Le numéro WhatsApp est requis.";
        } elseif (!preg_match('/^[0-9]{9,12}$/', $whatsapp)) {
            $errors[] = "Le numéro WhatsApp doit contenir entre 9 et 12 chiffres.";
        }

        // Validation du pays
        if (empty($country_code) || !isset($countries[$country_code])) {
            $errors[] = "Veuillez sélectionner un pays valide.";
        }

        // Si tout est bon, on stocke en session et on redirige
       if (empty($errors)) {
    // Stockage unique et cohérent du numéro
    $_SESSION['temp_user']['phone'] = $whatsapp;
    $_SESSION['temp_user']['country_code'] = $country_code;

    // Supprimer l'ancienne clé pour éviter toute incohérence
    unset($_SESSION['temp_user']['whatsapp']);

    // Sauvegarder la session immédiatement
    session_write_close();

    header("Location: confirm_code_send.php");
    exit;
}

        if (!empty($errors)) {
            $error = implode("<br>", $errors);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Numéro de contact - InvestPro</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/register.css">
    <style>
        /* Styles spécifiques pour la page number */
        .form-group select {
            width: 100%;
            padding: 13px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 1rem;
            background: #f7fafc;
            color: #2d3748;
            transition: all 0.3s ease;
            appearance: none;
            -webkit-appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%234a5568' viewBox='0 0 16 16'%3E%3Cpath d='M8 11L3 6h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 16px center;
            cursor: pointer;
        }

        .form-group select:focus {
            border-color: #667eea;
            background: #ffffff;
            outline: none;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }

        .form-group select option {
            padding: 10px;
        }

        .whatsapp-group .input-wrapper {
            display: flex;
            gap: 0;
        }

        .whatsapp-group .country-prefix {
            display: flex;
            align-items: center;
            padding: 13px 12px 13px 16px;
            background: #f7fafc;
            border: 2px solid #e2e8f0;
            border-right: none;
            border-radius: 12px 0 0 12px;
            font-weight: 600;
            color: #2d3748;
            white-space: nowrap;
            min-width: 75px;
            justify-content: center;
            font-size: 0.95rem;
        }

        .whatsapp-group .input-wrapper input {
            border-radius: 0 12px 12px 0;
            border-left: none;
            height: 44px
  
        }

        .whatsapp-group .input-wrapper input:focus {
            border-color: #667eea;
            height: 44px
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

        .btn-group .btn-submit {
            flex: 2;
        }

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
            transition: all 0.3s ease;
        }

        .step-indicator .step.active {
            background: #667eea;
            color: white;
            transform: scale(1.1);
        }

        .step-indicator .step.completed {
            background: #48bb78;
            color: white;
        }

        .step-indicator .step-line {
            flex: 1;
            height: 2px;
            background: #e2e8f0;
            transition: all 0.3s ease;
        }

        .step-indicator .step-line.active {
            background: #667eea;
        }

        @media (max-width: 768px) {
            .btn-group {
                flex-direction: column;
            }
            
            .whatsapp-group .country-prefix {
                min-width: 60px;
                font-size: 0.85rem;
                padding: 12px 8px;
            }
        }
    </style>
</head>
<body>
    <div class="register-page">
        <div class="register-container">
            <!-- Colonne gauche : Formulaire -->
            <div class="register-form-wrapper">
                <div class="register-header">
                    <div class="step-indicator">
                        <span class="step completed">✓</span>
                        <span class="step-line active"></span>
                        <span class="step active">2</span>
                        <span class="step-line"></span>
                        <span class="step">3</span>
                    </div>
                    <h1>Créer un compte</h1>
                    <p>Contact et parrainage</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <span class="alert-icon">⚠️</span>
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" class="register-form">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                    <!-- Numéro WhatsApp -->
                    <div class="form-group whatsapp-group">
                        <label for="whatsapp">Numéro WhatsApp</label>
                        <div class="input-wrapper">
                            <span class="country-prefix" id="selectedPrefix">🇫🇷 +33</span>
                            <input 
                                type="tel" 
                                id="phone" 
                                name="phone" 
                                placeholder="Ex: 80XXXXXXXX" 
                                required
                            >
                        </div>
                        <div class="field-hint">Ex: 675090755 (sans l'indicatif)</div>
                    </div>

                    <!-- Pays -->
                    <div class="form-group">
                        <label for="country_code">Pays</label>
                        <select id="country_code" name="country_code" required>
                            <?php foreach ($countries as $key => $country): ?>
                                <option value="<?php echo $key; ?>" <?php echo ($key === 'FR') ? 'selected' : ''; ?>>
                                    <?php echo $country['flag']; ?> <?php echo $country['name']; ?> (<?php echo $country['code']; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Boutons -->
                    <div class="btn-group">
                        <a href="register.php" class="btn-back">
                            ← Précédent
                        </a>
                        <button type="submit" class="btn-submit" id="submitBtn">
                            Suivant →
                        </button>
                    </div>

                    <!-- Lien vers connexion -->
                    <div class="register-footer">
                        <p>Déjà un compte ? <a href="../login.php">Connexion</a></p>
                    </div>
                </form>
            </div>

            <!-- Colonne droite : Illustration -->
            <div class="register-illustration">
                <div class="illustration-content">
                    <div class="illustration-icon">📱</div>
                    <h2>Étape 2 sur 3</h2>
                    <p>Renseignez votre numéro WhatsApp pour recevoir les notifications.</p>
                    <div class="illustration-features">
                        <div class="feature-item">
                            <span>✓</span> Validation par WhatsApp
                        </div>
                        <div class="feature-item">
                            <span>✓</span> Notifications en temps réel
                        </div>
                        <div class="feature-item">
                            <span>✓</span> Sécurité renforcée
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Mise à jour du préfixe téléphonique selon le pays sélectionné
        document.addEventListener('DOMContentLoaded', function() {
            const countrySelect = document.getElementById('country_code');
            const prefixSpan = document.getElementById('selectedPrefix');
            
            // Liste des pays avec leurs informations
            const countries = {
                <?php foreach ($countries as $key => $country): ?>
                    '<?php echo $key; ?>': { flag: '<?php echo $country['flag']; ?>', code: '<?php echo $country['code']; ?>' },
                <?php endforeach; ?>
            };

            countrySelect.addEventListener('change', function() {
                const selected = this.value;
                if (selected && countries[selected]) {
                    prefixSpan.textContent = countries[selected].flag + ' ' + countries[selected].code;
                }
            });

            // Validation du numéro WhatsApp
            const whatsappInput = document.getElementById('phone');
            whatsappInput.addEventListener('input', function() {
                // Supprimer tout caractère non numérique
                this.value = this.value.replace(/[^0-9]/g, '');
                
                // Vérifier la longueur
                if (this.value.length > 0 && (this.value.length < 9 || this.value.length > 12)) {
                    this.style.borderColor = '#fc8181';
                    this.style.background = '#fff5f5';
                } else if (this.value.length >= 9 && this.value.length <= 12) {
                    this.style.borderColor = '#68d391';
                    this.style.background = '#f0fff4';
                } else {
                    this.style.borderColor = '';
                    this.style.background = '';
                }
            });
        });
    </script>
</body>
</html>
