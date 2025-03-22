<?php
require_once '../../config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../auth/login.php');
    exit;
}

$pageTitle = "Mes Sessions";

// Get user reservations directly from database instead of API
$user_id = $_SESSION['user_id'];
$userReservations = [];

try {
    $db = connectDB();
    $reservationsCollection = $db->reservations_session;
    $stationsCollection = $db->stations;
    $bornesCollection = $db->bornes;
    
    // Query for MongoDB ObjectId or string ID
    $mongoId = null;
    try {
        $mongoId = new MongoDB\BSON\ObjectId($user_id);
    } catch (Exception $e) {
        // If not valid MongoDB ObjectId, will use string comparison
        error_log("Warning: Using string ID for user: " . $e->getMessage());
    }
    
    $query = $mongoId ? ['utilisateur_id' => $mongoId] : ['utilisateur_id' => $user_id];
    error_log("Querying reservations with: " . json_encode($query));
    
    $cursor = $reservationsCollection->find($query);
    
    foreach ($cursor as $reservation) {
        // Convert MongoDB ObjectId to string for JSON
        $reservation['_id'] = (string)$reservation['_id'];
        $reservation['utilisateur_id'] = (string)$reservation['utilisateur_id'];
        
        // Handle station and borne IDs
        if (isset($reservation['station_id'])) {
            $stationId = $reservation['station_id'];
            $stationName = (string)$stationId;
            
            // Try to get station name if it's an ObjectId
            if ($stationId instanceof MongoDB\BSON\ObjectId) {
                $station = $stationsCollection->findOne(['_id' => $stationId]);
                if ($station && isset($station['nom'])) {
                    $stationName = $station['nom'];
                }
            }
            
            $reservation['station'] = $stationName;
        }
        
        if (isset($reservation['borne_id'])) {
            $borneId = $reservation['borne_id'];
            $borneNumber = (string)$borneId;
            
            // Try to get borne number if it's an ObjectId
            if ($borneId instanceof MongoDB\BSON\ObjectId) {
                $borne = $bornesCollection->findOne(['_id' => $borneId]);
                if ($borne && isset($borne['numero'])) {
                    $borneNumber = $borne['numero'];
                }
            }
            
            $reservation['borne'] = $borneNumber;
        }
        
        // Format the rest of the fields to match expected structure
        $userReservations[] = [
            'id' => $reservation['numero'],
            'utilisateur' => (string)$reservation['utilisateur_id'],
            'station' => $reservation['station'] ?? 'Station inconnue',
            'borne' => $reservation['borne'] ?? 'Borne inconnue',
            'date' => $reservation['date'],
            'heure_debut' => $reservation['heure_debut'],
            'heure_fin' => $reservation['heure_fin'],
            'statut' => $reservation['statut'],
            'date_reservation' => $reservation['date_reservation'],
            'date_annulation' => isset($reservation['date_annulation']) ? $reservation['date_annulation'] : null
        ];
    }
} catch (Exception $e) {
    error_log("Error fetching reservations: " . $e->getMessage());
    // Initialize with empty array to avoid fatal errors
    $userReservations = [];
}

// Include header
require_once '../../includes/header.php';
?>

<main class="container mt-4">
    <div class="row">
        <?php include_once '../../includes/user_sidebar.php'; ?>
        
        <div class="col-md-9">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php">Tableau de bord</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Mes sessions</li>
                </ol>
            </nav>
            
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="mb-0">Mes sessions</h1>
                <a href="new-reservation.php" class="btn btn-primary">
                    <i class="fas fa-plus me-1"></i> Nouvelle réservation
                </a>
            </div>
            
            <!-- Tabs -->
            <ul class="nav nav-tabs mb-4" id="reservationTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="upcoming-tab" data-bs-toggle="tab" data-bs-target="#upcoming" type="button" role="tab" aria-controls="upcoming" aria-selected="true">
                        À venir
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="past-tab" data-bs-toggle="tab" data-bs-target="#past" type="button" role="tab" aria-controls="past" aria-selected="false">
                        Passées
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="cancelled-tab" data-bs-toggle="tab" data-bs-target="#cancelled" type="button" role="tab" aria-controls="cancelled" aria-selected="false">
                        Annulées
                    </button>
                </li>
            </ul>
            
            <!-- Tab Content -->
            <div class="tab-content" id="reservationTabsContent">
                <!-- Upcoming Reservations -->
                <div class="tab-pane fade show active" id="upcoming" role="tabpanel" aria-labelledby="upcoming-tab">
                    <?php
                    // Filter for upcoming reservations
                    $upcomingReservations = array_filter($userReservations, function($reservation) {
                        $reservationDate = $reservation['date'] . ' ' . $reservation['heure_debut'];
                        return strtotime($reservationDate) > time() && $reservation['statut'] === 'confirmé';
                    });
                    
                    if (empty($upcomingReservations)):
                    ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i> Vous n'avez aucune réservation à venir.
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($upcomingReservations as $reservation): ?>
                                <div class="col-md-6 col-lg-4 mb-4">
                                    <div class="card h-100">
                                        <div class="card-header bg-primary text-white">
                                            <h5 class="card-title mb-0">
                                                <?php echo htmlspecialchars($reservation['station']); ?>
                                            </h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="d-flex align-items-center mb-3">
                                                <div class="rounded-circle bg-success text-white me-3 p-3">
                                                    <i class="fas fa-charging-station fa-2x"></i>
                                                </div>
                                                <div>
                                                    <h6 class="mb-0">Borne <?php echo htmlspecialchars($reservation['borne']); ?></h6>
                                                    <span class="badge bg-success">Confirmée</span>
                                                </div>
                                            </div>
                                            <ul class="list-group list-group-flush mb-3">
                                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                                    <span><i class="fas fa-calendar me-2"></i> Date</span>
                                                    <strong><?php echo date('d/m/Y', strtotime($reservation['date'])); ?></strong>
                                                </li>
                                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                                    <span><i class="fas fa-clock me-2"></i> Heure</span>
                                                    <strong><?php 
                                                        $start = date('H:i', strtotime($reservation['heure_debut']));
                                                        $end = date('H:i', strtotime($reservation['heure_fin']));
                                                        echo "$start - $end";
                                                    ?></strong>
                                                </li>
                                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                                    <span><i class="fas fa-bolt me-2"></i> Réservation</span>
                                                    <strong><?php echo htmlspecialchars($reservation['id']); ?></strong>
                                                </li>
                                            </ul>
                                            <div class="d-grid gap-2">
                                                <a href="edit-reservation.php?id=<?php echo htmlspecialchars($reservation['id']); ?>" class="btn btn-outline-primary">
                                                    <i class="fas fa-edit me-1"></i> Modifier
                                                </a>
                                                <a href="cancel-reservation.php?id=<?php echo htmlspecialchars($reservation['id']); ?>" class="btn btn-outline-danger">
                                                    <i class="fas fa-times me-1"></i> Annuler
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Past Reservations -->
                <div class="tab-pane fade" id="past" role="tabpanel" aria-labelledby="past-tab">
                    <?php
                    // Filter for past reservations
                    $pastReservations = array_filter($userReservations, function($reservation) {
                        $reservationDate = $reservation['date'] . ' ' . $reservation['heure_fin'];
                        return strtotime($reservationDate) < time() && $reservation['statut'] === 'confirmé';
                    });
                    
                    if (empty($pastReservations)):
                    ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i> Vous n'avez aucune réservation passée.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Station</th>
                                        <th>Borne</th>
                                        <th>Date</th>
                                        <th>Horaire</th>
                                        <th>Statut</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pastReservations as $reservation): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($reservation['id']); ?></td>
                                            <td><?php echo htmlspecialchars($reservation['station']); ?></td>
                                            <td><?php echo htmlspecialchars($reservation['borne']); ?></td>
                                            <td><?php echo date('d/m/Y', strtotime($reservation['date'])); ?></td>
                                            <td><?php 
                                                $start = date('H:i', strtotime($reservation['heure_debut']));
                                                $end = date('H:i', strtotime($reservation['heure_fin']));
                                                echo "$start - $end";
                                            ?></td>
                                            <td>
                                                <span class="badge bg-secondary">Terminée</span>
                                            </td>
                                            <td>
                                                <a href="reservation-details.php?id=<?php echo htmlspecialchars($reservation['id']); ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Cancelled Reservations -->
                <div class="tab-pane fade" id="cancelled" role="tabpanel" aria-labelledby="cancelled-tab">
                    <?php
                    // Filter for cancelled reservations
                    $cancelledReservations = array_filter($userReservations, function($reservation) {
                        return $reservation['statut'] === 'annulé';
                    });
                    
                    if (empty($cancelledReservations)):
                    ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i> Vous n'avez aucune réservation annulée.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Station</th>
                                        <th>Borne</th>
                                        <th>Date</th>
                                        <th>Horaire</th>
                                        <th>Annulée le</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($cancelledReservations as $reservation): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($reservation['id']); ?></td>
                                            <td><?php echo htmlspecialchars($reservation['station']); ?></td>
                                            <td><?php echo htmlspecialchars($reservation['borne']); ?></td>
                                            <td><?php echo date('d/m/Y', strtotime($reservation['date'])); ?></td>
                                            <td><?php 
                                                $start = date('H:i', strtotime($reservation['heure_debut']));
                                                $end = date('H:i', strtotime($reservation['heure_fin']));
                                                echo "$start - $end";
                                            ?></td>
                                            <td><?php echo isset($reservation['date_annulation']) ? date('d/m/Y H:i', strtotime($reservation['date_annulation'])) : 'N/A'; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<?php require_once '../../includes/footer.php'; ?>