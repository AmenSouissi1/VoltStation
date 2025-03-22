<?php
require_once '../../config.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../../auth/login.php');
    exit;
}

$pageTitle = "Gestion des Bornes";
$success = '';
$error = '';

// Get stations from database
try {
    $db = connectDB();
    $stations = $db->stations->find()->toArray();
} catch (Exception $e) {
    $error = 'Erreur de connexion à la base de données: ' . $e->getMessage();
}

// Handle form submission for adding/updating bornes
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'add_borne') {
        $station_id = $_POST['station_id'] ?? '';
        $numero = $_POST['numero'] ?? '';
        $type = $_POST['type'] ?? '';
        $puissance = $_POST['puissance'] ?? 0;
        $connecteur = $_POST['connecteur'] ?? '';
        $statut = $_POST['statut'] ?? 'disponible';
        
        if (empty($station_id) || empty($numero) || empty($type)) {
            $error = 'Veuillez remplir tous les champs obligatoires.';
        } else {
            try {
                // Create new borne
                $newBorne = [
                    'station_id' => new MongoDB\BSON\ObjectId($station_id),
                    'numero' => $numero,
                    'type' => $type,
                    'puissance' => (float)$puissance,
                    'connecteur' => $connecteur,
                    'statut' => $statut,
                    'date_installation' => new MongoDB\BSON\UTCDateTime(time() * 1000)
                ];
                
                $result = $db->bornes->insertOne($newBorne);
                
                if ($result->getInsertedCount()) {
                    $success = 'Borne ajoutée avec succès!';
                } else {
                    $error = 'Erreur lors de l\'ajout de la borne.';
                }
            } catch (Exception $e) {
                $error = 'Erreur: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'update_borne') {
        $borne_id = $_POST['borne_id'] ?? '';
        $statut = $_POST['statut'] ?? '';
        $dernier_entretien = $_POST['dernier_entretien'] ?? '';
        
        if (empty($borne_id) || empty($statut)) {
            $error = 'Informations incomplètes pour la mise à jour.';
        } else {
            try {
                $updateData = [
                    'statut' => $statut
                ];
                
                if (!empty($dernier_entretien)) {
                    $updateData['dernier_entretien'] = new MongoDB\BSON\UTCDateTime(strtotime($dernier_entretien) * 1000);
                }
                
                $result = $db->bornes->updateOne(
                    ['_id' => new MongoDB\BSON\ObjectId($borne_id)],
                    ['$set' => $updateData]
                );
                
                if ($result->getModifiedCount()) {
                    $success = 'Borne mise à jour avec succès!';
                } else {
                    $error = 'Aucune modification n\'a été apportée.';
                }
            } catch (Exception $e) {
                $error = 'Erreur: ' . $e->getMessage();
            }
        }
    }
}

// Get all bornes with station info
try {
    $bornes = $db->bornes->aggregate([
        [
            '$lookup' => [
                'from' => 'stations',
                'localField' => 'station_id',
                'foreignField' => '_id',
                'as' => 'station'
            ]
        ],
        [
            '$unwind' => '$station'
        ],
        [
            '$sort' => [
                'station.nom_station' => 1,
                'numero' => 1
            ]
        ]
    ])->toArray();
} catch (Exception $e) {
    $error = 'Erreur lors de la récupération des bornes: ' . $e->getMessage();
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
                <h1 class="h2">Gestion des Bornes</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addBorneModal">
                        <i class="fas fa-plus me-1"></i> Ajouter une borne
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
            
            <!-- Bornes Overview Cards -->
            <div class="row mb-4">
                <div class="col-md-3 mb-4">
                    <div class="card bg-primary text-white h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-uppercase">Total Bornes</h6>
                                    <h2 class="display-6"><?php echo count($bornes); ?></h2>
                                </div>
                                <i class="fas fa-charging-station fa-3x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3 mb-4">
                    <div class="card bg-success text-white h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-uppercase">Disponibles</h6>
                                    <h2 class="display-6">
                                        <?php 
                                            $availableBornes = array_filter($bornes, function($borne) {
                                                return $borne['statut'] === 'disponible';
                                            });
                                            echo count($availableBornes);
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
                                            $maintenanceBornes = array_filter($bornes, function($borne) {
                                                return $borne['statut'] === 'en maintenance';
                                            });
                                            echo count($maintenanceBornes);
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
                                            $offlineBornes = array_filter($bornes, function($borne) {
                                                return $borne['statut'] === 'hors service';
                                            });
                                            echo count($offlineBornes);
                                        ?>
                                    </h2>
                                </div>
                                <i class="fas fa-times-circle fa-3x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Bornes Table -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Liste des bornes</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Station</th>
                                    <th>Numéro</th>
                                    <th>Type</th>
                                    <th>Puissance</th>
                                    <th>Connecteur</th>
                                    <th>Statut</th>
                                    <th>Dernier entretien</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($bornes as $borne): ?>
                                    <?php
                                        // Determine status badge class
                                        $statusClass = 'bg-success';
                                        switch ($borne['statut']) {
                                            case 'en cours d\'utilisation':
                                                $statusClass = 'bg-primary';
                                                break;
                                            case 'en maintenance':
                                                $statusClass = 'bg-warning';
                                                break;
                                            case 'hors service':
                                                $statusClass = 'bg-danger';
                                                break;
                                        }
                                    ?>
                                    <tr>
                                        <td><?php echo isset($borne['_id']) ? $borne['_id'] : 'N/A'; ?></td>
                                        <td><?php echo isset($borne['station']['nom_station']) ? htmlspecialchars($borne['station']['nom_station']) : 'N/A'; ?></td>
                                        <td><?php echo isset($borne['numero']) ? htmlspecialchars($borne['numero']) : 'N/A'; ?></td>
                                        <td><?php echo isset($borne['type']) ? htmlspecialchars($borne['type']) : 'N/A'; ?></td>
                                        <td><?php echo isset($borne['puissance']) ? htmlspecialchars($borne['puissance']) . ' kW' : 'N/A'; ?></td>
                                        <td><?php echo isset($borne['connecteur']) ? htmlspecialchars($borne['connecteur']) : 'N/A'; ?></td>
                                        <td><span class="badge <?php echo $statusClass; ?>"><?php echo isset($borne['statut']) ? htmlspecialchars($borne['statut']) : 'N/A'; ?></span></td>
                                        <td>
                                            <?php 
                                                echo isset($borne['dernier_entretien']) 
                                                    ? date('d/m/Y', $borne['dernier_entretien']->toDateTime()->getTimestamp()) 
                                                    : 'Jamais';
                                            ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#editBorneModal" 
                                                        data-borne-id="<?php echo isset($borne['_id']) ? $borne['_id'] : ''; ?>"
                                                        data-borne-numero="<?php echo isset($borne['numero']) ? htmlspecialchars($borne['numero']) : ''; ?>"
                                                        data-borne-station="<?php echo (isset($borne['station']) && isset($borne['station']['nom_station'])) ? htmlspecialchars($borne['station']['nom_station']) : ''; ?>"
                                                        data-borne-statut="<?php echo isset($borne['statut']) ? htmlspecialchars($borne['statut']) : ''; ?>">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button type="button" class="btn btn-info" data-bs-toggle="modal" data-bs-target="#viewBorneModal"
                                                        data-borne-id="<?php echo isset($borne['_id']) ? $borne['_id'] : ''; ?>"
                                                        data-borne-numero="<?php echo isset($borne['numero']) ? htmlspecialchars($borne['numero']) : ''; ?>"
                                                        data-borne-station="<?php echo (isset($borne['station']) && isset($borne['station']['nom_station'])) ? htmlspecialchars($borne['station']['nom_station']) : ''; ?>"
                                                        data-borne-type="<?php echo isset($borne['type']) ? htmlspecialchars($borne['type']) : ''; ?>"
                                                        data-borne-puissance="<?php echo isset($borne['puissance']) ? htmlspecialchars($borne['puissance']) : ''; ?>"
                                                        data-borne-connecteur="<?php echo isset($borne['connecteur']) ? htmlspecialchars($borne['connecteur']) : 'N/A'; ?>"
                                                        data-borne-statut="<?php echo isset($borne['statut']) ? htmlspecialchars($borne['statut']) : ''; ?>"
                                                        data-borne-installation="<?php echo isset($borne['date_installation']) ? date('d/m/Y', $borne['date_installation']->toDateTime()->getTimestamp()) : 'N/A'; ?>"
                                                        data-borne-entretien="<?php echo isset($borne['dernier_entretien']) ? date('d/m/Y', $borne['dernier_entretien']->toDateTime()->getTimestamp()) : 'Jamais'; ?>">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </div>
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

<!-- Add Borne Modal -->
<div class="modal fade" id="addBorneModal" tabindex="-1" aria-labelledby="addBorneModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addBorneModalLabel">Ajouter une nouvelle borne</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="bornes.php" method="post">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_borne">
                    
                    <div class="mb-3">
                        <label for="station_id" class="form-label">Station</label>
                        <select class="form-select" id="station_id" name="station_id" required>
                            <option value="">Sélectionnez une station</option>
                            <?php foreach ($stations as $station): ?>
                                <option value="<?php echo isset($station['_id']) ? $station['_id'] : ''; ?>">
                                    <?php echo isset($station['nom_station']) ? htmlspecialchars($station['nom_station']) : 'Station sans nom'; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="numero" class="form-label">Numéro de borne</label>
                        <input type="text" class="form-control" id="numero" name="numero" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="type" class="form-label">Type de borne</label>
                        <select class="form-select" id="type" name="type" required>
                            <option value="">Sélectionnez un type</option>
                            <option value="standard">Standard</option>
                            <option value="charge rapide">Charge rapide</option>
                            <option value="ultra-rapide">Ultra-rapide</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="puissance" class="form-label">Puissance (kW)</label>
                        <input type="number" class="form-control" id="puissance" name="puissance" min="3" step="0.1" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="connecteur" class="form-label">Type de connecteur</label>
                        <select class="form-select" id="connecteur" name="connecteur" required>
                            <option value="">Sélectionnez un connecteur</option>
                            <option value="Type 2">Type 2</option>
                            <option value="CCS">CCS</option>
                            <option value="CHAdeMO">CHAdeMO</option>
                            <option value="Combo CCS">Combo CCS</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="statut" class="form-label">Statut initial</label>
                        <select class="form-select" id="statut" name="statut" required>
                            <option value="disponible">Disponible</option>
                            <option value="en maintenance">En maintenance</option>
                            <option value="hors service">Hors service</option>
                        </select>
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

<!-- Edit Borne Modal -->
<div class="modal fade" id="editBorneModal" tabindex="-1" aria-labelledby="editBorneModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editBorneModalLabel">Modifier la borne</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="bornes.php" method="post">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_borne">
                    <input type="hidden" name="borne_id" id="edit_borne_id">
                    
                    <div class="mb-3">
                        <label class="form-label">Station</label>
                        <p class="form-control-static" id="edit_station_name"></p>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Numéro de borne</label>
                        <p class="form-control-static" id="edit_borne_numero"></p>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_statut" class="form-label">Statut</label>
                        <select class="form-select" id="edit_statut" name="statut" required>
                            <option value="disponible">Disponible</option>
                            <option value="en cours d'utilisation">En cours d'utilisation</option>
                            <option value="en maintenance">En maintenance</option>
                            <option value="hors service">Hors service</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="dernier_entretien" class="form-label">Date du dernier entretien</label>
                        <input type="date" class="form-control" id="dernier_entretien" name="dernier_entretien">
                        <small class="form-text text-muted">Laissez vide si aucun entretien n'a été effectué</small>
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

<!-- View Borne Modal -->
<div class="modal fade" id="viewBorneModal" tabindex="-1" aria-labelledby="viewBorneModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewBorneModalLabel">Détails de la borne</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <table class="table table-bordered">
                    <tbody>
                        <tr>
                            <th>ID</th>
                            <td id="view_borne_id"></td>
                        </tr>
                        <tr>
                            <th>Station</th>
                            <td id="view_station_name"></td>
                        </tr>
                        <tr>
                            <th>Numéro</th>
                            <td id="view_borne_numero"></td>
                        </tr>
                        <tr>
                            <th>Type</th>
                            <td id="view_borne_type"></td>
                        </tr>
                        <tr>
                            <th>Puissance</th>
                            <td id="view_borne_puissance"></td>
                        </tr>
                        <tr>
                            <th>Connecteur</th>
                            <td id="view_borne_connecteur"></td>
                        </tr>
                        <tr>
                            <th>Statut</th>
                            <td id="view_borne_statut"></td>
                        </tr>
                        <tr>
                            <th>Date d'installation</th>
                            <td id="view_borne_installation"></td>
                        </tr>
                        <tr>
                            <th>Dernier entretien</th>
                            <td id="view_borne_entretien"></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Edit Borne Modal
    const editBorneModal = document.getElementById('editBorneModal');
    editBorneModal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        const borneId = button.getAttribute('data-borne-id');
        const borneNumero = button.getAttribute('data-borne-numero');
        const borneStation = button.getAttribute('data-borne-station');
        const borneStatut = button.getAttribute('data-borne-statut');
        
        const modal = this;
        modal.querySelector('#edit_borne_id').value = borneId;
        modal.querySelector('#edit_station_name').textContent = borneStation;
        modal.querySelector('#edit_borne_numero').textContent = borneNumero;
        modal.querySelector('#edit_statut').value = borneStatut;
    });
    
    // View Borne Modal
    const viewBorneModal = document.getElementById('viewBorneModal');
    viewBorneModal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        const borneId = button.getAttribute('data-borne-id');
        const borneNumero = button.getAttribute('data-borne-numero');
        const borneStation = button.getAttribute('data-borne-station');
        const borneType = button.getAttribute('data-borne-type');
        const bornePuissance = button.getAttribute('data-borne-puissance');
        const borneConnecteur = button.getAttribute('data-borne-connecteur');
        const borneStatut = button.getAttribute('data-borne-statut');
        const borneInstallation = button.getAttribute('data-borne-installation');
        const borneEntretien = button.getAttribute('data-borne-entretien');
        
        const modal = this;
        modal.querySelector('#view_borne_id').textContent = borneId;
        modal.querySelector('#view_station_name').textContent = borneStation;
        modal.querySelector('#view_borne_numero').textContent = borneNumero;
        modal.querySelector('#view_borne_type').textContent = borneType;
        modal.querySelector('#view_borne_puissance').textContent = bornePuissance + ' kW';
        modal.querySelector('#view_borne_connecteur').textContent = borneConnecteur;
        
        // Apply status badge
        let statusBadgeClass = 'bg-success';
        if (borneStatut === 'en cours d\'utilisation') {
            statusBadgeClass = 'bg-primary';
        } else if (borneStatut === 'en maintenance') {
            statusBadgeClass = 'bg-warning';
        } else if (borneStatut === 'hors service') {
            statusBadgeClass = 'bg-danger';
        }
        
        modal.querySelector('#view_borne_statut').innerHTML = `<span class="badge ${statusBadgeClass}">${borneStatut}</span>`;
        modal.querySelector('#view_borne_installation').textContent = borneInstallation;
        modal.querySelector('#view_borne_entretien').textContent = borneEntretien;
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>