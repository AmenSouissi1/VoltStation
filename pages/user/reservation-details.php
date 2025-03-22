<?php
require_once '../../config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../auth/login.php');
    exit;
}

// Check if ID is provided
if (!isset($_GET['id'])) {
    header('Location: reservations.php');
    exit;
}

$reservation_id = $_GET['id'];
$user_id = $_SESSION['user_id'];
$pageTitle = "Détails de la réservation";

// Fetch reservation details
$ch = curl_init(APP_URL . '/api/reservations.php?id=' . $reservation_id);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$reservation = [];
if ($statusCode === 200) {
    $reservation = json_decode($response, true);
    
    // Check if this is the user's reservation
    if ($reservation && !empty($reservation) && $reservation['utilisateur'] != $user_id) {
        header('Location: reservations.php');
        exit;
    }
} else {
    header('Location: reservations.php');
    exit;
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
                    <li class="breadcrumb-item"><a href="reservations.php">Mes sessions</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Détails de la réservation</li>
                </ol>
            </nav>
            
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h4 class="card-title mb-0">Détails de la réservation</h4>
                </div>
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="d-flex align-items-center">
                                <div class="rounded-circle bg-primary text-white p-3 me-3">
                                    <i class="fas fa-charging-station fa-2x"></i>
                                </div>
                                <div>
                                    <h5 class="mb-0">Réservation #<?php echo htmlspecialchars($reservation['id']); ?></h5>
                                    <p class="text-muted mb-0">
                                        <?php 
                                            $statusClass = '';
                                            $statusText = '';
                                            
                                            if ($reservation['statut'] === 'confirmé') {
                                                $reservationDate = $reservation['date'] . ' ' . $reservation['heure_fin'];
                                                if (strtotime($reservationDate) < time()) {
                                                    $statusClass = 'bg-secondary';
                                                    $statusText = 'Terminée';
                                                } else {
                                                    $statusClass = 'bg-success';
                                                    $statusText = 'Confirmée';
                                                }
                                            } elseif ($reservation['statut'] === 'annulé') {
                                                $statusClass = 'bg-danger';
                                                $statusText = 'Annulée';
                                            }
                                            
                                            echo '<span class="badge ' . $statusClass . '">' . $statusText . '</span>';
                                        ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 text-md-end">
                            <?php 
                                $reservationDate = $reservation['date'] . ' ' . $reservation['heure_fin'];
                                if (strtotime($reservationDate) > time() && $reservation['statut'] === 'confirmé'):
                            ?>
                                <a href="edit-reservation.php?id=<?php echo htmlspecialchars($reservation['id']); ?>" class="btn btn-outline-primary me-2">
                                    <i class="fas fa-edit me-1"></i> Modifier
                                </a>
                                <a href="cancel-reservation.php?id=<?php echo htmlspecialchars($reservation['id']); ?>" class="btn btn-outline-danger">
                                    <i class="fas fa-times me-1"></i> Annuler
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card mb-4">
                                <div class="card-body">
                                    <h5 class="card-title">Informations de réservation</h5>
                                    <table class="table table-borderless">
                                        <tbody>
                                            <tr>
                                                <th scope="row" width="40%">Station:</th>
                                                <td><?php echo htmlspecialchars($reservation['station']); ?></td>
                                            </tr>
                                            <tr>
                                                <th scope="row">Borne:</th>
                                                <td><?php echo htmlspecialchars($reservation['borne']); ?></td>
                                            </tr>
                                            <tr>
                                                <th scope="row">Date:</th>
                                                <td><?php echo date('d/m/Y', strtotime($reservation['date'])); ?></td>
                                            </tr>
                                            <tr>
                                                <th scope="row">Horaire:</th>
                                                <td>
                                                    <?php 
                                                        $start = date('H:i', strtotime($reservation['heure_debut']));
                                                        $end = date('H:i', strtotime($reservation['heure_fin']));
                                                        echo "$start - $end";
                                                    ?>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card mb-4">
                                <div class="card-body">
                                    <h5 class="card-title">Historique</h5>
                                    <table class="table table-borderless">
                                        <tbody>
                                            <tr>
                                                <th scope="row" width="40%">Réservée le:</th>
                                                <td><?php echo date('d/m/Y H:i', strtotime($reservation['date_reservation'])); ?></td>
                                            </tr>
                                            <?php if (isset($reservation['date_annulation']) && $reservation['date_annulation']): ?>
                                            <tr>
                                                <th scope="row">Annulée le:</th>
                                                <td><?php echo date('d/m/Y H:i', strtotime($reservation['date_annulation'])); ?></td>
                                            </tr>
                                            <?php endif; ?>
                                            <?php
                                                $reservationDate = $reservation['date'] . ' ' . $reservation['heure_fin'];
                                                if (strtotime($reservationDate) < time() && $reservation['statut'] === 'confirmé'):
                                            ?>
                                            <tr>
                                                <th scope="row">Terminée le:</th>
                                                <td><?php echo date('d/m/Y H:i', strtotime($reservation['date'] . ' ' . $reservation['heure_fin'])); ?></td>
                                            </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <?php 
                        $reservationDate = $reservation['date'] . ' ' . $reservation['heure_debut'];
                        $isPast = strtotime($reservationDate) < time();
                        $isFuture = strtotime($reservationDate) > time();
                        
                        if ($reservation['statut'] === 'confirmé' && $isFuture):
                    ?>
                    <div class="alert alert-info mt-3">
                        <h5 class="alert-heading">Rappel!</h5>
                        <ul class="mb-0">
                            <li>Veuillez arriver à l'heure pour profiter de votre réservation</li>
                            <li>En cas de retard de plus de 15 minutes, votre réservation pourra être annulée</li>
                            <li>Vous pouvez modifier ou annuler votre réservation jusqu'à 1h avant l'heure prévue</li>
                        </ul>
                    </div>
                    <?php endif; ?>
                    
                    <div class="mt-4">
                        <a href="reservations.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-1"></i> Retour à mes sessions
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php require_once '../../includes/footer.php'; ?>