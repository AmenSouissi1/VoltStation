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
$pageTitle = "Modifier une réservation";

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

// Get stations from API for the station select dropdown
$stationsApiUrl = APP_URL . '/api/stations.php?action=available';
$stationsJson = file_get_contents($stationsApiUrl);
$availableStations = json_decode($stationsJson, true);

// Process form submission
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date = $_POST['date'] ?? '';
    $time_start = $_POST['time_start'] ?? '';
    $time_end = $_POST['time_end'] ?? '';
    
    // Validate input
    if (empty($date) || empty($time_start) || empty($time_end)) {
        $error = 'Veuillez remplir tous les champs obligatoires';
    } else {
        // Create update data
        $updateData = [
            'date' => $date,
            'heure_debut' => $time_start,
            'heure_fin' => $time_end,
        ];
        
        // Call API to update reservation (PUT request)
        $ch = curl_init(APP_URL . '/api/reservations.php?id=' . $reservation_id);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($updateData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        
        $response = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($statusCode === 200) {
            $result = json_decode($response, true);
            if ($result && isset($result['success']) && $result['success']) {
                $success = 'Votre réservation a été modifiée avec succès';
                
                // Refresh reservation details
                $ch = curl_init(APP_URL . '/api/reservations.php?id=' . $reservation_id);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                $response = curl_exec($ch);
                $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                if ($statusCode === 200) {
                    $reservation = json_decode($response, true);
                }
            } else {
                $error = 'Erreur lors de la modification de la réservation';
            }
        } else {
            $error = 'Erreur de communication avec le serveur (code: ' . $statusCode . ')';
        }
    }
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
                    <li class="breadcrumb-item active" aria-current="page">Modifier une réservation</li>
                </ol>
            </nav>
            
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h4 class="card-title mb-0">Modifier la réservation</h4>
                </div>
                <div class="card-body">
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger" role="alert">
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($success)): ?>
                        <div class="alert alert-success" role="alert">
                            <?php echo htmlspecialchars($success); ?>
                            <div class="mt-3">
                                <a href="reservations.php" class="btn btn-primary">Retour à mes sessions</a>
                            </div>
                        </div>
                    <?php endif; ?>
                    
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
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <form action="edit-reservation.php?id=<?php echo htmlspecialchars($reservation_id); ?>" method="post" id="editForm">
                        <div class="card mb-4">
                            <div class="card-body">
                                <h5 class="card-title">Modifier la date et l'heure</h5>
                                
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label for="date" class="form-label">Date</label>
                                        <input type="date" class="form-control" id="date" name="date" required min="<?php echo date('Y-m-d'); ?>" value="<?php echo htmlspecialchars($reservation['date']); ?>">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label for="time_start" class="form-label">Heure de début</label>
                                        <select class="form-select" id="time_start" name="time_start" required>
                                            <option value="">Sélectionnez</option>
                                            <?php
                                            $start_times = [
                                                '08:00:00', '08:30:00', '09:00:00', '09:30:00', '10:00:00', '10:30:00',
                                                '11:00:00', '11:30:00', '12:00:00', '12:30:00', '13:00:00', '13:30:00',
                                                '14:00:00', '14:30:00', '15:00:00', '15:30:00', '16:00:00', '16:30:00',
                                                '17:00:00', '17:30:00', '18:00:00', '18:30:00', '19:00:00', '19:30:00'
                                            ];
                                            
                                            foreach ($start_times as $time) {
                                                $selected = ($time === $reservation['heure_debut']) ? 'selected' : '';
                                                $display_time = substr($time, 0, 5);
                                                echo "<option value=\"$time\" $selected>$display_time</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label for="time_end" class="form-label">Heure de fin</label>
                                        <select class="form-select" id="time_end" name="time_end" required>
                                            <option value="">Sélectionnez</option>
                                            <?php
                                            $end_times = [
                                                '08:30:00', '09:00:00', '09:30:00', '10:00:00', '10:30:00', '11:00:00',
                                                '11:30:00', '12:00:00', '12:30:00', '13:00:00', '13:30:00', '14:00:00',
                                                '14:30:00', '15:00:00', '15:30:00', '16:00:00', '16:30:00', '17:00:00',
                                                '17:30:00', '18:00:00', '18:30:00', '19:00:00', '19:30:00', '20:00:00'
                                            ];
                                            
                                            foreach ($end_times as $time) {
                                                $selected = ($time === $reservation['heure_fin']) ? 'selected' : '';
                                                $display_time = substr($time, 0, 5);
                                                echo "<option value=\"$time\" $selected>$display_time</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="reservations.php" class="btn btn-outline-secondary">Annuler</a>
                            <div>
                                <a href="cancel-reservation.php?id=<?php echo htmlspecialchars($reservation_id); ?>" class="btn btn-outline-danger me-2">Annuler la réservation</a>
                                <button type="submit" class="btn btn-primary">Enregistrer les modifications</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Time selection logic
    const timeStartSelect = document.getElementById('time_start');
    const timeEndSelect = document.getElementById('time_end');
    
    timeStartSelect.addEventListener('change', function() {
        // Clear end time options
        while (timeEndSelect.options.length > 1) {
            timeEndSelect.remove(1);
        }
        
        if (this.value) {
            const startTime = this.value;
            const [startHour, startMinute] = startTime.split(':');
            const startMinutes = parseInt(startHour) * 60 + parseInt(startMinute);
            
            // Add time slots at least 30 minutes after start time
            for (let minutes = startMinutes + 30; minutes <= 20 * 60; minutes += 30) {
                const hour = Math.floor(minutes / 60);
                const minute = minutes % 60;
                
                const timeValue = `${String(hour).padStart(2, '0')}:${String(minute).padStart(2, '0')}:00`;
                const timeDisplay = `${String(hour).padStart(2, '0')}:${String(minute).padStart(2, '0')}`;
                
                const option = document.createElement('option');
                option.value = timeValue;
                option.text = timeDisplay;
                timeEndSelect.appendChild(option);
            }
        }
    });
    
    // Trigger change event on page load to populate end times based on selected start time
    if (timeStartSelect.value) {
        const event = new Event('change');
        timeStartSelect.dispatchEvent(event);
        
        // Set the correct end time after populating options
        const savedEndTime = "<?php echo $reservation['heure_fin']; ?>";
        if (savedEndTime) {
            for (let i = 0; i < timeEndSelect.options.length; i++) {
                if (timeEndSelect.options[i].value === savedEndTime) {
                    timeEndSelect.selectedIndex = i;
                    break;
                }
            }
        }
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>