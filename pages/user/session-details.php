<?php
require_once '../../config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../auth/login.php');
    exit;
}

$pageTitle = "Détails de la session";
$sessionData = null;
$error = '';

// Check if session ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $error = 'ID de session non fourni';
} else {
    try {
        $db = connectDB();
        $session_id = $_GET['id'];
        
        // Get user ID from session
        $user_id = $_SESSION['user_id'];
        
        // Get session details
        $sessionsCollection = $db->sessions;
        try {
            $sessionData = $sessionsCollection->findOne([
                '_id' => new MongoDB\BSON\ObjectId($session_id),
                'utilisateur_id' => new MongoDB\BSON\ObjectId($user_id)
            ]);
        } catch (Exception $e) {
            error_log('Error parsing session ID: ' . $e->getMessage());
            $error = 'ID de session invalide';
        }
        
        if (!$sessionData) {
            $error = 'Session non trouvée ou non autorisée';
        } else {
            // Get station and borne details
            $stationName = '';
            $stationAddress = '';
            $borneData = null;
            
            if (isset($sessionData['borne_id']) && !empty($sessionData['borne_id'])) {
                $borneData = $db->bornes->findOne(['_id' => $sessionData['borne_id']]);
                
                if ($borneData && isset($borneData['station_id']) && !empty($borneData['station_id'])) {
                    $stationData = $db->stations->findOne(['_id' => $borneData['station_id']]);
                    if ($stationData) {
                        $stationName = $stationData['nom'];
                        $stationAddress = $stationData['adresse'] ?? '';
                    }
                }
            }
        }
    } catch (Exception $e) {
        error_log('Database error: ' . $e->getMessage());
        $error = 'Erreur de connexion à la base de données';
    }
}

// Include header
require_once '../../includes/header.php';
?>

<main class="container mt-4">
    <div class="row">
        <?php include_once '../../includes/user_sidebar.php'; ?>
        
        <!-- Main content -->
        <div class="col-md-9">
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($error); ?>
                </div>
                <div class="text-center mt-4">
                    <a href="history.php" class="btn btn-primary">
                        <i class="fas fa-arrow-left me-2"></i> Retour à l'historique
                    </a>
                </div>
            <?php elseif ($sessionData): ?>
                <?php
                // Format session data
                $startDateTime = $sessionData['date_debut'] instanceof MongoDB\BSON\UTCDateTime 
                    ? $sessionData['date_debut']->toDateTime() 
                    : new DateTime($sessionData['date_debut']);
                    
                $endDateTime = isset($sessionData['date_fin']) && $sessionData['date_fin']
                    ? ($sessionData['date_fin'] instanceof MongoDB\BSON\UTCDateTime 
                        ? $sessionData['date_fin']->toDateTime() 
                        : new DateTime($sessionData['date_fin']))
                    : null;
                        
                $formattedStartDate = $startDateTime->format('d/m/Y');
                $formattedStartTime = $startDateTime->format('H:i');
                
                $formattedEndDate = $endDateTime ? $endDateTime->format('d/m/Y') : 'En cours';
                $formattedEndTime = $endDateTime ? $endDateTime->format('H:i') : '--:--';
                
                // Calculate duration
                $duration = 'En cours';
                if ($endDateTime) {
                    $durationSeconds = $endDateTime->getTimestamp() - $startDateTime->getTimestamp();
                    $hours = floor($durationSeconds / 3600);
                    $minutes = floor(($durationSeconds % 3600) / 60);
                    $duration = $hours . 'h ' . $minutes . 'm';
                }
                
                // Energy and cost
                $energy = isset($sessionData['energie_consommee']) ? number_format($sessionData['energie_consommee'], 2) . ' kWh' : 'Non disponible';
                $cost = isset($sessionData['cout']) ? number_format($sessionData['cout'], 2) . ' €' : 'Non disponible';
                
                // Calculate CO2 savings (estimation: 0.5 kg CO2 saved per kWh compared to gasoline)
                $co2Savings = isset($sessionData['energie_consommee']) 
                    ? number_format($sessionData['energie_consommee'] * 0.5, 2) . ' kg' 
                    : 'Non disponible';
                ?>
                
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">
                            Détails de la session - <?php echo $formattedStartDate; ?>
                        </h5>
                        <a href="history.php" class="btn btn-sm btn-light">
                            <i class="fas fa-arrow-left me-2"></i> Retour
                        </a>
                    </div>
                    <div class="card-body">
                        <!-- Session overview -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="card bg-light mb-3">
                                    <div class="card-body">
                                        <h6 class="card-title mb-3">Informations générales</h6>
                                        
                                        <div class="mb-2">
                                            <strong>ID de session:</strong> 
                                            <span class="text-muted"><?php echo htmlspecialchars((string)$sessionData['_id']); ?></span>
                                        </div>
                                        
                                        <div class="mb-2">
                                            <strong>Statut:</strong> 
                                            <?php if (($sessionData['statut'] ?? '') === 'terminée'): ?>
                                                <span class="badge bg-success">Terminée</span>
                                            <?php elseif (($sessionData['statut'] ?? '') === 'en cours'): ?>
                                                <span class="badge bg-primary">En cours</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary"><?php echo htmlspecialchars($sessionData['statut'] ?? 'Inconnu'); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="mb-2">
                                            <strong>Station:</strong> 
                                            <span><?php echo htmlspecialchars($stationName); ?></span>
                                        </div>
                                        
                                        <div class="mb-2">
                                            <strong>Adresse:</strong> 
                                            <span><?php echo htmlspecialchars($stationAddress); ?></span>
                                        </div>
                                        
                                        <div class="mb-2">
                                            <strong>Borne:</strong> 
                                            <span><?php echo htmlspecialchars($borneData['numero'] ?? 'Inconnue'); ?></span>
                                        </div>
                                        
                                        <div class="mb-2">
                                            <strong>Type de borne:</strong> 
                                            <span><?php echo htmlspecialchars($borneData['type'] ?? 'Inconnu'); ?></span>
                                        </div>
                                        
                                        <div class="mb-2">
                                            <strong>Puissance de la borne:</strong> 
                                            <span><?php echo htmlspecialchars(($borneData['puissance'] ?? 0) . ' kW'); ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="card bg-light mb-3">
                                    <div class="card-body">
                                        <h6 class="card-title mb-3">Détails de la charge</h6>
                                        
                                        <div class="mb-2">
                                            <strong>Date de début:</strong> 
                                            <span><?php echo $formattedStartDate; ?> à <?php echo $formattedStartTime; ?></span>
                                        </div>
                                        
                                        <div class="mb-2">
                                            <strong>Date de fin:</strong> 
                                            <span><?php echo $formattedEndDate; ?> <?php echo $formattedEndDate !== 'En cours' ? 'à ' . $formattedEndTime : ''; ?></span>
                                        </div>
                                        
                                        <div class="mb-2">
                                            <strong>Durée:</strong> 
                                            <span><?php echo $duration; ?></span>
                                        </div>
                                        
                                        <div class="mb-2">
                                            <strong>Énergie consommée:</strong> 
                                            <span><?php echo $energy; ?></span>
                                        </div>
                                        
                                        <div class="mb-2">
                                            <strong>Coût total:</strong> 
                                            <span><?php echo $cost; ?></span>
                                        </div>
                                        
                                        <div class="mb-2">
                                            <strong>Économie de CO2:</strong> 
                                            <span><?php echo $co2Savings; ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Price breakdown -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h6 class="mb-0">Détail du tarif</h6>
                            </div>
                            <div class="card-body">
                                <?php
                                // Get pricing information
                                $tarif = null;
                                if (isset($borneData['type'])) {
                                    $tarif = $db->tarifications->findOne(['type_borne' => $borneData['type']]);
                                }
                                
                                if ($tarif): 
                                ?>
                                    <div class="table-responsive">
                                        <table class="table table-borderless">
                                            <tbody>
                                                <tr>
                                                    <td>Prix par kWh:</td>
                                                    <td class="text-end"><?php echo number_format($tarif['prix_kwh'], 2); ?> €</td>
                                                </tr>
                                                <tr>
                                                    <td>Prix par minute:</td>
                                                    <td class="text-end"><?php echo number_format($tarif['prix_minute'], 2); ?> €</td>
                                                </tr>
                                                <tr>
                                                    <td>Frais de service:</td>
                                                    <td class="text-end"><?php echo number_format($tarif['frais_service'], 2); ?> €</td>
                                                </tr>
                                                <tr>
                                                    <td>Remise abonnement:</td>
                                                    <td class="text-end">
                                                        <?php 
                                                        $discountPercent = 0;
                                                        if (isset($user['abonnement']['type'])) {
                                                            if ($user['abonnement']['type'] === 'standard') {
                                                                $discountPercent = 10;
                                                            } elseif ($user['abonnement']['type'] === 'premium') {
                                                                $discountPercent = 20;
                                                            }
                                                        }
                                                        echo "-{$discountPercent}%";
                                                        ?>
                                                    </td>
                                                </tr>
                                                <tr class="fw-bold">
                                                    <td>Total:</td>
                                                    <td class="text-end"><?php echo $cost; ?></td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <p class="text-center text-muted">Détails de tarification non disponibles</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Action buttons -->
                        <div class="row">
                            <div class="col-12 text-center">
                                <a href="history.php" class="btn btn-primary me-2">
                                    <i class="fas fa-arrow-left me-2"></i> Retour à l'historique
                                </a>
                                <button class="btn btn-outline-secondary" onclick="window.print()">
                                    <i class="fas fa-print me-2"></i> Imprimer
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php require_once '../../includes/footer.php'; ?>