<?php
require_once '../config.php';

$pageTitle = "Détails de la Station";

// Get station ID from URL parameter
$id = isset($_GET['id']) ? $_GET['id'] : null;

if (!$id) {
    header('Location: stations.php');
    exit;
}

// Get station details from API
$apiUrl = APP_URL . '/api/stations.php?id=' . urlencode($id);
$stationJson = file_get_contents($apiUrl);
$station = json_decode($stationJson, true);

// Check if station exists
if (!$station || isset($station['error'])) {
    header('Location: stations.php');
    exit;
}

// Include header
require_once '../includes/header.php';
?>

<style>
/* Fix pour s'assurer que tous les boutons primary ont un texte blanc */
.btn-primary, .btn-sm.btn-primary, 
.card .btn-primary, .leaflet-popup-content .btn-primary {
    color: white !important;
}
</style>

<main class="container mt-4">
    <div class="row">
        <div class="col-12">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="stations.php">Stations</a></li>
                    <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($station['nom_station']); ?></li>
                </ol>
            </nav>
            
            <div class="card mb-4">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-8">
                            <h1 class="mb-2"><?php echo htmlspecialchars($station['nom_station']); ?></h1>
                            <p class="text-muted mb-3">
                                <i class="fas fa-map-marker-alt me-2"></i>
                                <?php echo htmlspecialchars($station['adresse']['rue'] . ', ' . $station['adresse']['code_postal'] . ' ' . $station['adresse']['ville']); ?>
                            </p>
                            
                            <?php
                            // Determine status badge class
                            $statusClass = 'bg-success';
                            if ($station['statut'] === 'en maintenance') {
                                $statusClass = 'bg-warning';
                            } else if ($station['statut'] === 'hors service') {
                                $statusClass = 'bg-danger';
                            }
                            ?>
                            
                            <div class="mb-4">
                                <span class="badge <?php echo $statusClass; ?> me-2"><?php echo htmlspecialchars($station['statut']); ?></span>
                                <span class="badge bg-primary"><?php echo htmlspecialchars($station['nombre_bornes']); ?> bornes</span>
                                <span class="ms-3 text-muted">Installée le <?php echo date('d/m/Y', strtotime($station['date_installation'])); ?></span>
                            </div>
                            
                            <h5>Information sur la station</h5>
                            <table class="table table-bordered">
                                <tbody>
                                    <tr>
                                        <th width="40%">Gestionnaire</th>
                                        <td><?php echo htmlspecialchars($station['gestionnaire']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Temps moyen d'utilisation</th>
                                        <td><?php echo htmlspecialchars($station['temps_moyen_utilisation']); ?> minutes</td>
                                    </tr>
                                    <tr>
                                        <th>Coordonnées GPS</th>
                                        <td>
                                            <span class="d-block">Latitude: <?php echo htmlspecialchars($station['coordonnees']['latitude']); ?></span>
                                            <span class="d-block">Longitude: <?php echo htmlspecialchars($station['coordonnees']['longitude']); ?></span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                            
                            <?php if (isset($_SESSION['user_id'])): ?>
                                <a href="../pages/user/new-reservation.php?station_id=<?php echo htmlspecialchars($station['id']); ?>" class="btn btn-primary mt-3">
                                    <i class="fas fa-calendar-plus me-1"></i> Réserver une borne
                                </a>
                            <?php else: ?>
                                <a href="../auth/login.php" class="btn btn-primary mt-3">
                                    <i class="fas fa-sign-in-alt me-1"></i> Connectez-vous pour réserver
                                </a>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4">
                            <div id="stationMap" style="height: 250px;" class="mb-3"></div>
                            
                            <div class="card mb-3">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Disponibilité des bornes</h5>
                                </div>
                                <div class="card-body">
                                    <?php
                                    // Count bornes by status
                                    $bornesByStatus = [
                                        'disponible' => 0,
                                        'en cours d\'utilisation' => 0,
                                        'en maintenance' => 0,
                                        'hors service' => 0
                                    ];
                                    
                                    foreach ($station['bornes'] as $borne) {
                                        if (isset($bornesByStatus[$borne['etat_actuel']])) {
                                            $bornesByStatus[$borne['etat_actuel']]++;
                                        }
                                    }
                                    
                                    // Calculate percentages
                                    $totalBornes = count($station['bornes']);
                                    $availablePercentage = ($bornesByStatus['disponible'] / $totalBornes) * 100;
                                    ?>
                                    
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between mb-1">
                                            <span>Bornes disponibles:</span>
                                            <strong><?php echo $bornesByStatus['disponible']; ?> / <?php echo $totalBornes; ?></strong>
                                        </div>
                                        <div class="progress" style="height: 10px;">
                                            <div class="progress-bar bg-success" role="progressbar" 
                                                style="width: <?php echo $availablePercentage; ?>%;" 
                                                aria-valuenow="<?php echo $availablePercentage; ?>" 
                                                aria-valuemin="0" 
                                                aria-valuemax="100"></div>
                                        </div>
                                    </div>
                                    
                                    <ul class="list-group list-group-flush">
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            Disponibles
                                            <span class="badge bg-success rounded-pill"><?php echo $bornesByStatus['disponible']; ?></span>
                                        </li>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            En utilisation
                                            <span class="badge bg-primary rounded-pill"><?php echo $bornesByStatus['en cours d\'utilisation']; ?></span>
                                        </li>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            En maintenance
                                            <span class="badge bg-warning rounded-pill"><?php echo $bornesByStatus['en maintenance']; ?></span>
                                        </li>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            Hors service
                                            <span class="badge bg-danger rounded-pill"><?php echo $bornesByStatus['hors service']; ?></span>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Bornes List -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Liste des bornes</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Type</th>
                                    <th>Puissance</th>
                                    <th>État actuel</th>
                                    <th>Dernier entretien</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($station['bornes'] as $borne): ?>
                                    <?php
                                    // Determine status badge class
                                    $borneStatusClass = 'bg-success';
                                    if ($borne['etat_actuel'] === 'en cours d\'utilisation') {
                                        $borneStatusClass = 'bg-primary';
                                    } else if ($borne['etat_actuel'] === 'en maintenance') {
                                        $borneStatusClass = 'bg-warning';
                                    } else if ($borne['etat_actuel'] === 'hors service') {
                                        $borneStatusClass = 'bg-danger';
                                    }
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($borne['id_borne']); ?></td>
                                        <td><?php echo htmlspecialchars($borne['type_borne']); ?></td>
                                        <td><?php echo htmlspecialchars($borne['puissance']); ?> kW</td>
                                        <td><span class="badge <?php echo $borneStatusClass; ?>"><?php echo htmlspecialchars($borne['etat_actuel']); ?></span></td>
                                        <td><?php echo isset($borne['dernier_entretien']) ? date('d/m/Y', strtotime($borne['dernier_entretien'])) : 'N/A'; ?></td>
                                        <td>
                                            <?php if ($borne['etat_actuel'] === 'disponible' && isset($_SESSION['user_id'])): ?>
                                                <a href="../pages/user/new-reservation.php?station_id=<?php echo htmlspecialchars($station['id']); ?>&borne_id=<?php echo htmlspecialchars($borne['id_borne']); ?>" class="btn btn-sm btn-primary">
                                                    <i class="fas fa-calendar-plus"></i> Réserver
                                                </a>
                                            <?php elseif ($borne['etat_actuel'] === 'disponible'): ?>
                                                <a href="../auth/login.php" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-sign-in-alt"></i> Connectez-vous
                                                </a>
                                            <?php else: ?>
                                                <button class="btn btn-sm btn-outline-secondary" disabled>
                                                    <i class="fas fa-ban"></i> Non disponible
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Add Map JavaScript -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.8.0/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.8.0/dist/leaflet.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize map
    const map = L.map('stationMap').setView([
        <?php echo $station['coordonnees']['latitude']; ?>, 
        <?php echo $station['coordonnees']['longitude']; ?>
    ], 15);
    
    // Add OpenStreetMap tile layer
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);
    
    // Add marker for this station
    L.marker([
        <?php echo $station['coordonnees']['latitude']; ?>, 
        <?php echo $station['coordonnees']['longitude']; ?>
    ]).addTo(map)
    .bindPopup('<strong><?php echo htmlspecialchars($station['nom_station']); ?></strong>')
    .openPopup();
});
</script>

<?php require_once '../includes/footer.php'; ?>