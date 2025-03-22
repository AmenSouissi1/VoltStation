<?php
require_once '../../config.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../../auth/login.php');
    exit;
}

$pageTitle = "Gestion des Sessions";
$error = '';

// Get period filter from URL parameter
$period = isset($_GET['period']) ? $_GET['period'] : 'today';
$startDate = null;
$endDate = null;

switch ($period) {
    case 'today':
        $startDate = date('Y-m-d 00:00:00');
        $endDate = date('Y-m-d 23:59:59');
        break;
    case 'yesterday':
        $startDate = date('Y-m-d 00:00:00', strtotime('-1 day'));
        $endDate = date('Y-m-d 23:59:59', strtotime('-1 day'));
        break;
    case 'week':
        $startDate = date('Y-m-d 00:00:00', strtotime('monday this week'));
        $endDate = date('Y-m-d 23:59:59', strtotime('sunday this week'));
        break;
    case 'month':
        $startDate = date('Y-m-01 00:00:00');
        $endDate = date('Y-m-t 23:59:59');
        break;
    default:
        $period = 'today';
        $startDate = date('Y-m-d 00:00:00');
        $endDate = date('Y-m-d 23:59:59');
        break;
}

// Get custom date range
if ($period === 'custom' && isset($_GET['start_date']) && isset($_GET['end_date'])) {
    $startDate = $_GET['start_date'] . ' 00:00:00';
    $endDate = $_GET['end_date'] . ' 23:59:59';
}

// Get sessions from database
try {
    $db = connectDB();
    
    // Aggregate pipeline to join with user and borne data
    $sessions = $db->sessions->aggregate([
        [
            '$match' => [
                'date_debut' => [
                    '$gte' => new MongoDB\BSON\UTCDateTime(strtotime($startDate) * 1000),
                    '$lte' => new MongoDB\BSON\UTCDateTime(strtotime($endDate) * 1000)
                ]
            ]
        ],
        [
            '$lookup' => [
                'from' => 'utilisateurs',
                'localField' => 'utilisateur_id',
                'foreignField' => '_id',
                'as' => 'utilisateur'
            ]
        ],
        [
            '$lookup' => [
                'from' => 'bornes',
                'localField' => 'borne_id',
                'foreignField' => '_id',
                'as' => 'borne'
            ]
        ],
        [
            '$unwind' => [
                'path' => '$utilisateur',
                'preserveNullAndEmptyArrays' => true
            ]
        ],
        [
            '$unwind' => [
                'path' => '$borne',
                'preserveNullAndEmptyArrays' => true
            ]
        ],
        [
            '$lookup' => [
                'from' => 'stations',
                'localField' => 'borne.station_id',
                'foreignField' => '_id',
                'as' => 'station'
            ]
        ],
        [
            '$unwind' => [
                'path' => '$station',
                'preserveNullAndEmptyArrays' => true
            ]
        ],
        [
            '$sort' => [
                'date_debut' => -1
            ]
        ]
    ])->toArray();
    
    // Calculate total statistics
    $totalSessions = count($sessions);
    $totalEnergy = 0;
    $totalRevenue = 0;
    $totalDuration = 0;
    
    foreach ($sessions as $session) {
        $totalEnergy += $session['energie_consommee'] ?? 0;
        $totalRevenue += $session['cout'] ?? 0;
        
        // Calculate duration in minutes
        if (isset($session['date_debut']) && isset($session['date_fin'])) {
            $startTime = $session['date_debut']->toDateTime();
            $endTime = $session['date_fin']->toDateTime();
            $duration = ($endTime->getTimestamp() - $startTime->getTimestamp()) / 60;  // in minutes
            $totalDuration += $duration;
        }
    }
    
    // Calculate average duration
    $avgDuration = $totalSessions > 0 ? $totalDuration / $totalSessions : 0;
    
} catch (Exception $e) {
    $error = 'Erreur de connexion à la base de données: ' . $e->getMessage();
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
                <h1 class="h2">Gestion des Sessions</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <a href="sessions.php?period=today" class="btn btn-sm btn-outline-secondary <?php echo $period === 'today' ? 'active' : ''; ?>">Aujourd'hui</a>
                        <a href="sessions.php?period=yesterday" class="btn btn-sm btn-outline-secondary <?php echo $period === 'yesterday' ? 'active' : ''; ?>">Hier</a>
                        <a href="sessions.php?period=week" class="btn btn-sm btn-outline-secondary <?php echo $period === 'week' ? 'active' : ''; ?>">Cette semaine</a>
                        <a href="sessions.php?period=month" class="btn btn-sm btn-outline-secondary <?php echo $period === 'month' ? 'active' : ''; ?>">Ce mois</a>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="modal" data-bs-target="#dateRangeModal">
                        <i class="fas fa-calendar me-1"></i> Période personnalisée
                    </button>
                </div>
            </div>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <!-- Sessions Overview Cards -->
            <div class="row mb-4">
                <div class="col-md-3 mb-4">
                    <div class="card bg-primary text-white h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-uppercase">Sessions</h6>
                                    <h2 class="display-6"><?php echo $totalSessions; ?></h2>
                                </div>
                                <i class="fas fa-history fa-3x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3 mb-4">
                    <div class="card bg-success text-white h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-uppercase">Énergie</h6>
                                    <h2 class="display-6"><?php echo number_format($totalEnergy, 1); ?> kWh</h2>
                                </div>
                                <i class="fas fa-bolt fa-3x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3 mb-4">
                    <div class="card bg-warning text-white h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-uppercase">Revenus</h6>
                                    <h2 class="display-6"><?php echo number_format($totalRevenue, 2); ?> €</h2>
                                </div>
                                <i class="fas fa-euro-sign fa-3x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3 mb-4">
                    <div class="card bg-info text-white h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-uppercase">Durée moyenne</h6>
                                    <h2 class="display-6"><?php echo number_format($avgDuration, 0); ?> min</h2>
                                </div>
                                <i class="fas fa-clock fa-3x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Sessions Table -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Liste des sessions</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Utilisateur</th>
                                    <th>Station</th>
                                    <th>Borne</th>
                                    <th>Début</th>
                                    <th>Fin</th>
                                    <th>Durée</th>
                                    <th>Énergie</th>
                                    <th>Coût</th>
                                    <th>Statut</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sessions as $session): ?>
                                    <?php
                                        // Format dates
                                        $startDate = isset($session['date_debut']) ? $session['date_debut']->toDateTime() : null;
                                        $endDate = isset($session['date_fin']) ? $session['date_fin']->toDateTime() : null;
                                        
                                        // Calculate duration
                                        $duration = '';
                                        if ($startDate && $endDate) {
                                            $durationMinutes = ($endDate->getTimestamp() - $startDate->getTimestamp()) / 60;
                                            $duration = number_format($durationMinutes, 0) . ' min';
                                        }
                                        
                                        // Determine status badge class
                                        $statusClass = 'bg-success';
                                        $status = $session['statut'] ?? 'terminée';
                                        
                                        if ($status === 'en cours') {
                                            $statusClass = 'bg-primary';
                                        } elseif ($status === 'annulée') {
                                            $statusClass = 'bg-danger';
                                        }
                                    ?>
                                    <tr>
                                        <td><?php echo isset($session['_id']) ? $session['_id'] : 'N/A'; ?></td>
                                        <td>
                                            <?php 
                                                if (isset($session['utilisateur'])) {
                                                    echo htmlspecialchars($session['utilisateur']['prenom'] . ' ' . $session['utilisateur']['nom']);
                                                } else {
                                                    echo 'Utilisateur inconnu';
                                                }
                                            ?>
                                        </td>
                                        <td>
                                            <?php 
                                                if (isset($session['station']) && isset($session['station']['nom_station'])) {
                                                    echo htmlspecialchars($session['station']['nom_station']);
                                                } else {
                                                    echo 'Station inconnue';
                                                }
                                            ?>
                                        </td>
                                        <td>
                                            <?php 
                                                if (isset($session['borne']) && isset($session['borne']['numero'])) {
                                                    echo htmlspecialchars($session['borne']['numero']);
                                                } else {
                                                    echo 'Borne inconnue';
                                                }
                                            ?>
                                        </td>
                                        <td><?php echo $startDate ? $startDate->format('d/m/Y H:i') : 'N/A'; ?></td>
                                        <td><?php echo $endDate ? $endDate->format('d/m/Y H:i') : 'En cours'; ?></td>
                                        <td><?php echo $duration ?: 'En cours'; ?></td>
                                        <td><?php echo isset($session['energie_consommee']) ? number_format($session['energie_consommee'], 2) . ' kWh' : 'N/A'; ?></td>
                                        <td><?php echo isset($session['cout']) ? number_format($session['cout'], 2) . ' €' : 'N/A'; ?></td>
                                        <td><span class="badge <?php echo $statusClass; ?>"><?php echo isset($status) ? htmlspecialchars($status) : 'N/A'; ?></span></td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#viewSessionModal" 
                                                    data-session-id="<?php echo isset($session['_id']) ? $session['_id'] : 'N/A'; ?>"
                                                    data-user-name="<?php echo isset($session['utilisateur']) ? htmlspecialchars($session['utilisateur']['prenom'] . ' ' . $session['utilisateur']['nom']) : 'Utilisateur inconnu'; ?>"
                                                    data-station-name="<?php echo (isset($session['station']) && isset($session['station']['nom_station'])) ? htmlspecialchars($session['station']['nom_station']) : 'Station inconnue'; ?>"
                                                    data-borne-number="<?php echo (isset($session['borne']) && isset($session['borne']['numero'])) ? htmlspecialchars($session['borne']['numero']) : 'Borne inconnue'; ?>"
                                                    data-start-date="<?php echo $startDate ? $startDate->format('d/m/Y H:i') : 'N/A'; ?>"
                                                    data-end-date="<?php echo $endDate ? $endDate->format('d/m/Y H:i') : 'En cours'; ?>"
                                                    data-duration="<?php echo $duration ?: 'En cours'; ?>"
                                                    data-energy="<?php echo isset($session['energie_consommee']) ? number_format($session['energie_consommee'], 2) . ' kWh' : 'N/A'; ?>"
                                                    data-cost="<?php echo isset($session['cout']) ? number_format($session['cout'], 2) . ' €' : 'N/A'; ?>"
                                                    data-status="<?php echo isset($status) ? htmlspecialchars($status) : 'N/A'; ?>"
                                                    data-status-class="<?php echo $statusClass; ?>">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (count($sessions) === 0): ?>
                                    <tr>
                                        <td colspan="11" class="text-center">Aucune session trouvée pour cette période</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Date Range Modal -->
<div class="modal fade" id="dateRangeModal" tabindex="-1" aria-labelledby="dateRangeModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="dateRangeModalLabel">Sélectionner une période</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="sessions.php" method="get">
                <div class="modal-body">
                    <input type="hidden" name="period" value="custom">
                    
                    <div class="mb-3">
                        <label for="start_date" class="form-label">Date de début</label>
                        <input type="date" class="form-control" id="start_date" name="start_date" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="end_date" class="form-label">Date de fin</label>
                        <input type="date" class="form-control" id="end_date" name="end_date" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Appliquer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Session Modal -->
<div class="modal fade" id="viewSessionModal" tabindex="-1" aria-labelledby="viewSessionModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewSessionModalLabel">Détails de la session</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <table class="table table-bordered">
                    <tbody>
                        <tr>
                            <th>ID</th>
                            <td id="view_session_id"></td>
                        </tr>
                        <tr>
                            <th>Utilisateur</th>
                            <td id="view_user_name"></td>
                        </tr>
                        <tr>
                            <th>Station</th>
                            <td id="view_station_name"></td>
                        </tr>
                        <tr>
                            <th>Borne</th>
                            <td id="view_borne_number"></td>
                        </tr>
                        <tr>
                            <th>Début</th>
                            <td id="view_start_date"></td>
                        </tr>
                        <tr>
                            <th>Fin</th>
                            <td id="view_end_date"></td>
                        </tr>
                        <tr>
                            <th>Durée</th>
                            <td id="view_duration"></td>
                        </tr>
                        <tr>
                            <th>Énergie consommée</th>
                            <td id="view_energy"></td>
                        </tr>
                        <tr>
                            <th>Coût</th>
                            <td id="view_cost"></td>
                        </tr>
                        <tr>
                            <th>Statut</th>
                            <td id="view_status"></td>
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
    // Set default dates for custom date range
    const today = new Date();
    document.getElementById('start_date').valueAsDate = new Date(today.getFullYear(), today.getMonth(), 1); // First day of current month
    document.getElementById('end_date').valueAsDate = today;
    
    // View Session Modal
    const viewSessionModal = document.getElementById('viewSessionModal');
    viewSessionModal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        const sessionId = button.getAttribute('data-session-id');
        const userName = button.getAttribute('data-user-name');
        const stationName = button.getAttribute('data-station-name');
        const borneNumber = button.getAttribute('data-borne-number');
        const startDate = button.getAttribute('data-start-date');
        const endDate = button.getAttribute('data-end-date');
        const duration = button.getAttribute('data-duration');
        const energy = button.getAttribute('data-energy');
        const cost = button.getAttribute('data-cost');
        const status = button.getAttribute('data-status');
        const statusClass = button.getAttribute('data-status-class');
        
        const modal = this;
        modal.querySelector('#view_session_id').textContent = sessionId;
        modal.querySelector('#view_user_name').textContent = userName;
        modal.querySelector('#view_station_name').textContent = stationName;
        modal.querySelector('#view_borne_number').textContent = borneNumber;
        modal.querySelector('#view_start_date').textContent = startDate;
        modal.querySelector('#view_end_date').textContent = endDate;
        modal.querySelector('#view_duration').textContent = duration;
        modal.querySelector('#view_energy').textContent = energy;
        modal.querySelector('#view_cost').textContent = cost;
        modal.querySelector('#view_status').innerHTML = `<span class="badge ${statusClass}">${status}</span>`;
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>