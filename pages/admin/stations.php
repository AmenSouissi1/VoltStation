<?php
require_once '../../config.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../../auth/login.php');
    exit;
}

$pageTitle = "Gestion des Stations";
$success = '';
$error = '';

// Get stations from database
try {
    $db = connectDB();
    $stations = $db->stations->find()->toArray();
} catch (Exception $e) {
    $error = 'Erreur de connexion à la base de données: ' . $e->getMessage();
    $stations = [];
}

// Handle form submission for adding/updating stations
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'add_station') {
        $nom_station = $_POST['nom_station'] ?? '';
        $adresse = $_POST['adresse'] ?? '';
        $ville = $_POST['ville'] ?? '';
        $code_postal = $_POST['code_postal'] ?? '';
        $latitude = $_POST['latitude'] ?? 0;
        $longitude = $_POST['longitude'] ?? 0;
        $places_totales = $_POST['places_totales'] ?? 0;
        $statut = $_POST['statut'] ?? 'active';
        
        if (empty($nom_station) || empty($adresse) || empty($ville) || empty($code_postal)) {
            $error = 'Veuillez remplir tous les champs obligatoires.';
        } else {
            try {
                // Create new station
                $newStation = [
                    'nom_station' => $nom_station,
                    'adresse' => $adresse,
                    'ville' => $ville,
                    'code_postal' => $code_postal,
                    'localisation' => [
                        'type' => 'Point',
                        'coordinates' => [(float)$longitude, (float)$latitude]
                    ],
                    'places_totales' => (int)$places_totales,
                    'statut' => $statut,
                    'date_creation' => new MongoDB\BSON\UTCDateTime(time() * 1000)
                ];
                
                $result = $db->stations->insertOne($newStation);
                
                if ($result->getInsertedCount() > 0) {
                    $success = 'Station ajoutée avec succès!';
                    // Refresh stations
                    $stations = $db->stations->find()->toArray();
                } else {
                    $error = 'Erreur lors de l\'ajout de la station.';
                }
            } catch (Exception $e) {
                $error = 'Erreur lors de l\'ajout de la station: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'update_station' && isset($_POST['station_id'])) {
        $station_id = $_POST['station_id'];
        $nom_station = $_POST['nom_station'] ?? '';
        $adresse = $_POST['adresse'] ?? '';
        $ville = $_POST['ville'] ?? '';
        $code_postal = $_POST['code_postal'] ?? '';
        $latitude = $_POST['latitude'] ?? 0;
        $longitude = $_POST['longitude'] ?? 0;
        $places_totales = $_POST['places_totales'] ?? 0;
        $statut = $_POST['statut'] ?? 'active';
        
        if (empty($nom_station) || empty($adresse) || empty($ville) || empty($code_postal)) {
            $error = 'Veuillez remplir tous les champs obligatoires.';
        } else {
            try {
                // Update station
                $updates = [
                    'nom_station' => $nom_station,
                    'adresse' => $adresse,
                    'ville' => $ville,
                    'code_postal' => $code_postal,
                    'localisation' => [
                        'type' => 'Point',
                        'coordinates' => [(float)$longitude, (float)$latitude]
                    ],
                    'places_totales' => (int)$places_totales,
                    'statut' => $statut,
                    'date_modification' => new MongoDB\BSON\UTCDateTime(time() * 1000)
                ];
                
                $result = $db->stations->updateOne(
                    ['_id' => new MongoDB\BSON\ObjectId($station_id)],
                    ['$set' => $updates]
                );
                
                if ($result->getModifiedCount() > 0) {
                    $success = 'Station mise à jour avec succès!';
                    // Refresh stations
                    $stations = $db->stations->find()->toArray();
                } else {
                    $error = 'Aucune modification apportée ou station non trouvée.';
                }
            } catch (Exception $e) {
                $error = 'Erreur lors de la mise à jour de la station: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'delete_station' && isset($_POST['station_id'])) {
        $station_id = $_POST['station_id'];
        
        try {
            // Check if station has bornes
            $bornesCount = $db->bornes->countDocuments(['station_id' => new MongoDB\BSON\ObjectId($station_id)]);
            
            if ($bornesCount > 0) {
                $error = 'Impossible de supprimer cette station car elle contient des bornes. Veuillez d\'abord supprimer ou déplacer ces bornes.';
            } else {
                // Delete station
                $result = $db->stations->deleteOne(['_id' => new MongoDB\BSON\ObjectId($station_id)]);
                
                if ($result->getDeletedCount() > 0) {
                    $success = 'Station supprimée avec succès!';
                    // Refresh stations
                    $stations = $db->stations->find()->toArray();
                } else {
                    $error = 'Erreur lors de la suppression de la station.';
                }
            }
        } catch (Exception $e) {
            $error = 'Erreur lors de la suppression de la station: ' . $e->getMessage();
        }
    }
}

// Count bornes for each station
$stationBorneCount = [];
foreach ($stations as $station) {
    try {
        $stationId = (string)$station['_id'];
        $bornesCount = $db->bornes->countDocuments(['station_id' => new MongoDB\BSON\ObjectId($stationId)]);
        $stationBorneCount[$stationId] = $bornesCount;
    } catch (Exception $e) {
        $stationBorneCount[$stationId] = 'Erreur';
    }
}

// Include header
require_once '../../includes/header.php';
?>

<main class="container-fluid mt-4">
    <div class="row">
        <?php include_once '../../includes/admin_sidebar.php'; ?>

        <!-- Main content -->
        <div class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Gestion des Stations</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addStationModal">
                        <i class="fas fa-plus me-1"></i> Ajouter une station
                    </button>
                </div>
            </div>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($success); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <!-- Stations Overview Cards -->
            <div class="row mb-4">
                <div class="col-md-3 mb-4">
                    <div class="card bg-primary text-white h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-uppercase">Total Stations</h6>
                                    <h2 class="display-6"><?php echo count($stations); ?></h2>
                                </div>
                                <i class="fas fa-map-marker-alt fa-3x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3 mb-4">
                    <div class="card bg-success text-white h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-uppercase">Actives</h6>
                                    <h2 class="display-6">
                                        <?php 
                                            $activeStations = array_filter($stations, function($station) {
                                                return $station['statut'] === 'active';
                                            });
                                            echo count($activeStations);
                                        ?>
                                    </h2>
                                </div>
                                <i class="fas fa-check-circle fa-3x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3 mb-4">
                    <div class="card bg-warning text-white h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-uppercase">En Maintenance</h6>
                                    <h2 class="display-6">
                                        <?php 
                                            $maintenanceStations = array_filter($stations, function($station) {
                                                return $station['statut'] === 'maintenance';
                                            });
                                            echo count($maintenanceStations);
                                        ?>
                                    </h2>
                                </div>
                                <i class="fas fa-tools fa-3x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3 mb-4">
                    <div class="card bg-danger text-white h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-uppercase">Hors Service</h6>
                                    <h2 class="display-6">
                                        <?php 
                                            $offlineStations = array_filter($stations, function($station) {
                                                return $station['statut'] === 'hors service';
                                            });
                                            echo count($offlineStations);
                                        ?>
                                    </h2>
                                </div>
                                <i class="fas fa-times-circle fa-3x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Stations List -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Liste des stations</h5>
                    <div class="input-group w-25">
                        <input type="text" class="form-control" id="stationSearch" placeholder="Rechercher une station...">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Nom</th>
                                    <th>Adresse</th>
                                    <th>Ville</th>
                                    <th>Bornes</th>
                                    <th>Statut</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="stationsTableBody">
                                <?php if (empty($stations)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center">Aucune station trouvée</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($stations as $station): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($station['nom_station'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($station['adresse'] ?? 'N/A'); ?></td>
                                            <td>
                                                <?php 
                                                    $villeCP = '';
                                                    if (isset($station['code_postal'])) {
                                                        $villeCP .= htmlspecialchars($station['code_postal']) . ' ';
                                                    }
                                                    if (isset($station['ville'])) {
                                                        $villeCP .= htmlspecialchars($station['ville']);
                                                    } else {
                                                        $villeCP .= 'N/A';
                                                    }
                                                    echo $villeCP;
                                                ?>
                                            </td>
                                            <td>
                                                <?php 
                                                    $stationId = (string)$station['_id'];
                                                    echo isset($stationBorneCount[$stationId]) ? $stationBorneCount[$stationId] : '0';
                                                ?> / <?php echo $station['places_totales'] ?? '0'; ?>
                                            </td>
                                            <td>
                                                <?php 
                                                    $statusClass = 'bg-secondary';
                                                    $statusText = 'Inconnu';
                                                    
                                                    switch ($station['statut'] ?? '') {
                                                        case 'active':
                                                            $statusClass = 'bg-success';
                                                            $statusText = 'Active';
                                                            break;
                                                        case 'maintenance':
                                                            $statusClass = 'bg-warning';
                                                            $statusText = 'Maintenance';
                                                            break;
                                                        case 'hors service':
                                                            $statusClass = 'bg-danger';
                                                            $statusText = 'Hors service';
                                                            break;
                                                    }
                                                ?>
                                                <span class="badge <?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <button type="button" class="btn btn-outline-primary edit-station-btn" data-bs-toggle="modal" data-bs-target="#editStationModal" data-station-id="<?php echo $station['_id']; ?>">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-outline-danger delete-station-btn" data-bs-toggle="modal" data-bs-target="#deleteStationModal" data-station-id="<?php echo $station['_id']; ?>" data-station-name="<?php echo htmlspecialchars($station['nom_station'] ?? 'N/A'); ?>">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                    <a href="bornes.php?station_id=<?php echo $station['_id']; ?>" class="btn btn-outline-info">
                                                        <i class="fas fa-charging-station"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Station Map -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Carte des stations</h5>
                </div>
                <div class="card-body">
                    <div id="stationMap" style="height: 450px;"></div>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Add Station Modal -->
<div class="modal fade" id="addStationModal" tabindex="-1" aria-labelledby="addStationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="addStationModalLabel">Ajouter une station</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="stations.php" method="post">
                <input type="hidden" name="action" value="add_station">
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="nom_station" class="form-label">Nom de la station *</label>
                            <input type="text" class="form-control" id="nom_station" name="nom_station" required>
                        </div>
                        <div class="col-md-6">
                            <label for="statut" class="form-label">Statut</label>
                            <select class="form-select" id="statut" name="statut">
                                <option value="active">Active</option>
                                <option value="maintenance">En maintenance</option>
                                <option value="hors service">Hors service</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="adresse" class="form-label">Adresse *</label>
                        <input type="text" class="form-control" id="adresse" name="adresse" required>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="code_postal" class="form-label">Code postal *</label>
                            <input type="text" class="form-control" id="code_postal" name="code_postal" required>
                        </div>
                        <div class="col-md-8">
                            <label for="ville" class="form-label">Ville *</label>
                            <input type="text" class="form-control" id="ville" name="ville" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="latitude" class="form-label">Latitude</label>
                            <input type="number" step="0.000001" class="form-control" id="latitude" name="latitude">
                        </div>
                        <div class="col-md-6">
                            <label for="longitude" class="form-label">Longitude</label>
                            <input type="number" step="0.000001" class="form-control" id="longitude" name="longitude">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="places_totales" class="form-label">Nombre de places</label>
                        <input type="number" class="form-control" id="places_totales" name="places_totales" min="1" value="1">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Ajouter</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Station Modal -->
<div class="modal fade" id="editStationModal" tabindex="-1" aria-labelledby="editStationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="editStationModalLabel">Modifier la station</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="stations.php" method="post">
                <input type="hidden" name="action" value="update_station">
                <input type="hidden" name="station_id" id="edit_station_id">
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_nom_station" class="form-label">Nom de la station *</label>
                            <input type="text" class="form-control" id="edit_nom_station" name="nom_station" required>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_statut" class="form-label">Statut</label>
                            <select class="form-select" id="edit_statut" name="statut">
                                <option value="active">Active</option>
                                <option value="maintenance">En maintenance</option>
                                <option value="hors service">Hors service</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="edit_adresse" class="form-label">Adresse *</label>
                        <input type="text" class="form-control" id="edit_adresse" name="adresse" required>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="edit_code_postal" class="form-label">Code postal *</label>
                            <input type="text" class="form-control" id="edit_code_postal" name="code_postal" required>
                        </div>
                        <div class="col-md-8">
                            <label for="edit_ville" class="form-label">Ville *</label>
                            <input type="text" class="form-control" id="edit_ville" name="ville" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_latitude" class="form-label">Latitude</label>
                            <input type="number" step="0.000001" class="form-control" id="edit_latitude" name="latitude">
                        </div>
                        <div class="col-md-6">
                            <label for="edit_longitude" class="form-label">Longitude</label>
                            <input type="number" step="0.000001" class="form-control" id="edit_longitude" name="longitude">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="edit_places_totales" class="form-label">Nombre de places</label>
                        <input type="number" class="form-control" id="edit_places_totales" name="places_totales" min="1">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Station Modal -->
<div class="modal fade" id="deleteStationModal" tabindex="-1" aria-labelledby="deleteStationModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteStationModalLabel">Supprimer la station</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Êtes-vous sûr de vouloir supprimer la station <strong id="delete_station_name"></strong> ?</p>
                <p class="text-danger">Cette action est irréversible et supprimera toutes les données associées à cette station.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <form action="stations.php" method="post" class="d-inline">
                    <input type="hidden" name="action" value="delete_station">
                    <input type="hidden" name="station_id" id="delete_station_id">
                    <button type="submit" class="btn btn-danger">Supprimer</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Station search functionality
    const stationSearch = document.getElementById('stationSearch');
    if (stationSearch) {
        stationSearch.addEventListener('keyup', function() {
            const searchValue = this.value.toLowerCase();
            const rows = document.querySelectorAll('#stationsTableBody tr');
            
            rows.forEach(row => {
                const name = row.cells[0].textContent.toLowerCase();
                const address = row.cells[1].textContent.toLowerCase();
                const city = row.cells[2].textContent.toLowerCase();
                
                if (name.includes(searchValue) || address.includes(searchValue) || city.includes(searchValue)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    }
    
    // Edit station functionality
    const editButtons = document.querySelectorAll('.edit-station-btn');
    editButtons.forEach(button => {
        button.addEventListener('click', function() {
            const stationId = this.getAttribute('data-station-id');
            // In a real app, you would fetch the station data via AJAX
            // For now, we'll use the data already in the table
            const row = this.closest('tr');
            
            document.getElementById('edit_station_id').value = stationId;
            document.getElementById('edit_nom_station').value = row.cells[0].textContent.trim();
            document.getElementById('edit_adresse').value = row.cells[1].textContent.trim();
            
            // Parse city and postal code
            const cityWithCP = row.cells[2].textContent.trim();
            const cpMatch = cityWithCP.match(/^(\d+)\s+(.+)$/);
            if (cpMatch) {
                document.getElementById('edit_code_postal').value = cpMatch[1];
                document.getElementById('edit_ville').value = cpMatch[2];
            } else {
                document.getElementById('edit_code_postal').value = '';
                document.getElementById('edit_ville').value = cityWithCP;
            }
            
            // Parse bornes
            const bornesText = row.cells[3].textContent.trim();
            const placesMatch = bornesText.match(/\d+\s*\/\s*(\d+)/);
            if (placesMatch) {
                document.getElementById('edit_places_totales').value = placesMatch[1];
            } else {
                document.getElementById('edit_places_totales').value = '1';
            }
            
            // Set status
            const statusBadge = row.cells[4].querySelector('.badge');
            const statusText = statusBadge.textContent.trim().toLowerCase();
            let statusValue = 'active';
            
            switch (statusText) {
                case 'maintenance':
                    statusValue = 'maintenance';
                    break;
                case 'hors service':
                    statusValue = 'hors service';
                    break;
            }
            
            document.getElementById('edit_statut').value = statusValue;
        });
    });
    
    // Delete station functionality
    const deleteButtons = document.querySelectorAll('.delete-station-btn');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function() {
            const stationId = this.getAttribute('data-station-id');
            const stationName = this.getAttribute('data-station-name');
            
            document.getElementById('delete_station_id').value = stationId;
            document.getElementById('delete_station_name').textContent = stationName;
        });
    });
    
    // Initialize map (if Leaflet is available)
    if (typeof L !== 'undefined') {
        const map = L.map('stationMap').setView([46.603354, 1.888334], 5);
        
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(map);
        
        // Add markers for each station
        <?php foreach ($stations as $station): ?>
            <?php if (isset($station['localisation']) && isset($station['localisation']['coordinates'])): ?>
                <?php
                    $longitude = $station['localisation']['coordinates'][0] ?? 0;
                    $latitude = $station['localisation']['coordinates'][1] ?? 0;
                    
                    if ($latitude != 0 && $longitude != 0):
                ?>
                    L.marker([<?php echo $latitude; ?>, <?php echo $longitude; ?>])
                        .addTo(map)
                        .bindPopup(
                            "<strong><?php echo htmlspecialchars($station['nom_station'] ?? 'Station'); ?></strong><br>" +
                            "<?php echo htmlspecialchars($station['adresse'] ?? ''); ?><br>" +
                            "<?php echo htmlspecialchars($station['code_postal'] ?? ''); ?> <?php echo htmlspecialchars($station['ville'] ?? ''); ?><br>" +
                            "Bornes: <?php echo isset($stationBorneCount[(string)$station['_id']]) ? $stationBorneCount[(string)$station['_id']] : '0'; ?> / <?php echo $station['places_totales'] ?? '0'; ?>"
                        );
                <?php endif; ?>
            <?php endif; ?>
        <?php endforeach; ?>
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>