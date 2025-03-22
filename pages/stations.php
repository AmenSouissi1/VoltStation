<?php
require_once '../config.php';

$pageTitle = "Stations de Recharge";

// Get stations from API
$apiUrl = APP_URL . '/api/stations.php';
$stationsJson = file_get_contents($apiUrl);
$stations = json_decode($stationsJson, true);

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
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="mb-0">Stations de Recharge</h1>
            </div>
            
            <!-- Map of All Stations -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Carte des stations</h5>
                </div>
                <div class="card-body">
                    <div id="stationsMap" style="height: 500px;"></div>
                </div>
            </div>
            
            <!-- List of Stations -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Liste des stations</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($stations as $station): ?>
                            <?php 
                                // Count available bornes
                                $availableBornes = 0;
                                $totalBornes = count($station['bornes']);
                                
                                foreach ($station['bornes'] as $borne) {
                                    if ($borne['etat_actuel'] === 'disponible') {
                                        $availableBornes++;
                                    }
                                }
                                
                                // Calculate availability percentage
                                $availabilityPercentage = ($availableBornes / $totalBornes) * 100;
                                
                                // Determine status color
                                $statusColorClass = '';
                                if ($station['statut'] === 'opérationnelle') {
                                    $statusColorClass = 'bg-success';
                                } elseif ($station['statut'] === 'en maintenance') {
                                    $statusColorClass = 'bg-warning';
                                } else {
                                    $statusColorClass = 'bg-danger';
                                }
                            ?>
                            <div class="col-md-6 col-lg-4 mb-4">
                                <div class="card h-100">
                                    <div class="card-header d-flex align-items-center">
                                        <span class="status-indicator me-2 <?php echo $statusColorClass; ?>"></span>
                                        <h5 class="card-title mb-0"><?php echo htmlspecialchars($station['nom_station']); ?></h5>
                                    </div>
                                    <div class="card-body">
                                        <p class="card-text">
                                            <i class="fas fa-map-marker-alt me-2"></i>
                                            <?php echo htmlspecialchars($station['adresse']['rue'] . 
                                                (isset($station['adresse']['code_postal']) && !empty($station['adresse']['code_postal']) 
                                                ? ', ' . $station['adresse']['code_postal'] : '') . 
                                                (!empty($station['adresse']['ville']) ? ' ' . $station['adresse']['ville'] : '')); ?>
                                        </p>
                                        
                                        <div class="mb-3">
                                            <div class="d-flex justify-content-between mb-1">
                                                <span>Disponibilité:</span>
                                                <span><?php echo $availableBornes; ?> / <?php echo $totalBornes; ?> bornes</span>
                                            </div>
                                            <div class="progress" style="height: 10px;">
                                                <div class="progress-bar bg-success" role="progressbar" 
                                                    style="width: <?php echo $availabilityPercentage; ?>%;" 
                                                    aria-valuenow="<?php echo $availabilityPercentage; ?>" 
                                                    aria-valuemin="0" 
                                                    aria-valuemax="100"></div>
                                            </div>
                                        </div>
                                        
                                        <ul class="list-group list-group-flush mb-3">
                                            <li class="list-group-item d-flex justify-content-between">
                                                <span>Statut:</span>
                                                <span class="badge <?php echo $statusColorClass; ?>"><?php echo htmlspecialchars($station['statut']); ?></span>
                                            </li>
                                            <li class="list-group-item d-flex justify-content-between">
                                                <span>Gestionnaire:</span>
                                                <span><?php echo htmlspecialchars($station['gestionnaire']); ?></span>
                                            </li>
                                            <li class="list-group-item d-flex justify-content-between">
                                                <span>Temps moyen d'utilisation:</span>
                                                <span><?php echo htmlspecialchars($station['temps_moyen_utilisation']); ?> min</span>
                                            </li>
                                        </ul>
                                        
                                        <div class="d-grid">
                                            <a href="station-details.php?id=<?php echo htmlspecialchars($station['id']); ?>" class="btn btn-primary">
                                                <i class="fas fa-info-circle me-1"></i> Voir détails
                                            </a>
                                            <?php if (isset($_SESSION['user_id'])): ?>
                                                <a href="user/new-reservation.php?station_id=<?php echo htmlspecialchars($station['id']); ?>" class="btn btn-outline-primary mt-2">
                                                    <i class="fas fa-calendar-plus me-1"></i> Réserver une borne
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
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
    const map = L.map('stationsMap').setView([48.8566, 2.3522], 12); // Paris coordinates
    
    // Add OpenStreetMap tile layer
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);
    
    // Add markers for each station
    <?php foreach ($stations as $station): ?>
        <?php
        // Determine marker color based on status
        $markerColor = 'green';
        if ($station['statut'] === 'en maintenance') {
            $markerColor = 'orange';
        } else if ($station['statut'] === 'hors service') {
            $markerColor = 'red';
        }
        
        // Count available bornes
        $availableBornes = 0;
        $totalBornes = count($station['bornes']);
        
        foreach ($station['bornes'] as $borne) {
            if ($borne['etat_actuel'] === 'disponible') {
                $availableBornes++;
            }
        }
        ?>
        L.marker([<?php echo $station['coordonnees']['latitude']; ?>, <?php echo $station['coordonnees']['longitude']; ?>], {
            icon: L.divIcon({
                className: 'custom-marker marker-<?php echo $markerColor; ?>',
                html: '<i class="fas fa-charging-station"></i>',
                iconSize: [30, 30]
            })
        })
        .addTo(map)
        .bindPopup(`
            <strong><?php echo htmlspecialchars($station['nom_station']); ?></strong><br>
            <?php echo htmlspecialchars($station['adresse']['rue'] . 
                (isset($station['adresse']['code_postal']) && !empty($station['adresse']['code_postal']) 
                ? ', ' . $station['adresse']['code_postal'] : '') . 
                (!empty($station['adresse']['ville']) ? ' ' . $station['adresse']['ville'] : '')); ?><br>
            <span class="text-success"><?php echo $availableBornes; ?> bornes disponibles</span> / <?php echo $totalBornes; ?> bornes<br>
            <a href="station-details.php?id=<?php echo htmlspecialchars($station['id']); ?>" class="btn btn-sm btn-primary mt-2">Voir détails</a>
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="user/new-reservation.php?station_id=<?php echo htmlspecialchars($station['id']); ?>" class="btn btn-sm btn-outline-primary mt-2">Réserver</a>
            <?php endif; ?>
        `);
    <?php endforeach; ?>
});
</script>

<style>
.status-indicator {
    display: inline-block;
    width: 12px;
    height: 12px;
    border-radius: 50%;
}
.custom-marker {
    background-color: #fff;
    border-radius: 50%;
    text-align: center;
    line-height: 30px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.3);
}
.marker-green { color: #28a745; }
.marker-orange { color: #fd7e14; }
.marker-red { color: #dc3545; }
</style>

<?php require_once '../includes/footer.php'; ?>