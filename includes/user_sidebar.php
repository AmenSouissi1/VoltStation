<?php
// Vérifier si le script est appelé directement
if (!defined('APP_URL')) {
    exit('Accès non autorisé');
}

// Obtenir le nom du fichier actuel pour mettre en évidence le lien actif
$current_page = basename($_SERVER['PHP_SELF']);

// Récupération de l'ID utilisateur depuis la session
$user_id = $_SESSION['user_id'] ?? null;

// Les données utilisateur devraient être disponibles dans la variable $user
if (!isset($user) || empty($user)) {
    // Si l'ID utilisateur est disponible, essayer de récupérer les données depuis la base de données
    if ($user_id) {
        try {
            $db = connectDB();
            $userData = $db->utilisateurs->findOne(['_id' => new MongoDB\BSON\ObjectId($user_id)]);
            
            if ($userData) {
                $user = $userData;
            }
        } catch (Exception $e) {
            // En cas d'erreur, on utilise les valeurs par défaut ci-dessous
            error_log("Erreur lors de la récupération des données utilisateur : " . $e->getMessage());
        }
    }
    
    // Si toujours pas de données utilisateur, utiliser les valeurs par défaut
    if (!isset($user) || empty($user)) {
        $user = [
            'prenom' => $_SESSION['user_name'] ?? 'Utilisateur',
            'email' => $_SESSION['user_email'] ?? '',
            'vehicule' => [
                'marque' => 'Non défini',
                'modele' => 'Non défini',
            ],
            'abonnement' => [
                'type' => 'standard',
                'date_fin' => date('Y-m-d', strtotime('+30 days')),
                'statut' => 'actif'
            ]
        ];
    }
}
?>

<!-- Sidebar -->
<div class="col-md-3 mb-4">
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h5 class="card-title mb-0">Mon profil</h5>
        </div>
        <div class="card-body">
            <div class="text-center mb-3">
                <div class="avatar-circle">
                    <span class="initials"><?php echo isset($user['prenom']) ? substr($user['prenom'], 0, 1) : 'U'; ?></span>
                </div>
                <h6 class="mt-2 mb-1"><?php echo isset($user['prenom']) ? htmlspecialchars($user['prenom']) : 'Utilisateur'; ?></h6>
                <p class="small text-muted"><?php echo isset($user['email']) ? htmlspecialchars($user['email']) : ''; ?></p>
            </div>
            
            <hr>
            
            <h6>Mon véhicule</h6>
            <p><?php 
                $marque = isset($user['vehicule']) && isset($user['vehicule']['marque']) ? $user['vehicule']['marque'] : 'Non défini';
                $modele = isset($user['vehicule']) && isset($user['vehicule']['modele']) ? $user['vehicule']['modele'] : '';
                echo htmlspecialchars($marque . ' ' . $modele); 
            ?></p>
            
            <h6>Mon abonnement</h6>
            <div class="mb-3">
                <span class="badge bg-success">Actif</span>
                <span class="ms-2">Abonnement <?php echo isset($user['abonnement']) && isset($user['abonnement']['type']) ? htmlspecialchars($user['abonnement']['type']) : 'standard'; ?></span>
            </div>
            <p class="small text-muted">Expire le: <?php 
                $expireDate = isset($user['abonnement']) && isset($user['abonnement']['date_fin']) && !empty($user['abonnement']['date_fin']) 
                    ? date('d/m/Y', strtotime($user['abonnement']['date_fin'])) 
                    : date('d/m/Y', strtotime('+30 days')); 
                echo $expireDate;
            ?></p>
            
            <div class="d-grid gap-2">
                <a href="profile.php" class="btn btn-outline-primary btn-sm <?php echo ($current_page === 'profile.php') ? 'active' : ''; ?>">
                    <i class="fas fa-edit me-1"></i> Modifier mon profil
                </a>
                <a href="subscriptions.php" class="btn btn-outline-primary btn-sm">
                    <i class="fas fa-credit-card me-1"></i> Gérer mon abonnement
                </a>
            </div>
        </div>
    </div>
</div>