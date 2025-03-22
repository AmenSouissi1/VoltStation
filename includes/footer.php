    <footer class="bg-dark text-white mt-5 py-4">
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-3">
                    <h5>VoltStation</h5>
                    <p>La solution intelligente pour gérer votre réseau de stations de recharge pour véhicules électriques.</p>
                </div>
                <div class="col-md-2 mb-3">
                    <h5>Navigation</h5>
                    <ul class="nav flex-column">
                        <li class="nav-item"><a href="<?php echo APP_URL; ?>" class="nav-link p-0 text-white">Accueil</a></li>
                        <li class="nav-item"><a href="<?php echo APP_URL; ?>/pages/stations.php" class="nav-link p-0 text-white">Stations</a></li>
                        <li class="nav-item"><a href="<?php echo APP_URL; ?>/pages/tarifs.php" class="nav-link p-0 text-white">Tarifs</a></li>
                    </ul>
                </div>
                <div class="col-md-2 mb-3">
                    <h5>Ressources</h5>
                    <ul class="nav flex-column">
                        <li class="nav-item"><a href="#" class="nav-link p-0 text-white">FAQ</a></li>
                        <li class="nav-item"><a href="#" class="nav-link p-0 text-white">Assistance</a></li>
                        <li class="nav-item"><a href="#" class="nav-link p-0 text-white">Partenaires</a></li>
                        <li class="nav-item"><a href="#" class="nav-link p-0 text-white">Blog</a></li>
                    </ul>
                </div>
                <div class="col-md-4 mb-3">
                    <h5>Contact</h5>
                    <ul class="nav flex-column">
                        <li class="nav-item"><a href="mailto:contact@voltstation.com" class="nav-link p-0 text-white">contact@voltstation.com</a></li>
                        <li class="nav-item"><span class="nav-link p-0 text-white">+33 (0)1 23 45 67 89</span></li>
                        <li class="nav-item"><span class="nav-link p-0 text-white">123 Avenue de l'Énergie, 75000 Paris</span></li>
                    </ul>
                    <div class="mt-3">
                        <a href="#" class="text-white me-2"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="text-white me-2"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="text-white me-2"><i class="fab fa-linkedin-in"></i></a>
                        <a href="#" class="text-white"><i class="fab fa-instagram"></i></a>
                    </div>
                </div>
            </div>
            <hr>
            <div class="d-flex flex-column flex-sm-row justify-content-between py-2">
                <p>&copy; <?php echo date('Y'); ?> VoltStation. Tous droits réservés.</p>
                <ul class="list-unstyled d-flex">
                    <li class="ms-3"><a class="text-white" href="#">Mentions légales</a></li>
                    <li class="ms-3"><a class="text-white" href="#">Politique de confidentialité</a></li>
                    <li class="ms-3"><a class="text-white" href="#">CGU</a></li>
                </ul>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JavaScript Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.8.0/dist/leaflet.js"></script>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Custom JavaScript -->
    <script src="<?php echo APP_URL; ?>/js/main.js"></script>
    
    <!-- Page specific JS if exists -->
    <?php if(isset($extraJS)) echo $extraJS; ?>
</body>
</html>