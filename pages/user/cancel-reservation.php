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
$pageTitle = "Annuler une réservation";

// Process cancellation
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if confirmation checkbox is checked
    if (!isset($_POST['confirm_cancel']) || $_POST['confirm_cancel'] !== '1') {
        $error = 'Vous devez confirmer l\'annulation de la réservation';
    } else {
        // Call API to cancel reservation (DELETE request)
        $ch = curl_init(APP_URL . '/api/reservations.php?id=' . $reservation_id);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $response = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($statusCode === 200) {
            $result = json_decode($response, true);
            if ($result && isset($result['success']) && $result['success']) {
                $success = 'Votre réservation a été annulée avec succès';
            } else {
                $error = 'Erreur lors de l\'annulation de la réservation';
            }
        } else {
            $error = 'Erreur de communication avec le serveur (code: ' . $statusCode . ')';
        }
    }
}

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
                    <li class="breadcrumb-item active" aria-current="page">Annuler une réservation</li>
                </ol>
            </nav>
            
            <?php if (!empty($success)): ?>
                <div class="alert alert-success" role="alert">
                    <h4 class="alert-heading">Réservation annulée!</h4>
                    <p><?php echo htmlspecialchars($success); ?></p>
                    <hr>
                    <div class="d-flex justify-content-between">
                        <a href="reservations.php" class="btn btn-primary">Retour à mes sessions</a>
                        <a href="new-reservation.php" class="btn btn-outline-primary">Faire une nouvelle réservation</a>
                    </div>
                </div>
            <?php else: ?>
                <div class="card shadow">
                    <div class="card-header bg-danger text-white">
                        <h4 class="card-title mb-0">Annuler la réservation</h4>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger" role="alert">
                                <?php echo htmlspecialchars($error); ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="alert alert-warning">
                            <h5 class="alert-heading">Attention!</h5>
                            <p>Vous êtes sur le point d'annuler votre réservation. Cette action est irréversible.</p>
                        </div>
                        
                        <div class="card mb-4">
                            <div class="card-body">
                                <h5 class="card-title">Détails de la réservation</h5>
                                <table class="table table-borderless">
                                    <tbody>
                                        <tr>
                                            <th scope="row" width="40%">Numéro:</th>
                                            <td><?php echo htmlspecialchars($reservation['id']); ?></td>
                                        </tr>
                                        <tr>
                                            <th scope="row">Station:</th>
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
                                        <tr>
                                            <th scope="row">Date de réservation:</th>
                                            <td><?php echo date('d/m/Y H:i', strtotime($reservation['date_reservation'])); ?></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        
                        <form action="cancel-reservation.php?id=<?php echo htmlspecialchars($reservation_id); ?>" method="post">
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" value="1" name="confirm_cancel" id="confirmCheck" required>
                                <label class="form-check-label" for="confirmCheck">
                                    Je confirme vouloir annuler cette réservation
                                </label>
                            </div>
                            
                            <div class="d-flex justify-content-between">
                                <a href="reservations.php" class="btn btn-outline-secondary">Retour</a>
                                <button type="submit" class="btn btn-danger">Annuler la réservation</button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php require_once '../../includes/footer.php'; ?>