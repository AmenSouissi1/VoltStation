<?php
require_once '../../config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../auth/login.php');
    exit;
}

$pageTitle = "Nouvelle Réservation";

// Get stations from database directly
try {
    $db = connectDB();
    $availableStations = [];
    $stationsCollection = $db->stations;
    $bornesCollection = $db->bornes;
    
    $stations = $stationsCollection->find()->toArray();
    
    foreach ($stations as $station) {
        // Count available bornes
        $availableBorneCount = $bornesCollection->count([
            'station_id' => $station['_id'],
            'statut' => 'disponible'
        ]);
        
        // Count total bornes
        $totalBorneCount = $bornesCollection->count([
            'station_id' => $station['_id']
        ]);
        
        // Ne pas ajouter les stations sans bornes disponibles
        if ($availableBorneCount > 0) {
            $availableStations[] = [
                'id' => (string)$station['_id'],
                'name' => $station['nom'],
                'address' => $station['adresse'],
                'coordinates' => isset($station['coordonnees']) ? $station['coordonnees'] : [0, 0],
                'available_bornes' => $availableBorneCount,
                'total_bornes' => $totalBorneCount
            ];
        }
    }
} catch (Exception $e) {
    error_log("Error loading stations: " . $e->getMessage());
    $availableStations = [];
}

// Process form submission
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $station_id = $_POST['station_id'] ?? '';
    $borne_id = $_POST['borne_id'] ?? '';
    $date = $_POST['date'] ?? '';
    $time_start = $_POST['time_start'] ?? '';
    $time_end = $_POST['time_end'] ?? '';
    
    // Validate input
    if (empty($station_id) || empty($borne_id) || empty($date) || empty($time_start) || empty($time_end)) {
        $error = 'Veuillez remplir tous les champs obligatoires';
    } else {
        // Vérifier que la date et l'heure ne sont pas dans le passé
        $reservationDateTime = new DateTime($date . ' ' . $time_start);
        $now = new DateTime();
        
        if ($reservationDateTime < $now) {
            $error = 'La date et l\'heure de réservation ne peuvent pas être dans le passé';
        } else {
            // Create reservation data
            $reservationData = [
                'utilisateur' => $_SESSION['user_id'],
                'station' => $station_id,
                'borne' => $borne_id,
                'date' => $date,
                'heure_debut' => $time_start,
                'heure_fin' => $time_end,
                'statut' => 'confirmé',
                'date_reservation' => date('Y-m-d H:i:s')
            ];
        
            // Log the data being sent for debugging
            error_log("Reservation data: " . json_encode($reservationData));
            
            // Call API to create reservation
            $ch = curl_init(APP_URL . '/api/reservations.php');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($reservationData));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json'
            ]);
            
            $response = curl_exec($ch);
            $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
        
            if ($curlError) {
                $error = 'Erreur CURL: ' . $curlError;
            } else if ($statusCode === 200) {
                $result = json_decode($response, true);
                if ($result && isset($result['success']) && $result['success']) {
                    $success = 'Réservation confirmée! Votre numéro de réservation est: ' . $result['id'];
                } else {
                    $errorMessage = isset($result['error']) ? $result['error'] : 'Erreur inconnue';
                    $error = 'Erreur lors de la création de la réservation: ' . $errorMessage;
                }
            } else {
                // Decode the error response if possible
                $errorResponse = json_decode($response, true);
                $specificError = isset($errorResponse['error']) ? $errorResponse['error'] : "Code HTTP: $statusCode";
                $error = 'Erreur de communication avec le serveur: ' . $specificError;
                
                // Log the complete response for debugging
                error_log("API Error - Status: $statusCode, Response: $response");
            }
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
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h3 class="card-title mb-0">Réserver une borne de recharge</h3>
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
                                <a href="reservations.php" class="btn btn-primary">Voir mes sessions</a>
                                <a href="dashboard.php" class="btn btn-outline-primary ms-2">Retour au tableau de bord</a>
                            </div>
                        </div>
                    <?php else: ?>
                        <form action="new-reservation.php" method="post" id="reservationForm">
                            <!-- Step 1: Choose Station -->
                            <div class="reservation-step" id="step1">
                                <h4 class="mb-4">Étape 1: Choisir une station</h4>
                                
                                <div class="mb-3">
                                    <label for="station_id" class="form-label">Station de recharge</label>
                                    <select class="form-select" id="station_id" name="station_id" required>
                                        <option value="">Sélectionnez une station</option>
                                        <?php foreach ($availableStations as $station): ?>
                                            <option value="<?php echo htmlspecialchars($station['id']); ?>" 
                                                    data-available="<?php echo htmlspecialchars($station['available_bornes']); ?>"
                                                    data-total="<?php echo htmlspecialchars($station['total_bornes']); ?>"
                                                    data-address="<?php echo htmlspecialchars($station['address']); ?>">
                                                <?php echo htmlspecialchars($station['name']); ?> 
                                                (<?php echo htmlspecialchars($station['available_bornes']); ?>/<?php echo htmlspecialchars($station['total_bornes']); ?> bornes disponibles)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="mb-3" id="stationDetails" style="display: none;">
                                    <div class="card">
                                        <div class="card-body">
                                            <h5 class="card-title" id="selectedStationName"></h5>
                                            <p class="card-text" id="selectedStationAddress"></p>
                                            <div class="progress mb-3">
                                                <div class="progress-bar bg-success" id="stationProgress" role="progressbar" style="width: 0%"></div>
                                            </div>
                                            <p class="text-success" id="stationAvailability"></p>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="d-flex justify-content-between mt-4">
                                    <a href="dashboard.php" class="btn btn-outline-secondary">Annuler</a>
                                    <button type="button" class="btn btn-primary next-step" data-next="step2">Continuer</button>
                                </div>
                            </div>
                            
                            <!-- Step 2: Choose Borne and Time -->
                            <div class="reservation-step" id="step2" style="display: none;">
                                <h4 class="mb-4">Étape 2: Choisir une borne et un créneau horaire</h4>
                                
                                <div class="mb-3">
                                    <label for="borne_id" class="form-label">Borne de recharge</label>
                                    <select class="form-select" id="borne_id" name="borne_id" required>
                                        <option value="">Sélectionnez une borne</option>
                                        <!-- Will be populated via JavaScript -->
                                    </select>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label for="date" class="form-label">Date</label>
                                        <input type="date" class="form-control" id="date" name="date" required min="<?php echo date('Y-m-d'); ?>">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label for="time_start" class="form-label">Heure de début</label>
                                        <select class="form-select" id="time_start" name="time_start" required>
                                            <option value="">Sélectionnez</option>
                                            <!-- Will be populated via JavaScript -->
                                        </select>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label for="time_end" class="form-label">Heure de fin</label>
                                        <select class="form-select" id="time_end" name="time_end" required>
                                            <option value="">Sélectionnez</option>
                                            <!-- Will be populated via JavaScript -->
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" value="" id="notification">
                                        <label class="form-check-label" for="notification">
                                            Recevoir un rappel par email 1h avant la réservation
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="d-flex justify-content-between mt-4">
                                    <button type="button" class="btn btn-outline-secondary prev-step" data-prev="step1">Retour</button>
                                    <button type="button" class="btn btn-primary next-step" data-next="step3">Continuer</button>
                                </div>
                            </div>
                            
                            <!-- Step 3: Confirm Reservation -->
                            <div class="reservation-step" id="step3" style="display: none;">
                                <h4 class="mb-4">Étape 3: Confirmer la réservation</h4>
                                
                                <div class="card mb-4">
                                    <div class="card-body">
                                        <h5 class="card-title">Résumé de votre réservation</h5>
                                        <table class="table table-borderless">
                                            <tbody>
                                                <tr>
                                                    <th scope="row" width="40%">Station:</th>
                                                    <td id="summary_station"></td>
                                                </tr>
                                                <tr>
                                                    <th scope="row">Borne:</th>
                                                    <td id="summary_borne"></td>
                                                </tr>
                                                <tr>
                                                    <th scope="row">Date:</th>
                                                    <td id="summary_date"></td>
                                                </tr>
                                                <tr>
                                                    <th scope="row">Horaire:</th>
                                                    <td id="summary_time"></td>
                                                </tr>
                                                <tr>
                                                    <th scope="row">Durée:</th>
                                                    <td id="summary_duration"></td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                
                                <div class="alert alert-info">
                                    <h6 class="alert-heading">Informations importantes</h6>
                                    <ul class="mb-0">
                                        <li>Vous pouvez annuler ou modifier votre réservation jusqu'à 1h avant l'heure prévue</li>
                                        <li>Veuillez arriver à l'heure pour profiter de votre réservation</li>
                                        <li>En cas de retard de plus de 15 minutes, votre réservation pourra être annulée</li>
                                    </ul>
                                </div>
                                
                                <div class="form-check mb-4">
                                    <input class="form-check-input" type="checkbox" value="1" name="terms_accepted" id="termsCheck" required>
                                    <label class="form-check-label" for="termsCheck">
                                        J'accepte les conditions d'utilisation du service de réservation
                                    </label>
                                </div>
                                
                                <div class="d-flex justify-content-between mt-4">
                                    <button type="button" class="btn btn-outline-secondary prev-step" data-prev="step2">Retour</button>
                                    <button type="submit" class="btn btn-success" id="confirmButton">Confirmer la réservation</button>
                                </div>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Définir la date minimale au champ date (aujourd'hui)
    const dateInput = document.getElementById('date');
    if (dateInput) {
        const today = new Date();
        const yyyy = today.getFullYear();
        const mm = String(today.getMonth() + 1).padStart(2, '0');
        const dd = String(today.getDate()).padStart(2, '0');
        const todayString = `${yyyy}-${mm}-${dd}`;
        dateInput.min = todayString;
        dateInput.value = todayString;
    }
    
    // Step navigation
    const reservationSteps = document.querySelectorAll('.reservation-step');
    const nextButtons = document.querySelectorAll('.next-step');
    const prevButtons = document.querySelectorAll('.prev-step');
    
    nextButtons.forEach(button => {
        button.addEventListener('click', function() {
            const nextStep = this.getAttribute('data-next');
            
            // Validate current step
            if (nextStep === 'step2') {
                if (!document.getElementById('station_id').value) {
                    alert('Veuillez sélectionner une station');
                    return;
                }
            } else if (nextStep === 'step3') {
                if (!document.getElementById('borne_id').value ||
                    !document.getElementById('date').value ||
                    !document.getElementById('time_start').value ||
                    !document.getElementById('time_end').value) {
                    alert('Veuillez remplir tous les champs');
                    return;
                }
                
                // Update summary
                document.getElementById('summary_station').textContent = 
                    document.getElementById('station_id').options[document.getElementById('station_id').selectedIndex].text;
                document.getElementById('summary_borne').textContent = 
                    document.getElementById('borne_id').options[document.getElementById('borne_id').selectedIndex].text;
                
                const dateObj = new Date(document.getElementById('date').value);
                const formattedDate = dateObj.toLocaleDateString('fr-FR', { 
                    weekday: 'long', 
                    year: 'numeric', 
                    month: 'long', 
                    day: 'numeric' 
                });
                document.getElementById('summary_date').textContent = formattedDate;
                
                const timeStart = document.getElementById('time_start').value;
                const timeEnd = document.getElementById('time_end').value;
                document.getElementById('summary_time').textContent = 
                    `De ${timeStart.substring(0, 5)} à ${timeEnd.substring(0, 5)}`;
                
                // Calculate duration
                const startParts = timeStart.split(':');
                const endParts = timeEnd.split(':');
                const startMinutes = parseInt(startParts[0]) * 60 + parseInt(startParts[1]);
                const endMinutes = parseInt(endParts[0]) * 60 + parseInt(endParts[1]);
                const durationMinutes = endMinutes - startMinutes;
                const hours = Math.floor(durationMinutes / 60);
                const minutes = durationMinutes % 60;
                
                let durationText = '';
                if (hours > 0) {
                    durationText += `${hours} heure${hours > 1 ? 's' : ''}`;
                }
                if (minutes > 0) {
                    durationText += `${hours > 0 ? ' et ' : ''}${minutes} minute${minutes > 1 ? 's' : ''}`;
                }
                
                document.getElementById('summary_duration').textContent = durationText;
            }
            
            // Hide all steps
            reservationSteps.forEach(step => {
                step.style.display = 'none';
            });
            
            // Show next step
            document.getElementById(nextStep).style.display = 'block';
        });
    });
    
    prevButtons.forEach(button => {
        button.addEventListener('click', function() {
            const prevStep = this.getAttribute('data-prev');
            
            // Hide all steps
            reservationSteps.forEach(step => {
                step.style.display = 'none';
            });
            
            // Show previous step
            document.getElementById(prevStep).style.display = 'block';
        });
    });
    
    // Station selection
    const stationSelect = document.getElementById('station_id');
    stationSelect.addEventListener('change', function() {
        const stationDetails = document.getElementById('stationDetails');
        
        if (this.value) {
            const selectedOption = this.options[this.selectedIndex];
            const stationName = selectedOption.text.split(' (')[0];
            const stationAddress = selectedOption.getAttribute('data-address');
            const availableBornes = parseInt(selectedOption.getAttribute('data-available'));
            const totalBornes = parseInt(selectedOption.getAttribute('data-total'));
            const availabilityPercentage = (availableBornes / totalBornes) * 100;
            
            document.getElementById('selectedStationName').textContent = stationName;
            document.getElementById('selectedStationAddress').textContent = stationAddress;
            document.getElementById('stationProgress').style.width = availabilityPercentage + '%';
            document.getElementById('stationAvailability').textContent = 
                `${availableBornes} bornes disponibles sur ${totalBornes}`;
            
            stationDetails.style.display = 'block';
            
            // Populate bornes (in a real app, this would call an API)
            const borneSelect = document.getElementById('borne_id');
            borneSelect.innerHTML = '<option value="">Sélectionnez une borne</option>';
            
            // Fetch actual bornes from the database
            fetch(`/api/stations.php?id=${this.value}`)
                .then(response => response.json())
                .then(station => {
                    if (station.bornes) {
                        // Use actual borne data from the MongoDB database
                        station.bornes.forEach(borne => {
                            if (borne.etat_actuel === 'disponible') {
                                const option = document.createElement('option');
                                option.value = borne.id_borne;
                                option.text = `${borne.id_borne} (${borne.type_borne} - ${borne.puissance} kW)`;
                                borneSelect.appendChild(option);
                            }
                        });
                    } else {
                        // Fallback to sample data for testing
                        const stationPrefix = stationName.includes('Centre') ? 'PC' : 
                                         stationName.includes('Nord') ? 'PN' : 
                                         stationName.includes('Sud') ? 'PS' : 
                                         stationName.includes('Est') ? 'PE' : 'PO';
                    
                        for (let i = 1; i <= availableBornes; i++) {
                            const option = document.createElement('option');
                            const borneId = `${stationPrefix}-${String(i).padStart(2, '0')}`;
                            option.value = borneId;
                            option.text = `${borneId} (Disponible)`;
                            borneSelect.appendChild(option);
                        }
                    }
                })
                .catch(error => {
                    console.error('Error fetching bornes:', error);
                    
                    // Fallback to generic borne IDs
                    for (let i = 1; i <= availableBornes; i++) {
                        const option = document.createElement('option');
                        option.value = `B${String(i).padStart(3, '0')}`;
                        option.text = `Borne ${i} (Disponible)`;
                        borneSelect.appendChild(option);
                    }
                });
        } else {
            stationDetails.style.display = 'none';
        }
    });
    
    // Time selection logic
    const timeStartSelect = document.getElementById('time_start');
    const timeEndSelect = document.getElementById('time_end');
    
    // Limiter les heures de début en fonction de la date et l'heure actuelle
    function updateTimeStartOptions() {
        const selectedDate = dateInput.value;
        const now = new Date();
        const today = now.toISOString().split('T')[0];
        
        timeStartSelect.innerHTML = '<option value="">Sélectionnez</option>';
        
        // Générer les options d'heure (de 6h à 20h par pas de 30 min)
        for (let hour = 6; hour <= 20; hour++) {
            for (let minute of [0, 30]) {
                // Si date = aujourd'hui et heure < heure actuelle, ne pas ajouter cette option
                if (selectedDate === today) {
                    const currentHour = now.getHours();
                    const currentMinute = now.getMinutes();
                    const currentTotalMinutes = currentHour * 60 + currentMinute;
                    const optionTotalMinutes = hour * 60 + minute;
                    
                    // Ignorer les heures passées avec une marge de 30 minutes
                    if (optionTotalMinutes <= currentTotalMinutes + 30) {
                        continue;
                    }
                }
                
                const timeValue = `${String(hour).padStart(2, '0')}:${String(minute).padStart(2, '0')}:00`;
                const timeDisplay = `${String(hour).padStart(2, '0')}:${String(minute).padStart(2, '0')}`;
                
                const option = document.createElement('option');
                option.value = timeValue;
                option.text = timeDisplay;
                timeStartSelect.appendChild(option);
            }
        }
    }
    
    // Mettre à jour les heures de début lors du changement de date
    if (dateInput) {
        dateInput.addEventListener('change', updateTimeStartOptions);
        // Initialiser au chargement
        updateTimeStartOptions();
    }
    
    timeStartSelect.addEventListener('change', function() {
        timeEndSelect.innerHTML = '<option value="">Sélectionnez</option>';
        
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
    
    // Terms checkbox
    const termsCheck = document.getElementById('termsCheck');
    const confirmButton = document.getElementById('confirmButton');
    
    if (termsCheck && confirmButton) {
        // Disable button if checkbox is not checked
        confirmButton.disabled = !termsCheck.checked;
        
        // Add event listener for checkbox changes
        termsCheck.addEventListener('change', function() {
            confirmButton.disabled = !this.checked;
        });
        
        // Fix form submission
        confirmButton.addEventListener('click', function(event) {
            if (!termsCheck.checked) {
                event.preventDefault();
                alert('Veuillez accepter les conditions d\'utilisation pour confirmer votre réservation.');
                return false;
            }
            document.getElementById('reservationForm').submit();
        });
        
        // Prevent default form submission and handle it ourselves
        document.getElementById('reservationForm').addEventListener('submit', function(event) {
            if (!termsCheck.checked) {
                event.preventDefault();
                alert('Veuillez accepter les conditions d\'utilisation pour confirmer votre réservation.');
            }
        });
    } else {
        console.warn('Terms checkbox or confirm button not found in DOM');
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>