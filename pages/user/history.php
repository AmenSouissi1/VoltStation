<?php
require_once '../../config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../auth/login.php');
    exit;
}

$pageTitle = "Historique des sessions";

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
    
    // Get pagination parameters
    $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
    $limit = 10; // Number of sessions per page
    $skip = ($page - 1) * $limit;
    
    // Get filter parameters
    $dateFilter = isset($_GET['date']) ? $_GET['date'] : '';
    $stationFilter = isset($_GET['station']) ? $_GET['station'] : '';
    
    // Prepare the filter
    $filter = ['utilisateur_id' => new MongoDB\BSON\ObjectId($user_id)];
    
    // Add date filter if provided
    if (!empty($dateFilter)) {
        // Convert date to MongoDB query
        list($month, $year) = explode('/', $dateFilter);
        $startDate = new MongoDB\BSON\UTCDateTime(strtotime("$year-$month-01 00:00:00") * 1000);
        
        // Calculate end date (first day of next month)
        $endMonth = $month == 12 ? 1 : $month + 1;
        $endYear = $month == 12 ? $year + 1 : $year;
        $endDate = new MongoDB\BSON\UTCDateTime(strtotime("$endYear-$endMonth-01 00:00:00") * 1000);
        
        $filter['date_debut'] = [
            '$gte' => $startDate,
            '$lt' => $endDate
        ];
    }
    
    // Add station filter if provided
    if (!empty($stationFilter)) {
        // Get the station ID
        $station = $db->stations->findOne(['nom' => $stationFilter]);
        if ($station) {
            // Get bornes in this station
            $bornes = $db->bornes->find(['station_id' => $station['_id']])->toArray();
            $borneIds = array_map(function($borne) { 
                return $borne['_id']; 
            }, $bornes);
            
            if (!empty($borneIds)) {
                $filter['borne_id'] = ['$in' => $borneIds];
            }
        }
    }
    
    // Get user's sessions from database with pagination
    $sessionsCollection = $db->sessions;
    $userSessions = $sessionsCollection->find(
        $filter,
        [
            'sort' => ['date_debut' => -1],
            'skip' => $skip,
            'limit' => $limit
        ]
    )->toArray();
    
    // Get total number of sessions for pagination
    $totalSessions = $sessionsCollection->countDocuments($filter);
    $totalPages = ceil($totalSessions / $limit);
    
    // Get all stations for the filter dropdown
    $stations = $db->stations->find([], ['sort' => ['nom' => 1]])->toArray();
    
    // Calculate total statistics
    $totalEnergy = 0;
    $totalCost = 0;
    $totalDuration = 0;
    
    $allUserSessions = $sessionsCollection->find(
        ['utilisateur_id' => new MongoDB\BSON\ObjectId($user_id)]
    )->toArray();
    
    foreach ($allUserSessions as $session) {
        $totalEnergy += $session['energie_consommee'] ?? 0;
        $totalCost += $session['cout'] ?? 0;
        
        // Calculate duration if both start and end dates are available
        if (isset($session['date_debut']) && isset($session['date_fin'])) {
            $start = $session['date_debut'] instanceof MongoDB\BSON\UTCDateTime 
                ? $session['date_debut']->toDateTime() 
                : new DateTime($session['date_debut']);
                
            $end = $session['date_fin'] instanceof MongoDB\BSON\UTCDateTime 
                ? $session['date_fin']->toDateTime() 
                : new DateTime($session['date_fin']);
                
            $duration = $end->getTimestamp() - $start->getTimestamp();
            $totalDuration += $duration;
        }
    }
    
    // Format total duration in hours and minutes
    $totalHours = floor($totalDuration / 3600);
    $totalMinutes = floor(($totalDuration % 3600) / 60);
    
} catch (Exception $e) {
    // Log the error but don't expose details to the user
    error_log("Database error: " . $e->getMessage());
    
    // Provide fallback data
    $userSessions = [];
    $totalSessions = 0;
    $totalPages = 1;
    $page = 1;
    $stations = [];
    $totalEnergy = 0;
    $totalCost = 0;
    $totalHours = 0;
    $totalMinutes = 0;
}

// Include header
require_once '../../includes/header.php';
?>

<main class="container mt-4">
    <div class="row">
        <?php include_once '../../includes/user_sidebar.php'; ?>
        
        <!-- Main content -->
        <div class="col-md-9">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Historique des sessions</h5>
                </div>
                <div class="card-body">
                    <!-- Statistics summary -->
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h6 class="card-title">Énergie totale</h6>
                                    <h3 class="text-primary"><?php echo number_format($totalEnergy, 1); ?> kWh</h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h6 class="card-title">Coût total</h6>
                                    <h3 class="text-primary"><?php echo number_format($totalCost, 2); ?> €</h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h6 class="card-title">Temps total</h6>
                                    <h3 class="text-primary"><?php echo $totalHours; ?>h <?php echo $totalMinutes; ?>m</h3>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Filters -->
                    <form class="mb-4" method="get">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="date">Mois</label>
                                    <input type="month" class="form-control" id="date" name="date" 
                                        value="<?php echo !empty($dateFilter) ? str_replace('/', '-', $dateFilter) : ''; ?>"
                                        onchange="this.form.submit()">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="station">Station</label>
                                    <select class="form-control" id="station" name="station" onchange="this.form.submit()">
                                        <option value="">Toutes les stations</option>
                                        <?php foreach ($stations as $station): ?>
                                            <option value="<?php echo htmlspecialchars($station['nom']); ?>"
                                                <?php echo ($stationFilter === $station['nom']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($station['nom']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <a href="history.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-sync-alt"></i> Réinitialiser
                                </a>
                            </div>
                        </div>
                    </form>
                    
                    <!-- Sessions Table -->
                    <?php if (empty($userSessions)): ?>
                        <div class="alert alert-info">
                            Aucune session trouvée pour les critères sélectionnés.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Station</th>
                                        <th>Borne</th>
                                        <th>Durée</th>
                                        <th>Énergie</th>
                                        <th>Coût</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($userSessions as $session): ?>
                                        <?php
                                        // Get station and borne details
                                        $stationName = '';
                                        $borneNumber = '';
                                        
                                        if (isset($session['borne_id']) && !empty($session['borne_id'])) {
                                            $borne = $db->bornes->findOne(['_id' => $session['borne_id']]);
                                            if ($borne) {
                                                $borneNumber = $borne['numero'];
                                                
                                                // Get station details
                                                if (isset($borne['station_id']) && !empty($borne['station_id'])) {
                                                    $station = $db->stations->findOne(['_id' => $borne['station_id']]);
                                                    $stationName = $station ? $station['nom'] : 'Station inconnue';
                                                }
                                            }
                                        }
                                        
                                        // Format date and duration
                                        $startDateTime = $session['date_debut'] instanceof MongoDB\BSON\UTCDateTime 
                                            ? $session['date_debut']->toDateTime() 
                                            : new DateTime($session['date_debut']);
                                            
                                        $endDateTime = isset($session['date_fin']) && $session['date_fin']
                                            ? ($session['date_fin'] instanceof MongoDB\BSON\UTCDateTime 
                                                ? $session['date_fin']->toDateTime() 
                                                : new DateTime($session['date_fin']))
                                            : null;
                                                
                                        $formattedDate = $startDateTime->format('d/m/Y');
                                        
                                        // Calculate duration
                                        $duration = 'En cours';
                                        if ($endDateTime) {
                                            $durationSeconds = $endDateTime->getTimestamp() - $startDateTime->getTimestamp();
                                            $hours = floor($durationSeconds / 3600);
                                            $minutes = floor(($durationSeconds % 3600) / 60);
                                            $duration = $hours . 'h ' . $minutes . 'm';
                                        }
                                        ?>
                                        <tr>
                                            <td><?php echo $formattedDate; ?></td>
                                            <td><?php echo htmlspecialchars($stationName); ?></td>
                                            <td><?php echo htmlspecialchars($borneNumber); ?></td>
                                            <td><?php echo $duration; ?></td>
                                            <td><?php echo number_format($session['energie_consommee'] ?? 0, 2); ?> kWh</td>
                                            <td><?php echo number_format($session['cout'] ?? 0, 2); ?> €</td>
                                            <td>
                                                <a href="session-details.php?id=<?php echo htmlspecialchars((string)$session['_id']); ?>" class="btn btn-sm btn-primary">
                                                    <i class="fas fa-eye"></i> Détails
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if ($totalPages > 1): ?>
                            <nav aria-label="Sessions pagination" class="mt-4">
                                <ul class="pagination justify-content-center">
                                    <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo !empty($dateFilter) ? '&date=' . urlencode($dateFilter) : ''; ?><?php echo !empty($stationFilter) ? '&station=' . urlencode($stationFilter) : ''; ?>" aria-label="Previous">
                                            <span aria-hidden="true">&laquo;</span>
                                        </a>
                                    </li>
                                    
                                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                        <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($dateFilter) ? '&date=' . urlencode($dateFilter) : ''; ?><?php echo !empty($stationFilter) ? '&station=' . urlencode($stationFilter) : ''; ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo !empty($dateFilter) ? '&date=' . urlencode($dateFilter) : ''; ?><?php echo !empty($stationFilter) ? '&station=' . urlencode($stationFilter) : ''; ?>" aria-label="Next">
                                            <span aria-hidden="true">&raquo;</span>
                                        </a>
                                    </li>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<?php require_once '../../includes/footer.php'; ?>