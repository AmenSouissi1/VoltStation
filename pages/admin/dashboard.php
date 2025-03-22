<?php
require_once '../../config.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../../auth/login.php');
    exit;
}

$pageTitle = "Tableau de Bord - Administration";
$error = '';

try {
    // Connect to database
    $db = connectDB();
    
    // Initialize stats array
    $stats = [];
    
    // Stations stats
    $stations = $db->stations->find()->toArray();
    $stats['stations_total'] = count($stations);
    
    $stations_active = $db->stations->countDocuments(['statut' => 'actif']);
    $stats['stations_active'] = $stations_active;
    
    $stations_maintenance = $db->stations->countDocuments(['statut' => 'maintenance']);
    $stats['stations_maintenance'] = $stations_maintenance;
    
    $stations_offline = $db->stations->countDocuments(['statut' => 'hors service']);
    $stats['stations_offline'] = $stations_offline;
    
    // Bornes stats
    $bornes = $db->bornes->find()->toArray();
    $stats['bornes_total'] = count($bornes);
    
    $bornes_available = $db->bornes->countDocuments(['statut' => 'disponible']);
    $stats['bornes_available'] = $bornes_available;
    
    $bornes_in_use = $db->bornes->countDocuments(['statut' => 'en charge']);
    $stats['bornes_in_use'] = $bornes_in_use;
    
    $bornes_maintenance = $db->bornes->countDocuments(['statut' => 'hors service']);
    $stats['bornes_maintenance'] = $bornes_maintenance;
    
    // Users stats
    $users = $db->utilisateurs->find()->toArray();
    $stats['users_total'] = count($users);
    
    // Get recent sessions
    $today = new MongoDB\BSON\UTCDateTime(strtotime('today') * 1000);
    
    // Count today's sessions
    $sessionsToday = $db->sessions->countDocuments([
        'date_debut' => ['$gte' => $today]
    ]);
    $stats['sessions_today'] = $sessionsToday;
    
    // Get session totals for different periods
    $lastWeek = new MongoDB\BSON\UTCDateTime(strtotime('-7 days') * 1000);
    $lastMonth = new MongoDB\BSON\UTCDateTime(strtotime('-30 days') * 1000);
    
    $sessionsWeek = $db->sessions->countDocuments([
        'date_debut' => ['$gte' => $lastWeek]
    ]);
    $stats['sessions_week'] = $sessionsWeek;
    
    $sessionsMonth = $db->sessions->countDocuments([
        'date_debut' => ['$gte' => $lastMonth]
    ]);
    $stats['sessions_month'] = $sessionsMonth;
    
    // Calculate energy delivered and revenue
    $energyPipeline = [
        ['$match' => ['statut' => 'terminée']],
        ['$group' => [
            '_id' => null,
            'total_energy' => ['$sum' => '$energie_consommee'],
            'total_revenue' => ['$sum' => '$cout']
        ]]
    ];
    
    // Energy and revenue today
    $energyTodayPipeline = $energyPipeline;
    $energyTodayPipeline[0]['$match']['date_debut'] = ['$gte' => $today];
    $energyTodayResult = $db->sessions->aggregate($energyTodayPipeline)->toArray();
    
    $stats['energy_today'] = !empty($energyTodayResult) ? $energyTodayResult[0]['total_energy'] ?? 0 : 0;
    $stats['revenue_today'] = !empty($energyTodayResult) ? $energyTodayResult[0]['total_revenue'] ?? 0 : 0;
    
    // Energy and revenue this week
    $energyWeekPipeline = $energyPipeline;
    $energyWeekPipeline[0]['$match']['date_debut'] = ['$gte' => $lastWeek];
    $energyWeekResult = $db->sessions->aggregate($energyWeekPipeline)->toArray();
    
    $stats['energy_week'] = !empty($energyWeekResult) ? $energyWeekResult[0]['total_energy'] ?? 0 : 0;
    $stats['revenue_week'] = !empty($energyWeekResult) ? $energyWeekResult[0]['total_revenue'] ?? 0 : 0;
    
    // Energy and revenue this month
    $energyMonthPipeline = $energyPipeline;
    $energyMonthPipeline[0]['$match']['date_debut'] = ['$gte' => $lastMonth];
    $energyMonthResult = $db->sessions->aggregate($energyMonthPipeline)->toArray();
    
    $stats['energy_month'] = !empty($energyMonthResult) ? $energyMonthResult[0]['total_energy'] ?? 0 : 0;
    $stats['revenue_month'] = !empty($energyMonthResult) ? $energyMonthResult[0]['total_revenue'] ?? 0 : 0;
    
    // Get recent sessions for the table - with user and station information
    // Simplifions l'approche et utilisons des requêtes plus basiques
    $recentSessionsData = $db->sessions->find(
        [],
        [
            'sort' => ['date_debut' => -1],
            'limit' => 5
        ]
    )->toArray();
    
    $recentSessions = [];
    
    // Format results for display
    foreach ($recentSessionsData as $session) {
        // Récupérer les informations de l'utilisateur
        $user = null;
        if (isset($session['utilisateur_id'])) {
            $user = $db->utilisateurs->findOne(['_id' => $session['utilisateur_id']]);
        }
        
        // Récupérer les informations de la borne
        $borne = null;
        if (isset($session['borne_id'])) {
            $borne = $db->bornes->findOne(['_id' => $session['borne_id']]);
        }
        
        // Récupérer les informations de la station si la borne existe
        $station = null;
        if ($borne && isset($borne['station_id'])) {
            $station = $db->stations->findOne(['_id' => $borne['station_id']]);
        }
        
        $recentSessions[] = [
            'id' => (string)$session['_id'],
            'user' => $user ? ($user['prenom'] . ' ' . $user['nom']) : 'Utilisateur inconnu',
            'station' => $station ? ($station['nom'] ?? 'Station inconnue') : 'Station inconnue',
            'borne' => $borne ? ($borne['numero'] ?? 'Borne inconnue') : 'Borne inconnue',
            'start' => isset($session['date_debut']) ? $session['date_debut']->toDateTime()->format('Y-m-d H:i:s') : 'N/A',
            'end' => isset($session['date_fin']) ? $session['date_fin']->toDateTime()->format('Y-m-d H:i:s') : 'En cours',
            'energy' => $session['energie_consommee'] ?? 0,
            'cost' => $session['cout'] ?? 0,
            'statut' => $session['statut'] ?? 'inconnu'
        ];
    }
    
    // Generate alerts based on real data
    $alerts = [];
    
    // Add alert for bornes that need maintenance
    if ($bornes_maintenance > 0) {
        $alerts[] = [
            'id' => 'A' . sprintf('%03d', count($alerts) + 1),
            'type' => 'maintenance',
            'message' => $bornes_maintenance . ' borne(s) nécessite(nt) une maintenance',
            'station' => 'Diverses stations',
            'date' => date('Y-m-d H:i:s'),
            'severity' => 'warning'
        ];
    }
    
    // Add alert for stations that are offline
    if ($stations_offline > 0) {
        $alerts[] = [
            'id' => 'A' . sprintf('%03d', count($alerts) + 1),
            'type' => 'offline',
            'message' => $stations_offline . ' station(s) hors ligne',
            'station' => 'Diverses stations',
            'date' => date('Y-m-d H:i:s'),
            'severity' => 'danger'
        ];
    }
    
    // Add alert for high usage today
    if ($sessionsToday > 5) {
        $alerts[] = [
            'id' => 'A' . sprintf('%03d', count($alerts) + 1),
            'type' => 'usage',
            'message' => $sessionsToday . ' sessions aujourd\'hui',
            'station' => 'Toutes stations',
            'date' => date('Y-m-d H:i:s'),
            'severity' => 'info'
        ];
    }
    
    // Add system alert
    $alerts[] = [
        'id' => 'A' . sprintf('%03d', count($alerts) + 1),
        'type' => 'system',
        'message' => 'Tableau de bord avec données réelles',
        'station' => 'Toutes',
        'date' => date('Y-m-d H:i:s'),
        'severity' => 'info'
    ];
    
} catch (Exception $e) {
    $error = 'Erreur de connexion à la base de données: ' . $e->getMessage();
    
    // Fallback to sample data
    $stats = [
        'stations_total' => 0,
        'stations_active' => 0,
        'stations_maintenance' => 0,
        'stations_offline' => 0,
        'bornes_total' => 0,
        'bornes_available' => 0,
        'bornes_in_use' => 0,
        'bornes_maintenance' => 0,
        'sessions_today' => 0,
        'sessions_week' => 0,
        'sessions_month' => 0,
        'energy_today' => 0,
        'energy_week' => 0,
        'energy_month' => 0,
        'revenue_today' => 0,
        'revenue_week' => 0,
        'revenue_month' => 0,
        'users_total' => 0
    ];
    
    $recentSessions = [];
    $alerts = [
        [
            'id' => 'A001',
            'type' => 'system',
            'message' => 'Erreur de connexion à la base de données',
            'station' => 'Système',
            'date' => date('Y-m-d H:i:s'),
            'severity' => 'danger'
        ]
    ];
}

// Include header
require_once '../../includes/header.php';

// For adding extra CSS specific to this page
$extraCSS = '<link rel="stylesheet" href="' . APP_URL . '/css/admin.css">';
?>

<main class="container-fluid mt-4">
    <div class="row">
        <?php include_once '../../includes/admin_sidebar.php'; ?>

        <!-- Main content -->
        <div class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Tableau de bord</h1>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- Stats Cards -->
            <div class="row dashboard-stats">
                <div class="col-md-3 mb-4">
                    <div class="stats-card stats-card-primary">
                        <div class="stats-icon">
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                        <div class="stats-number"><?php echo $stats['stations_active']; ?>/<?php echo $stats['stations_total']; ?></div>
                        <div class="stats-text">Stations actives</div>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="stats-card stats-card-success">
                        <div class="stats-icon">
                            <i class="fas fa-charging-station"></i>
                        </div>
                        <div class="stats-number"><?php echo $stats['bornes_available']; ?>/<?php echo $stats['bornes_total']; ?></div>
                        <div class="stats-text">Bornes disponibles</div>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="stats-card stats-card-warning">
                        <div class="stats-icon">
                            <i class="fas fa-bolt"></i>
                        </div>
                        <div class="stats-number"><?php echo number_format($stats['energy_today'], 2); ?> kWh</div>
                        <div class="stats-text">Énergie délivrée aujourd'hui</div>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="stats-card stats-card-danger">
                        <div class="stats-icon">
                            <i class="fas fa-euro-sign"></i>
                        </div>
                        <div class="stats-number"><?php echo number_format($stats['revenue_today'], 2); ?> €</div>
                        <div class="stats-text">Revenus aujourd'hui</div>
                    </div>
                </div>
            </div>

            <!-- Recent Sessions and Alerts -->
            <div class="row">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">Sessions récentes</h5>
                            <a href="sessions.php" class="btn btn-sm btn-primary">Voir toutes</a>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover table-striped">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Utilisateur</th>
                                            <th>Station</th>
                                            <th>Borne</th>
                                            <th>Début</th>
                                            <th>Fin</th>
                                            <th>Énergie</th>
                                            <th>Coût</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($recentSessions)): ?>
                                            <tr>
                                                <td colspan="8" class="text-center">Aucune session récente</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($recentSessions as $session): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($session['id']); ?></td>
                                                    <td><?php echo htmlspecialchars($session['user']); ?></td>
                                                    <td><?php echo htmlspecialchars($session['station']); ?></td>
                                                    <td><?php echo htmlspecialchars($session['borne']); ?></td>
                                                    <td><?php echo date('H:i', strtotime($session['start'])); ?></td>
                                                    <td><?php echo $session['end'] !== 'En cours' ? date('H:i', strtotime($session['end'])) : 'En cours'; ?></td>
                                                    <td><?php echo number_format($session['energy'], 2); ?> kWh</td>
                                                    <td><?php echo number_format($session['cost'], 2); ?> €</td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">Alertes</h5>
                        </div>
                        <div class="card-body p-0">
                            <ul class="list-group list-group-flush">
                                <?php if (empty($alerts)): ?>
                                    <li class="list-group-item text-center">Aucune alerte</li>
                                <?php else: ?>
                                    <?php foreach ($alerts as $alert): ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <div>
                                                <span class="badge bg-<?php echo $alert['severity']; ?> me-2">
                                                    <?php 
                                                        $icon = '';
                                                        switch ($alert['type']) {
                                                            case 'maintenance': $icon = 'tools'; break;
                                                            case 'offline': $icon = 'exclamation-circle'; break;
                                                            case 'usage': $icon = 'users'; break;
                                                            case 'payment': $icon = 'credit-card'; break;
                                                            case 'system': $icon = 'cog'; break;
                                                            default: $icon = 'info-circle';
                                                        }
                                                    ?>
                                                    <i class="fas fa-<?php echo $icon; ?>"></i>
                                                </span>
                                                <?php echo htmlspecialchars($alert['message']); ?>
                                            </div>
                                            <small class="text-muted"><?php echo date('H:i', strtotime($alert['date'])); ?></small>
                                        </li>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Stats row -->
            <div class="row mt-4">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Statistiques système</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="card bg-light mb-3">
                                        <div class="card-header">Sessions</div>
                                        <div class="card-body">
                                            <ul class="list-group list-group-flush">
                                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                                    Aujourd'hui
                                                    <span class="badge bg-primary rounded-pill"><?php echo $stats['sessions_today']; ?></span>
                                                </li>
                                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                                    Cette semaine
                                                    <span class="badge bg-primary rounded-pill"><?php echo $stats['sessions_week']; ?></span>
                                                </li>
                                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                                    Ce mois
                                                    <span class="badge bg-primary rounded-pill"><?php echo $stats['sessions_month']; ?></span>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="card bg-light mb-3">
                                        <div class="card-header">Énergie délivrée</div>
                                        <div class="card-body">
                                            <ul class="list-group list-group-flush">
                                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                                    Aujourd'hui
                                                    <span class="badge bg-success rounded-pill"><?php echo number_format($stats['energy_today'], 2); ?> kWh</span>
                                                </li>
                                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                                    Cette semaine
                                                    <span class="badge bg-success rounded-pill"><?php echo number_format($stats['energy_week'], 2); ?> kWh</span>
                                                </li>
                                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                                    Ce mois
                                                    <span class="badge bg-success rounded-pill"><?php echo number_format($stats['energy_month'], 2); ?> kWh</span>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="card bg-light mb-3">
                                        <div class="card-header">Revenus</div>
                                        <div class="card-body">
                                            <ul class="list-group list-group-flush">
                                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                                    Aujourd'hui
                                                    <span class="badge bg-danger rounded-pill"><?php echo number_format($stats['revenue_today'], 2); ?> €</span>
                                                </li>
                                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                                    Cette semaine
                                                    <span class="badge bg-danger rounded-pill"><?php echo number_format($stats['revenue_week'], 2); ?> €</span>
                                                </li>
                                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                                    Ce mois
                                                    <span class="badge bg-danger rounded-pill"><?php echo number_format($stats['revenue_month'], 2); ?> €</span>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php require_once '../../includes/footer.php'; ?>