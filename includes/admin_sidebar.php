<?php
// Vérifier si le script est appelé directement
if (!defined('APP_URL')) {
    exit('Accès non autorisé');
}

// Obtenir le nom du fichier actuel pour mettre en évidence le lien actif
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!-- Sidebar -->
<div class="col-md-3 col-lg-2 d-md-block bg-light sidebar">
    <div class="position-sticky pt-3">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page === 'dashboard.php') ? 'active' : ''; ?>" href="dashboard.php">
                    <i class="fas fa-tachometer-alt me-2"></i> Tableau de bord
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page === 'stations.php') ? 'active' : ''; ?>" href="stations.php">
                    <i class="fas fa-map-marker-alt me-2"></i> Stations
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page === 'bornes.php') ? 'active' : ''; ?>" href="bornes.php">
                    <i class="fas fa-charging-station me-2"></i> Bornes
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page === 'sessions.php') ? 'active' : ''; ?>" href="sessions.php">
                    <i class="fas fa-history me-2"></i> Sessions
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page === 'users.php') ? 'active' : ''; ?>" href="users.php">
                    <i class="fas fa-users me-2"></i> Utilisateurs
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page === 'tarifs.php') ? 'active' : ''; ?>" href="tarifs.php">
                    <i class="fas fa-euro-sign me-2"></i> Tarification
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page === 'settings.php') ? 'active' : ''; ?>" href="settings.php">
                    <i class="fas fa-cog me-2"></i> Paramètres
                </a>
            </li>
        </ul>
    </div>
</div>