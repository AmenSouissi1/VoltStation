<?php
require_once 'config.php';
require_once 'includes/header.php';

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']);
$userRole = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : '';

// Redirect based on user role
if ($isLoggedIn) {
    if ($userRole === 'admin') {
        header('Location: pages/admin/dashboard.php');
        exit;
    } else {
        header('Location: pages/user/dashboard.php');
        exit;
    }
}
?>

<main class="container mt-5">
    <div class="row">
        <div class="col-md-8">
            <h1 class="display-4">Bienvenue sur VoltStation</h1>
            <p class="lead">La solution intelligente pour gérer votre réseau de stations de recharge pour véhicules électriques.</p>
            
            <div class="row mt-4">
                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-body">
                            <h5 class="card-title">Pour les gestionnaires</h5>
                            <p class="card-text">Suivez et optimisez l'utilisation des bornes de recharge en temps réel.</p>
                            <ul>
                                <li>Suivi en temps réel des bornes</li>
                                <li>Statistiques d'utilisation</li>
                                <li>Alertes de maintenance</li>
                                <li>Gestion des tarifs</li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-body">
                            <h5 class="card-title">Pour les utilisateurs</h5>
                            <p class="card-text">Rechargez votre véhicule électrique facilement et efficacement.</p>
                            <ul>
                                <li>Réservation de bornes</li>
                                <li>Suivi des consommations</li>
                                <li>Historique des recharges</li>
                                <li>Abonnements personnalisés</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="text-center mt-4">
                <a href="auth/register.php" class="btn btn-primary btn-lg me-2">S'inscrire</a>
                <a href="auth/login.php" class="btn btn-outline-primary btn-lg">Se connecter</a>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Connexion rapide</h5>
                    <form action="auth/login.php" method="post">
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Mot de passe</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="remember" name="remember">
                            <label class="form-check-label" for="remember">Se souvenir de moi</label>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Se connecter</button>
                    </form>
                </div>
            </div>
            
            <div class="card mt-4">
                <div class="card-body">
                    <h5 class="card-title">Statistiques</h5>
                    <p><strong>Stations actives:</strong> <span id="active-stations">--</span></p>
                    <p><strong>Bornes disponibles:</strong> <span id="available-bornes">--</span></p>
                    <p><strong>Recharges aujourd'hui:</strong> <span id="today-sessions">--</span></p>
                    <p><strong>kWh économisés:</strong> <span id="saved-kwh">--</span></p>
                </div>
            </div>
        </div>
    </div>
</main>

<script src="js/home.js"></script>

<?php require_once 'includes/footer.php'; ?>