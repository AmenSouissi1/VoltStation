<?php
require_once '../../config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../auth/login.php');
    exit;
}

$pageTitle = "Mon Espace";

// Connect to the database
try {
    $db = connectDB();
    
    // Get user info from database
    $user_id = $_SESSION['user_id'];
    $userCollection = $db->utilisateurs;
    $user = $userCollection->findOne(['_id' => new MongoDB\BSON\ObjectId($user_id)]);
    
    if (!$user) {
        // Fallback if user not found
        $_SESSION = array();
        session_destroy();
        header('Location: ../../auth/login.php');
        exit;
    }
    
    // Get user's recent sessions from database
    $sessionsCollection = $db->sessions;
    $userSessions = $sessionsCollection->find(
        ['utilisateur_id' => new MongoDB\BSON\ObjectId($user_id)],
        [
            'sort' => ['date_debut' => -1],
            'limit' => 3
        ]
    )->toArray();
    
    // Get user's reservations from database
    $reservationsCollection = $db->reservations_session;
    $currentDate = new DateTime();
    $userReservations = $reservationsCollection->find(
        [
            'utilisateur_id' => new MongoDB\BSON\ObjectId($user_id),
            'date' => ['$gte' => $currentDate->format('Y-m-d')]
        ],
        [
            'sort' => ['date' => 1, 'heure_debut' => 1],
            'limit' => 3
        ]
    )->toArray();
    
    // Get available stations from database
    $stationsCollection = $db->stations;
    $availableStations = $stationsCollection->find(
        [],
        ['sort' => ['nom' => 1]]
    )->toArray();
    
    // Calculate current month usage statistics
    $firstDayOfMonth = new DateTime('first day of this month');
    $firstDayOfMonthStr = $firstDayOfMonth->format('Y-m-d');
    
    // Calculate previous month's date range
    $firstDayPrevMonth = new DateTime('first day of last month');
    $lastDayPrevMonth = new DateTime('last day of last month');
    
    // Get current month's consumption
    $monthlyConsumption = $sessionsCollection->aggregate([
        [
            '$match' => [
                'utilisateur_id' => new MongoDB\BSON\ObjectId($user_id),
                'date_debut' => [
                    '$gte' => new MongoDB\BSON\UTCDateTime($firstDayOfMonth->getTimestamp() * 1000)
                ]
            ]
        ],
        [
            '$group' => [
                '_id' => null,
                'totalEnergy' => ['$sum' => '$energie_consommee'],
                'totalCost' => ['$sum' => '$cout']
            ]
        ]
    ])->toArray();
    
    $totalEnergy = !empty($monthlyConsumption) ? $monthlyConsumption[0]['totalEnergy'] : 0;
    $totalCost = !empty($monthlyConsumption) ? $monthlyConsumption[0]['totalCost'] : 0;
    
    // Get previous month's consumption for comparison
    $prevMonthConsumption = $sessionsCollection->aggregate([
        [
            '$match' => [
                'utilisateur_id' => new MongoDB\BSON\ObjectId($user_id),
                'date_debut' => [
                    '$gte' => new MongoDB\BSON\UTCDateTime($firstDayPrevMonth->getTimestamp() * 1000),
                    '$lte' => new MongoDB\BSON\UTCDateTime($lastDayPrevMonth->getTimestamp() * 1000)
                ]
            ]
        ],
        [
            '$group' => [
                '_id' => null,
                'totalEnergy' => ['$sum' => '$energie_consommee'],
                'totalCost' => ['$sum' => '$cout']
            ]
        ]
    ])->toArray();
    
    $prevTotalEnergy = !empty($prevMonthConsumption) ? $prevMonthConsumption[0]['totalEnergy'] : 0;
    $prevTotalCost = !empty($prevMonthConsumption) ? $prevMonthConsumption[0]['totalCost'] : 0;
    
    // Calculate percentages compared to previous month
    $energyPercentage = ($prevTotalEnergy > 0) ? round(($totalEnergy / $prevTotalEnergy) * 100) : 0;
    $costPercentage = ($prevTotalCost > 0) ? round(($totalCost / $prevTotalCost) * 100) : 0;
    
    // Calculate CO2 savings (estimation: 0.5 kg CO2 saved per kWh compared to gasoline)
    $co2Savings = $totalEnergy * 0.5;
    
    // Get user's CO2 goal from database (if exists)
    $userSettings = isset($user['settings']) ? $user['settings'] : [];
    $co2Goal = isset($userSettings['co2_goal']) ? $userSettings['co2_goal'] : 50; // Default to 50kg if not set
    $co2Percentage = $co2Goal > 0 ? min(100, round(($co2Savings / $co2Goal) * 100)) : 0;
    
    // Get user's home location for map centering
    $userLocation = isset($user['location']) && isset($user['location']['coordinates']) 
                  ? $user['location']['coordinates'] 
                  : [48.8566, 2.3522]; // Default to Paris if not set
    
} catch (Exception $e) {
    // Log the error but don't expose details to the user
    error_log("Database error: " . $e->getMessage());
    
    // Provide empty fallback data
    $user = [
        '_id' => $_SESSION['user_id'],
        'nom' => 'Utilisateur',
        'prenom' => '',
        'email' => '',
        'vehicule' => [
            'marque' => 'Non spécifié',
            'modele' => 'Non spécifié',
            'batterie' => 0
        ],
        'abonnement' => [
            'type' => 'standard',
            'date_debut' => date('Y-m-d'),
            'date_fin' => date('Y-m-d', strtotime('+30 days')),
            'statut' => 'actif'
        ],
        'settings' => [
            'co2_goal' => 50
        ],
        'location' => [
            'coordinates' => [48.8566, 2.3522] // Paris
        ]
    ];
    $userSessions = [];
    $userReservations = [];
    $availableStations = [];
    $totalEnergy = 0;
    $totalCost = 0;
    $co2Savings = 0;
    $prevTotalEnergy = 0;
    $prevTotalCost = 0;
    $energyPercentage = 0;
    $costPercentage = 0;
    $co2Goal = 50;
    $co2Percentage = 0;
    $userLocation = [48.8566, 2.3522]; // Paris
}

// Include header
require_once '../../includes/header.php';
?>

<main class="container mt-4">
    <div class="row">
        <?php include_once '../../includes/user_sidebar.php'; ?>
        
        <!-- Main content -->
        <div class="col-md-9">
            <!-- Quick Actions -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title mb-3">Actions rapides</h5>
                            <div class="row">
                                <div class="col-md-4 mb-3 mb-md-0">
                                    <a href="new-reservation.php" class="btn btn-primary d-block py-3">
                                        <i class="fas fa-calendar-plus fa-2x mb-2"></i><br>
                                        Nouvelle réservation
                                    </a>
                                </div>
                                <div class="col-md-4 mb-3 mb-md-0">
                                    <a href="/pages/stations.php" class="btn btn-success d-block py-3">
                                        <i class="fas fa-map-marked-alt fa-2x mb-2"></i><br>
                                        Trouver une station
                                    </a>
                                </div>
                                <div class="col-md-4">
                                    <a href="reservations.php" class="btn btn-info d-block py-3 text-white">
                                        <i class="fas fa-history fa-2x mb-2"></i><br>
                                        Mes sessions
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Usage Stats -->
            <div class="row mb-4">
                <div class="col-md-4 mb-4 mb-md-0">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <h5 class="card-title">Ce mois-ci</h5>
                            <div class="display-4 my-3"><?php echo number_format($totalEnergy, 1); ?></div>
                            <p class="text-muted">kWh consommés</p>
                            <div class="progress mt-3" style="height: 10px;">
                                <div class="progress-bar bg-success" role="progressbar" 
                                     style="width: <?php echo min(100, $energyPercentage); ?>%;" 
                                     aria-valuenow="<?php echo min(100, $energyPercentage); ?>" 
                                     aria-valuemin="0" 
                                     aria-valuemax="100"></div>
                            </div>
                            <p class="small mt-2">
                                <?php 
                                if ($prevTotalEnergy > 0) {
                                    echo $energyPercentage . '% par rapport au mois dernier';
                                } else {
                                    echo 'Pas de données du mois précédent';
                                }
                                ?>
                            </p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-4 mb-md-0">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <h5 class="card-title">Économies CO2</h5>
                            <div class="display-4 my-3"><?php echo number_format($co2Savings, 1); ?></div>
                            <p class="text-muted">kg de CO2 évités</p>
                            <div class="progress mt-3" style="height: 10px;">
                                <div class="progress-bar bg-info" role="progressbar" 
                                     style="width: <?php echo $co2Percentage; ?>%;" 
                                     aria-valuenow="<?php echo $co2Percentage; ?>" 
                                     aria-valuemin="0" 
                                     aria-valuemax="100"></div>
                            </div>
                            <p class="small mt-2"><?php echo $co2Percentage; ?>% de votre objectif mensuel (<?php echo $co2Goal; ?> kg)</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <h5 class="card-title">Dépenses</h5>
                            <div class="display-4 my-3"><?php echo number_format($totalCost, 1); ?></div>
                            <p class="text-muted">€ ce mois-ci</p>
                            <div class="progress mt-3" style="height: 10px;">
                                <div class="progress-bar bg-warning" role="progressbar" 
                                     style="width: <?php echo min(100, $costPercentage); ?>%;" 
                                     aria-valuenow="<?php echo min(100, $costPercentage); ?>" 
                                     aria-valuemin="0" 
                                     aria-valuemax="100"></div>
                            </div>
                            <p class="small mt-2">
                                <?php 
                                if ($prevTotalCost > 0) {
                                    echo $costPercentage . '% par rapport au mois dernier';
                                } else {
                                    echo 'Pas de données du mois précédent';
                                }
                                ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Upcoming Reservations and Recent Sessions -->
            <div class="row mb-4">
                <div class="col-md-6 mb-4 mb-md-0">
                    <div class="card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">Sessions à venir</h5>
                            <a href="reservations.php" class="btn btn-sm btn-primary">Voir toutes</a>
                        </div>
                        <div class="card-body">
                            <?php if (empty($userReservations)): ?>
                                <p class="text-center text-muted my-4">Aucune réservation prévue</p>
                                <div class="text-center">
                                    <a href="new-reservation.php" class="btn btn-outline-primary">
                                        <i class="fas fa-calendar-plus me-1"></i> Nouvelle réservation
                                    </a>
                                </div>
                            <?php else: ?>
                                <ul class="list-group list-group-flush">
                                    <?php foreach ($userReservations as $reservation): ?>
                                        <li class="list-group-item">
                                            <div class="d-flex justify-content-between">
                                                <div>
                                                    <?php
                                                    // Récupérer le nom de la station si nécessaire
                                                    $stationName = '';
                                                    $borneNumber = '';
                                                    if (isset($reservation['station_id']) && !empty($reservation['station_id'])) {
                                                        // Si c'est un ObjectId
                                                        if ($reservation['station_id'] instanceof MongoDB\BSON\ObjectId) {
                                                            $station = $db->stations->findOne(['_id' => $reservation['station_id']]);
                                                            $stationName = $station ? $station['nom'] : 'Station inconnue';
                                                        } else {
                                                            // Si c'est une chaîne de caractères (ID)
                                                            $station = $db->stations->findOne(['_id' => new MongoDB\BSON\ObjectId((string)$reservation['station_id'])]);
                                                            $stationName = $station ? $station['nom'] : (string)$reservation['station_id'];
                                                        }
                                                    }
                                                    
                                                    if (isset($reservation['borne_id']) && !empty($reservation['borne_id'])) {
                                                        // Si c'est un ObjectId
                                                        if ($reservation['borne_id'] instanceof MongoDB\BSON\ObjectId) {
                                                            $borne = $db->bornes->findOne(['_id' => $reservation['borne_id']]);
                                                            $borneNumber = $borne ? $borne['numero'] : 'Borne inconnue';
                                                        } else {
                                                            // Si c'est une chaîne de caractères (ID ou numéro)
                                                            try {
                                                                $borne = $db->bornes->findOne(['_id' => new MongoDB\BSON\ObjectId((string)$reservation['borne_id'])]);
                                                                $borneNumber = $borne ? $borne['numero'] : (string)$reservation['borne_id'];
                                                            } catch (Exception $e) {
                                                                // Si ce n'est pas un ObjectId valide, utiliser directement comme numéro
                                                                $borneNumber = (string)$reservation['borne_id'];
                                                            }
                                                        }
                                                    }
                                                    ?>
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($stationName); ?></h6>
                                                    <p class="mb-1">Borne: <?php echo htmlspecialchars($borneNumber); ?></p>
                                                    <small class="text-muted">
                                                        <?php
                                                            $date = date('d/m/Y', strtotime($reservation['date']));
                                                            $time_start = date('H:i', strtotime($reservation['heure_debut']));
                                                            $time_end = date('H:i', strtotime($reservation['heure_fin']));
                                                            echo "Le $date, de $time_start à $time_end";
                                                        ?>
                                                    </small>
                                                </div>
                                                <div>
                                                    <span class="badge <?php echo htmlspecialchars($reservation['statut'] === 'annulé' ? 'bg-danger' : 'bg-success'); ?>">
                                                        <?php echo htmlspecialchars($reservation['statut'] ?? 'confirmé'); ?>
                                                    </span>
                                                    <div class="btn-group btn-group-sm mt-2">
                                                        <a href="edit-reservation.php?id=<?php echo htmlspecialchars($reservation['numero'] ?? ((string)$reservation['_id'])); ?>" class="btn btn-outline-secondary">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <a href="cancel-reservation.php?id=<?php echo htmlspecialchars($reservation['numero'] ?? ((string)$reservation['_id'])); ?>" class="btn btn-outline-danger">
                                                            <i class="fas fa-times"></i>
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">Sessions récentes</h5>
                            <a href="history.php" class="btn btn-sm btn-primary">Voir toutes</a>
                        </div>
                        <div class="card-body">
                            <?php if (empty($userSessions)): ?>
                                <p class="text-center text-muted my-4">Aucune session récente</p>
                            <?php else: ?>
                                <ul class="list-group list-group-flush">
                                    <?php foreach ($userSessions as $session): ?>
                                        <li class="list-group-item">
                                            <div class="d-flex justify-content-between">
                                                <div>
                                                    <?php
                                                    // Récupérer les informations sur la station et la borne
                                                    $stationName = '';
                                                    $borneNumber = '';
                                                    
                                                    if (isset($session['borne_id']) && !empty($session['borne_id'])) {
                                                        $borne = $db->bornes->findOne(['_id' => $session['borne_id']]);
                                                        if ($borne) {
                                                            $borneNumber = $borne['numero'];
                                                            
                                                            // Récupérer la station associée à cette borne
                                                            if (isset($borne['station_id'])) {
                                                                $station = $db->stations->findOne(['_id' => $borne['station_id']]);
                                                                $stationName = $station ? $station['nom'] : 'Station inconnue';
                                                            }
                                                        }
                                                    }
                                                    ?>
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($stationName); ?></h6>
                                                    <p class="mb-1">Borne: <?php echo htmlspecialchars($borneNumber); ?></p>
                                                    <small class="text-muted">
                                                        <?php
                                                            // Conversion de MongoDB\BSON\UTCDateTime en DateTime PHP
                                                            $date_debut = $session['date_debut'] instanceof MongoDB\BSON\UTCDateTime 
                                                                ? $session['date_debut']->toDateTime() 
                                                                : new DateTime($session['date_debut']);
                                                                
                                                            $date_fin = isset($session['date_fin']) && $session['date_fin'] 
                                                                ? ($session['date_fin'] instanceof MongoDB\BSON\UTCDateTime 
                                                                    ? $session['date_fin']->toDateTime() 
                                                                    : new DateTime($session['date_fin']))
                                                                : null;
                                                            
                                                            $date = $date_debut->format('d/m/Y');
                                                            $time_start = $date_debut->format('H:i');
                                                            $time_end = $date_fin ? $date_fin->format('H:i') : 'En cours';
                                                            
                                                            echo "Le $date, de $time_start à $time_end";
                                                        ?>
                                                    </small>
                                                </div>
                                                <div class="text-end">
                                                    <p class="mb-1"><?php echo htmlspecialchars(number_format($session['energie_consommee'] ?? 0, 2)); ?> kWh</p>
                                                    <p class="mb-1"><?php echo htmlspecialchars(number_format($session['cout'] ?? 0, 2)); ?> €</p>
                                                    <a href="session-details.php?id=<?php echo htmlspecialchars((string)$session['_id']); ?>" class="btn btn-sm btn-outline-primary mt-1">
                                                        Détails
                                                    </a>
                                                </div>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Map of Available Stations -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Stations à proximité</h5>
                    <a href="/pages/stations.php" class="btn btn-sm btn-primary">Vue complète</a>
                </div>
                <div class="card-body">
                    <div id="stationMap" style="height: 400px;"></div>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Add Map JavaScript -->
<script src="https://unpkg.com/leaflet@1.8.0/dist/leaflet.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize map with user's location (or default to Paris)
    const map = L.map('stationMap').setView([<?php echo $userLocation[0]; ?>, <?php echo $userLocation[1]; ?>], 12);
    
    // Add OpenStreetMap tile layer
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);
    
    // Add markers for each station
    <?php foreach ($availableStations as $station): ?>
        <?php 
        // Calculer le nombre de bornes disponibles pour cette station
        $bornesDisponibles = $db->bornes->count([
            'station_id' => $station['_id'],
            'statut' => 'disponible'
        ]);
        
        // Calculer le nombre total de bornes pour cette station
        $bornesTotal = $db->bornes->count([
            'station_id' => $station['_id']
        ]);
        
        // Récupérer les coordonnées
        $lat = isset($station['coordonnees']['lat']) ? $station['coordonnees']['lat'] : 0;
        $lng = isset($station['coordonnees']['lng']) ? $station['coordonnees']['lng'] : 0;
        ?>
        L.marker([<?php echo $lat; ?>, <?php echo $lng; ?>])
            .addTo(map)
            .bindPopup(`
                <strong><?php echo htmlspecialchars($station['nom']); ?></strong><br>
                <?php echo htmlspecialchars($station['adresse']); ?><br>
                <span class="text-success"><?php echo $bornesDisponibles; ?> bornes disponibles</span> / <?php echo $bornesTotal; ?> bornes<br>
                <a href="station-details.php?id=<?php echo htmlspecialchars((string)$station['_id']); ?>" class="btn btn-sm btn-primary mt-2">Voir détails</a>
            `);
    <?php endforeach; ?>
});
</script>

<?php require_once '../../includes/footer.php'; ?>