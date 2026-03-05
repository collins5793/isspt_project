

<style>
    /* ===== VARIABLES FOOTER ===== */
:root {
  --primary-red: rgb(186, 40, 30);
  --primary-dark: rgb(8, 0, 32);
  --primary-red-hover: rgba(186, 40, 30, 0.9);
  --primary-dark-hover: rgba(8, 0, 32, 0.95);
  --white: #ffffff;
  --light-gray: #f8f9fa;
  --text-light: #e0e0e0;
  --text-lighter: #b0b0b0;
  --border-dark: rgba(255, 255, 255, 0.1);
  --shadow: 0 -4px 30px rgba(0, 0, 0, 0.1);
  --shadow-hover: 0 -8px 40px rgba(0, 0, 0, 0.2);
  --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  --border-radius: 8px;
}

/* ===== FOOTER PRINCIPAL ===== */
.main-footer {
  background: linear-gradient(135deg, var(--primary-dark) 0%, #0a051f 100%);
  color: var(--text-light);
  margin-top: auto;
  position: relative;
  overflow: hidden;
}

.main-footer::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 1px;
  background: linear-gradient(90deg, 
    transparent 0%, 
    var(--primary-red) 50%, 
    transparent 100%);
}

/* ===== CONTAINER PRINCIPAL ===== */
.footer-container {
  max-width: 1200px;
  margin: 0 auto;
  padding: 4rem 2rem 2rem;
  display: grid;
  grid-template-columns: 2fr 1fr 1fr 1fr;
  gap: 3rem;
  position: relative;
}

/* Animation d'entrée */
.footer-container > * {
  opacity: 0;
  transform: translateY(30px);
  animation: fadeInUp 0.6s ease-out forwards;
}

.footer-container > *:nth-child(1) { animation-delay: 0.1s; }
.footer-container > *:nth-child(2) { animation-delay: 0.2s; }
.footer-container > *:nth-child(3) { animation-delay: 0.3s; }
.footer-container > *:nth-child(4) { animation-delay: 0.4s; }

@keyframes fadeInUp {
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

/* ===== SECTIONS DU FOOTER ===== */
.footer-section h3 {
  color: var(--white);
  font-size: 1.3rem;
  font-weight: 700;
  margin-bottom: 1.5rem;
  position: relative;
  padding-bottom: 0.5rem;
}

.footer-section h3::after {
  content: '';
  position: absolute;
  bottom: 0;
  left: 0;
  width: 40px;
  height: 2px;
  background: var(--primary-red);
  transition: var(--transition);
}

.footer-section:hover h3::after {
  width: 60px;
}

/* ===== SECTION ABOUT ===== */
.footer-section.about {
  padding-right: 2rem;
}

.footer-logo {
  display: inline-block;
  margin-bottom: 1rem;
  transition: var(--transition);
}

.logo-small {
  width: 60px;
  height: 60px;
  object-fit: contain;
  filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.3));
  transition: var(--transition);
}

.footer-logo:hover .logo-small {
  transform: scale(1.1) rotate(5deg);
  filter: drop-shadow(0 4px 8px rgba(186, 40, 30, 0.4));
}

.footer-section.about p {
  line-height: 1.7;
  color: var(--text-lighter);
  margin-bottom: 0;
  font-size: 0.95rem;
}

/* ===== SECTION LIENS RAPIDES ===== */
.footer-section.links ul {
  list-style: none;
  padding: 0;
  margin: 0;
}

.footer-section.links li {
  margin-bottom: 0.7rem;
  position: relative;
}

.footer-section.links a {
  color: var(--text-lighter);
  text-decoration: none;
  transition: var(--transition);
  padding: 0.3rem 0;
  display: block;
  position: relative;
  padding-left: 1rem;
}

.footer-section.links a::before {
  content: '›';
  position: absolute;
  left: 0;
  color: var(--primary-red);
  transition: var(--transition);
  transform: translateX(0);
}

.footer-section.links a:hover {
  color: var(--white);
  transform: translateX(5px);
}

.footer-section.links a:hover::before {
  transform: translateX(3px);
  color: var(--white);
}

/* ===== SECTION CONTACT ===== */
.footer-section.contact p {
  display: flex;
  align-items: center;
  margin-bottom: 1rem;
  color: var(--text-lighter);
  line-height: 1.5;
}

.footer-section.contact i {
  color: var(--primary-red);
  margin-right: 0.75rem;
  width: 16px;
  text-align: center;
  transition: var(--transition);
}

.footer-section.contact p:hover i {
  transform: scale(1.2);
  color: var(--white);
}

.footer-section.contact a {
  color: var(--text-lighter);
  text-decoration: none;
  transition: var(--transition);
  display: inline-flex;
  align-items: center;
}

.footer-section.contact a:hover {
  color: var(--primary-red);
}

.footer-section.contact a::after {
  content: '↗';
  margin-left: 0.3rem;
  font-size: 0.8rem;
  transition: var(--transition);
}

.footer-section.contact a:hover::after {
  transform: translate(2px, -2px);
}

/* ===== SECTION RÉSEAUX SOCIAUX ===== */
.footer-section.social .social-icons {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  gap: 1rem;
  flex-wrap: wrap;
}

.social-icons li {
  margin: 0;
}

.social-icons a {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 45px;
  height: 45px;
  background: rgba(255, 255, 255, 0.1);
  border-radius: 50%;
  color: var(--text-light);
  text-decoration: none;
  transition: var(--transition);
  position: relative;
  overflow: hidden;
  backdrop-filter: blur(10px);
}

.social-icons a::before {
  content: '';
  position: absolute;
  top: 0;
  left: -100%;
  width: 100%;
  height: 100%;
  background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
  transition: var(--transition);
}

.social-icons a:hover::before {
  left: 100%;
}

.social-icons a:hover {
  background: var(--primary-red);
  color: var(--white);
  transform: translateY(-3px) scale(1.1);
  box-shadow: 0 8px 20px rgba(186, 40, 30, 0.3);
}

.social-icons a:active {
  transform: translateY(-1px) scale(1.05);
}

/* Couleurs spécifiques pour chaque réseau */
.social-icons a:hover .fa-facebook-f { color: #1877f2; }
.social-icons a:hover .fa-twitter { color: #1da1f2; }
.social-icons a:hover .fa-linkedin-in { color: #0077b5; }
.social-icons a:hover .fa-instagram { 
  background: linear-gradient(45deg, #f09433, #e6683c, #dc2743, #cc2366, #bc1888);
  color: white;
}

/* ===== FOOTER BOTTOM ===== */
.footer-bottom {
  border-top: 1px solid var(--border-dark);
  padding: 1.5rem 2rem;
  text-align: center;
  background: rgba(0, 0, 0, 0.3);
  position: relative;
}

.footer-bottom::before {
  content: '';
  position: absolute;
  top: -1px;
  left: 50%;
  transform: translateX(-50%);
  width: 200px;
  height: 1px;
  background: linear-gradient(90deg, transparent, var(--primary-red), transparent);
}

.footer-bottom p {
  margin: 0;
  color: var(--text-lighter);
  font-size: 0.9rem;
}

.footer-bottom a {
  color: var(--text-light);
  text-decoration: none;
  transition: var(--transition);
  position: relative;
  padding: 0.2rem 0.5rem;
  border-radius: 3px;
}

.footer-bottom a::after {
  content: '';
  position: absolute;
  bottom: -2px;
  left: 50%;
  width: 0;
  height: 1px;
  background: var(--primary-red);
  transition: var(--transition);
  transform: translateX(-50%);
}

.footer-bottom a:hover {
  color: var(--white);
}

.footer-bottom a:hover::after {
  width: 80%;
}

/* ===== EFFET DE SURVOL GLOBAL ===== */
.footer-section {
  transition: var(--transition);
  padding: 1rem;
  border-radius: var(--border-radius);
}

.footer-section:hover {
  background: rgba(255, 255, 255, 0.03);
  transform: translateY(-5px);
}

/* ===== RESPONSIVE ===== */
@media (max-width: 1024px) {
  .footer-container {
    grid-template-columns: 1fr 1fr;
    gap: 2rem;
    padding: 3rem 2rem 2rem;
  }
  
  .footer-section.about {
    grid-column: 1 / -1;
    text-align: center;
    padding-right: 0;
  }
  
  .footer-section.about h3::after {
    left: 50%;
    transform: translateX(-50%);
  }
}

@media (max-width: 768px) {
  .footer-container {
    grid-template-columns: 1fr;
    gap: 2rem;
    padding: 2rem 1.5rem 1.5rem;
    text-align: center;
  }
  
  .footer-section h3::after {
    left: 50%;
    transform: translateX(-50%);
  }
  
  .social-icons {
    justify-content: center;
  }
  
  .footer-section.links a {
    padding-left: 0;
  }
  
  .footer-section.links a::before {
    display: none;
  }
  
  .footer-section.contact p {
    justify-content: center;
  }
}

@media (max-width: 480px) {
  .footer-container {
    padding: 1.5rem 1rem 1rem;
  }
  
  .footer-bottom {
    padding: 1rem;
  }
  
  .footer-bottom p {
    font-size: 0.8rem;
    line-height: 1.5;
  }
  
  .social-icons a {
    width: 40px;
    height: 40px;
  }
}

/* ===== ANIMATION DE SCROLL ===== */
.main-footer {
  transition: opacity 0.6s ease, transform 0.6s ease;
}

.main-footer.visible {
  opacity: 1;
  transform: translateY(0);
}

</style>

<footer class="main-footer">

  <div class="footer-container">

    <!-- Section 1 : À propos de l'université -->
    <div class="footer-section about">
      <a href="#" class="footer-logo">
        <img src="<?= $base_url ?>assets/images/logo.png" alt="Logo Université" class="logo-small">
      </a>
      <h3>À propos</h3>
      <p>
        Université ISSPT – Portail Étudiant. Centralisez et consultez les résultats académiques, activités et anciennes épreuves de manière simple et sécurisée.
      </p>
    </div>

    <!-- Section 2 : Liens rapides -->
    <div class="footer-section links">
      <h3>Liens rapides</h3>
      <ul>
        <li><a href="<?= $base_url ?>index.php">Accueil</a></li>
        <li><a href="<?= $base_url ?>activites.php">Activités & Événements</a></li>
        <li><a href="<?= $base_url ?>epreuves.php">Épreuves</a></li>
        <li><a href="<?= $base_url ?>contact.php">Contact</a></li>
        <li><a href="<?= $base_url ?>faq.php">FAQ</a></li>
      </ul>
    </div>

    <!-- Section 3 : Contact / Infos pratiques -->
    <div class="footer-section contact">
      <h3>Contact</h3>
      <p><i class="fas fa-map-marker-alt"></i> Abomey-Calavi, Bénin</p>
      <p><i class="fas fa-envelope"></i> contact@universitexyz.edu</p>
      <p><i class="fas fa-phone"></i> +229 97 00 00 00</p>
      <p><a href="https://www.google.com/maps/place/Institut+Sup%C3%A9rieur+Saint+Paul+de+Tarse+(ISSPT)/@6.4244138,2.3362067,17z/data=!4m14!1m7!3m6!1s0x1024a9fc75b45259:0xdca207aa6cb158f6!2sInstitut+Sup%C3%A9rieur+Saint+Paul+de+Tarse+(ISSPT)!8m2!3d6.4244085!4d2.3387816!16s%2Fg%2F11fp8zvvl4!3m5!1s0x1024a9fc75b45259:0xdca207aa6cb158f6!8m2!3d6.4244085!4d2.3387816!16s%2Fg%2F11fp8zvvl4?entry=ttu&g_ep=EgoyMDI2MDIwMy4wIKXMDSoASAFQAw%3D%3D" target="_blank">Voir sur la carte</a></p>
    </div>

    <!-- Section 4 : Réseaux sociaux -->
    <div class="footer-section social">
      <h3>Suivez-nous</h3>
      <ul class="social-icons">
        <li><a href="#" target="_blank"><i class="fab fa-facebook-f"></i></a></li>
        <li><a href="#" target="_blank"><i class="fab fa-twitter"></i></a></li>
        <li><a href="#" target="_blank"><i class="fab fa-linkedin-in"></i></a></li>
        <li><a href="#" target="_blank"><i class="fab fa-instagram"></i></a></li>
      </ul>
    </div>

  </div>

  <!-- Barre inférieure / Copyright -->
  <div class="footer-bottom">
    <p>&copy; <?= date('Y') ?> Université Superieur Saint Paul Tarse. Tous droits réservés. | <a href="politique_condition.php">Politique de confidentialité et condition d'utilisation</a></p>
  </div>

</footer>
