<?php
// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']);
$userRole = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : '';
$userName = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' . APP_NAME : APP_NAME; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
    
    <!-- Leaflet CSS for maps -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.8.0/dist/leaflet.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/css/style.css">
    
    <!-- Style pour s'assurer que les boutons primaires ont un texte blanc -->
    <style>
    .btn-primary, .btn-sm.btn-primary, 
    .card .btn-primary, .leaflet-popup-content .btn-primary {
        color: white !important; 
    }
    </style>
    
    <!-- Page specific CSS if exists -->
    <?php if(isset($extraCSS)) echo $extraCSS; ?>
</head>
<body>
    <header>
        <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
            <div class="container">
                <a class="navbar-brand" href="<?php echo APP_URL; ?>">
                    <i class="fas fa-charging-station me-2"></i>
                    <?php echo APP_NAME; ?>
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain">
                    <span class="navbar-toggler-icon"></span>
                </button>
                
                <div class="collapse navbar-collapse" id="navbarMain">
                    <ul class="navbar-nav me-auto">
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo APP_URL; ?>">Accueil</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo APP_URL; ?>/pages/stations.php">Stations</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo APP_URL; ?>/pages/tarifs.php">Tarifs</a>
                        </li>
                    </ul>
                    
                    <ul class="navbar-nav ms-auto">
                        <?php if($isLoggedIn): ?>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                                    <i class="fas fa-user-circle me-1"></i> <?php echo htmlspecialchars($userName); ?>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <?php if($userRole === 'admin'): ?>
                                        <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/pages/admin/dashboard.php">Tableau de bord</a></li>
                                    <?php else: ?>
                                        <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/pages/user/dashboard.php">Mon espace</a></li>
                                    <?php endif; ?>
                                    <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/pages/user/profile.php">Profil</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/auth/logout.php">Déconnexion</a></li>
                                </ul>
                            </li>
                        <?php else: ?>
                            <li class="nav-item">
                                <a class="nav-link" href="<?php echo APP_URL; ?>/auth/login.php">Connexion</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link btn btn-sm btn-outline-light" href="<?php echo APP_URL; ?>/auth/register.php">Inscription</a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </nav>
    </header>