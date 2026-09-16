<?php
session_start();

require_once 'config/database.php';

// Si l'utilisateur est déjà connecté,
// on l'envoie directement vers son tableau de bord.
if (isset($_SESSION['user_id'])) {
    header("Location: pages/dashboard/index.php");
    exit;
}

$is_logged_in = false;
$username = '';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Investissement Pro</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <header>
        <nav>
            <div class="logo">
                <span class="logo-icon">📈</span>
                <span class="logo-text">Invest<span>Pro</span></span>
            </div>

            <ul>
                <?php if ($is_logged_in): ?>
                    <li><a href="pages/dashboard/index.php">Tableau de bord</a></li>
                    <li><a href="pages/dashboard/index.php">Déconnexion</a></li>
               <?php else: ?>

   <li><a class="con" href="pages/login.php">Connexion</a></li>
<?php endif; ?>
            </ul>
        </nav>
    </header>

   <section id="home" class="hero">
    <div class="hero-content">
        <h1>Investissez dans <span class="highlight">votre avenir</span></h1>

        <p class="hero-subtitle">Gagnez <span class="percent">jusqu'à 70% par mois</span> avec nos plans d'investissement</p>

        <!-- Énumération animée -->
        <ul class="hero-features">
            <li><span>✓</span> Paiements sécurisés dans toutes les devises</li>
            <li><span>✓</span> Retraits rapides en 24h</li>
            <li><span>✓</span> Notre suivie professionnel</li>
        </ul>

        <a href="<?php echo $is_logged_in ? 'pages/dashboard/index.php' : 'pages/register.php'; ?>" class="cta-btn">
            <?php echo $is_logged_in ? 'Accéder à mon espace' : 'Commencer maintenant'; ?>
        </a>
    </div>

    <!-- Texte qui défile en fond -->
    <div class="marquee">
        <span>InvestPro • InvestPro • InvestPro • InvestPro • InvestPro • </span>
    </div>
</section>

   <section id="investpro" class="investpro">

    <!-- HERO -->
    <div class="investpro-hero">
        <div class="hero-overlay"></div>

        <div class="hero-content">
            <span class="hero-badge reveal-text">INVESTPRO</span>

            <h1 class="hero-title">
                Investir dans les projets qui
                <span>façonnent demain.</span>
            </h1>

            <p class="hero-description">
                InvestPro connecte les investisseurs à des opportunités
                dans les secteurs de l'énergie, des infrastructures,
                de la technologie durable et de la préservation des forêts.
            </p>

            <div class="hero-buttons">
                <a href="#opportunites" class="btn-primary">
                    Découvrir les opportunités
                </a>

                <a href="#vision" class="btn-secondary">
                    Notre vision
                </a>
            </div>
        </div>
    </div>


    <!-- PRESENTATION -->
    <section id="vision" class="investpro-intro">

        <div class="section-heading">
            <span>NOTRE VISION</span>
            <h2>Construire une économie plus durable</h2>
        </div>

        <div class="intro-grid">

            <div class="intro-image">
                <img
                    src="assets/images/investpro-energy.jpg"
                    alt="Projet d'énergie renouvelable"
                >
                <div class="image-label">
                    <strong>InvestPro</strong>
                    <span>Innovation • Durabilité • Croissance</span>
                </div>
            </div>

            <div class="intro-content">

                <p class="lead">
                    InvestPro a pour ambition de rapprocher le capital
                    des projets capables de transformer durablement
                    notre économie.
                </p>

                <p>
                    Nous mettons en avant différents secteurs stratégiques,
                    notamment les énergies renouvelables, la gestion
                    responsable des ressources forestières, les infrastructures
                    modernes et les solutions technologiques.
                </p>

                <p>
                    Notre approche repose sur une vision à long terme :
                    favoriser des projets créateurs de valeur tout en
                    participant au développement d'une économie plus
                    responsable.
                </p>

                <div class="intro-stats">
                    <div>
                        <strong>01</strong>
                        <span>Énergie verte</span>
                    </div>

                    <div>
                        <strong>02</strong>
                        <span>Forêts & ressources</span>
                    </div>

                    <div>
                        <strong>03</strong>
                        <span>Infrastructures</span>
                    </div>
                </div>

            </div>

        </div>
    </section>


    <!-- OPPORTUNITES -->
    <section id="opportunites" class="opportunities">

        <div class="section-heading">
            <span>SECTEURS</span>
            <h2>Des opportunités tournées vers l'avenir</h2>
            <p>
                Explorez les différents domaines dans lesquels InvestPro
                souhaite orienter son activité.
            </p>
        </div>


        <!-- CARROUSEL -->
        <div class="investment-carousel">

            <button class="carousel-btn prev" aria-label="Précédent">
                &#10094;
            </button>

            <div class="carousel-track">

                <!-- ENERGY -->
                <article class="investment-slide active">

                    <img
                        src="assets/images/solar-energy.jpg"
                        alt="Énergie solaire"
                    >

                    <div class="slide-overlay"></div>

                    <div class="slide-content">

                        <span class="category">
                            ÉNERGIE
                        </span>

                        <h3>
                            Énergies renouvelables
                        </h3>

                        <p>
                            Accompagner le développement de solutions
                            énergétiques modernes telles que le solaire,
                            l'éolien et les infrastructures énergétiques
                            propres.
                        </p>

                        <a href="#" class="slide-link">
                            Explorer le secteur →
                        </a>

                    </div>
                </article>


                <!-- FOREST -->
                <article class="investment-slide">

                    <img
                        src="assets/images/forest-investment.jpg"
                        alt="Forêt et développement durable"
                    >

                    <div class="slide-overlay"></div>

                    <div class="slide-content">

                        <span class="category">
                            ENVIRONNEMENT
                        </span>

                        <h3>
                            Forêts & ressources naturelles
                        </h3>

                        <p>
                            Soutenir des initiatives liées à la préservation
                            des espaces forestiers, à la gestion responsable
                            des ressources et à la restauration des écosystèmes.
                        </p>

                        <a href="#" class="slide-link">
                            Explorer le secteur →
                        </a>

                    </div>
                </article>


                <!-- INFRASTRUCTURE -->
                <article class="investment-slide">

                    <img
                        src="assets/images/infrastructure-investment.jpg"
                        alt="Infrastructure moderne"
                    >

                    <div class="slide-overlay"></div>

                    <div class="slide-content">

                        <span class="category">
                            INFRASTRUCTURES
                        </span>

                        <h3>
                            Infrastructures & développement
                        </h3>

                        <p>
                            Participer au développement de projets destinés
                            à améliorer les infrastructures et soutenir
                            la croissance économique des territoires.
                        </p>

                        <a href="#" class="slide-link">
                            Explorer le secteur →
                        </a>

                    </div>
                </article>


                <!-- TECHNOLOGY -->
                <article class="investment-slide">

                    <img
                        src="assets/images/technology-investment.jpg"
                        alt="Technologie et innovation"
                    >

                    <div class="slide-overlay"></div>

                    <div class="slide-content">

                        <span class="category">
                            TECHNOLOGIE
                        </span>

                        <h3>
                            Innovation & technologies
                        </h3>

                        <p>
                            Identifier les solutions technologiques capables
                            de répondre aux nouveaux défis économiques,
                            industriels et environnementaux.
                        </p>

                        <a href="#" class="slide-link">
                            Explorer le secteur →
                        </a>

                    </div>
                </article>

            </div>

            <button class="carousel-btn next" aria-label="Suivant">
                &#10095;
            </button>

        </div>

        <div class="carousel-dots">

            <button class="dot active"></button>
            <button class="dot"></button>
            <button class="dot"></button>
            <button class="dot"></button>

        </div>

    </section>


    <!-- POURQUOI INVESTPRO -->
    <section class="why-investpro">

        <div class="section-heading">
            <span>POURQUOI INVESTPRO</span>

            <h2>
                Une vision basée sur la création de valeur
            </h2>
        </div>


        <div class="features-grid">

            <div class="feature-card">
                <div class="feature-number">01</div>

                <h3>
                    Diversification
                </h3>

                <p>
                    Explorer plusieurs secteurs stratégiques permet
                    d'adopter une approche diversifiée des opportunités
                    d'investissement.
                </p>
            </div>


            <div class="feature-card">
                <div class="feature-number">02</div>

                <h3>
                    Vision long terme
                </h3>

                <p>
                    Nous privilégions les projets capables de s'inscrire
                    dans une dynamique durable et de répondre aux besoins
                    des prochaines générations.
                </p>
            </div>


            <div class="feature-card">
                <div class="feature-number">03</div>

                <h3>
                    Innovation
                </h3>

                <p>
                    La technologie et l'innovation occupent une place
                    importante dans notre réflexion sur les opportunités
                    économiques de demain.
                </p>
            </div>


            <div class="feature-card">
                <div class="feature-number">04</div>

                <h3>
                    Responsabilité
                </h3>

                <p>
                    Nous souhaitons favoriser une approche qui tient compte
                    des enjeux économiques, sociaux et environnementaux.
                </p>
            </div>

        </div>

    </section>


    <!-- MESSAGE FINAL -->
    <section class="investpro-cta">

        <div class="cta-content">

            <span>
                INVESTPRO
            </span>

            <h2>
                Le futur se construit aujourd'hui.
            </h2>

            <p>
                Découvrez notre vision, nos secteurs d'activité et les
                opportunités proposées par notre plateforme.
            </p>

            <a href="#opportunites" class="btn-primary">
                Découvrir InvestPro
            </a>

        </div>

    </section>

</section>

    <footer class="footer">
    <div class="footer-container">

        <!-- Colonne 1 : Logo + Description -->
        <div class="footer-col">
            <div class="footer-logo">
                <span>📈Invest<span>Pro</span></span>
            </div>
            <p class="footer-desc">
                InvestPro votre nouvelle plateform d'investissement,
 cumulé des revenus quotidien pour vous issez  au sommet des gains
            </p>

        </div>

        <!-- Colonne 2 : Liens rapides -->
        <div class="footer-col">
            <h4>Plateforme</h4>
            <ul>
                <li><a href="#">Accueil</a></li>
                <li><a href="#">Formations</a></li>
                <li><a href="#">Services de Paiement</a></li>
                <li><a href="#">Espace Membre</a></li>
                <li><a href="#">Blog</a></li>
            </ul>
        </div>

        <!-- Colonne 3 : Contact -->
        <div class="footer-col">
            <h4>Contact</h4>
            <ul class="footer-contact">
                <li>📍 CANADA, FRANCE, CAMEROUN, USA</li>
                <li>📞 <a href="tel:+2376809876325">+237 809876325</a></li>
                <li>✉️ <a href="mailto:contact@investpro.com">contact@investpro.com</a></li>
                <li>💬 <a href="https://wa.me/237809876325" target="_blank">WhatsApp : +237809876325</a></li>
            </ul>

            <div class="footer-social">
                <a href="#">Facebook</a>
                <a href="#">Telegram</a>
                <a href="#">LinkedIn</a>
            </div>
        </div>

    </div>

    <!-- Bas de footer -->
    <div class="footer-bottom">
        <p>© 2026 InvestPro. Tous droits réservés. | <a href="#">Politique de Confidentialité</a> | <a href="#">CGU</a></p>
    </div>
</footer>
    <script src="assets/js/script.js"></script>
</body>
</html>
