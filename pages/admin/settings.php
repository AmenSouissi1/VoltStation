<?php
require_once '../../config.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../../auth/login.php');
    exit;
}

$pageTitle = "Paramètres du Système";
$success = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    try {
        $db = connectDB();
        
        if ($action === 'update_general') {
            $app_name = $_POST['app_name'] ?? '';
            $app_url = $_POST['app_url'] ?? '';
            $notification_email = $_POST['notification_email'] ?? '';
            $max_reservations = filter_input(INPUT_POST, 'max_reservations', FILTER_VALIDATE_INT);
            
            if (empty($app_name) || empty($app_url) || $max_reservations === false) {
                $error = 'Veuillez remplir correctement tous les champs.';
            } else {
                // Update settings
                $settings = $db->settings->findOne(['type' => 'general']);
                
                if ($settings) {
                    $result = $db->settings->updateOne(
                        ['type' => 'general'],
                        ['$set' => [
                            'app_name' => $app_name,
                            'app_url' => $app_url,
                            'notification_email' => $notification_email,
                            'max_reservations' => $max_reservations,
                            'last_updated' => new MongoDB\BSON\UTCDateTime(time() * 1000)
                        ]]
                    );
                } else {
                    $result = $db->settings->insertOne([
                        'type' => 'general',
                        'app_name' => $app_name,
                        'app_url' => $app_url,
                        'notification_email' => $notification_email,
                        'max_reservations' => $max_reservations,
                        'created_at' => new MongoDB\BSON\UTCDateTime(time() * 1000),
                        'last_updated' => new MongoDB\BSON\UTCDateTime(time() * 1000)
                    ]);
                }
                
                if ($result->getModifiedCount() > 0 || $result->getInsertedCount() > 0) {
                    $success = 'Paramètres généraux mis à jour avec succès!';
                } else {
                    $error = 'Aucune modification n\'a été apportée.';
                }
            }
        } elseif ($action === 'update_mail') {
            $mail_host = $_POST['mail_host'] ?? '';
            $mail_port = filter_input(INPUT_POST, 'mail_port', FILTER_VALIDATE_INT);
            $mail_username = $_POST['mail_username'] ?? '';
            $mail_password = $_POST['mail_password'] ?? '';
            $mail_from = $_POST['mail_from'] ?? '';
            $mail_from_name = $_POST['mail_from_name'] ?? '';
            
            if (empty($mail_host) || $mail_port === false || empty($mail_from)) {
                $error = 'Veuillez remplir correctement tous les champs obligatoires.';
            } else {
                // Update mail settings
                $settings = $db->settings->findOne(['type' => 'mail']);
                
                if ($settings) {
                    $updateData = [
                        'host' => $mail_host,
                        'port' => $mail_port,
                        'username' => $mail_username,
                        'from' => $mail_from,
                        'from_name' => $mail_from_name,
                        'last_updated' => new MongoDB\BSON\UTCDateTime(time() * 1000)
                    ];
                    
                    // Only update password if a new one is provided
                    if (!empty($mail_password)) {
                        $updateData['password'] = $mail_password;
                    }
                    
                    $result = $db->settings->updateOne(
                        ['type' => 'mail'],
                        ['$set' => $updateData]
                    );
                } else {
                    $result = $db->settings->insertOne([
                        'type' => 'mail',
                        'host' => $mail_host,
                        'port' => $mail_port,
                        'username' => $mail_username,
                        'password' => $mail_password,
                        'from' => $mail_from,
                        'from_name' => $mail_from_name,
                        'created_at' => new MongoDB\BSON\UTCDateTime(time() * 1000),
                        'last_updated' => new MongoDB\BSON\UTCDateTime(time() * 1000)
                    ]);
                }
                
                if ($result->getModifiedCount() > 0 || $result->getInsertedCount() > 0) {
                    $success = 'Paramètres d\'email mis à jour avec succès!';
                } else {
                    $error = 'Aucune modification n\'a été apportée.';
                }
            }
        } elseif ($action === 'update_reservation') {
            $min_duration = filter_input(INPUT_POST, 'min_duration', FILTER_VALIDATE_INT);
            $max_duration = filter_input(INPUT_POST, 'max_duration', FILTER_VALIDATE_INT);
            $advance_time = filter_input(INPUT_POST, 'advance_time', FILTER_VALIDATE_INT);
            $cancellation_time = filter_input(INPUT_POST, 'cancellation_time', FILTER_VALIDATE_INT);
            
            if ($min_duration === false || $max_duration === false || $advance_time === false || $cancellation_time === false) {
                $error = 'Veuillez remplir correctement tous les champs.';
            } else {
                // Update reservation settings
                $settings = $db->settings->findOne(['type' => 'reservation']);
                
                if ($settings) {
                    $result = $db->settings->updateOne(
                        ['type' => 'reservation'],
                        ['$set' => [
                            'min_duration' => $min_duration,
                            'max_duration' => $max_duration,
                            'advance_time' => $advance_time,
                            'cancellation_time' => $cancellation_time,
                            'last_updated' => new MongoDB\BSON\UTCDateTime(time() * 1000)
                        ]]
                    );
                } else {
                    $result = $db->settings->insertOne([
                        'type' => 'reservation',
                        'min_duration' => $min_duration,
                        'max_duration' => $max_duration,
                        'advance_time' => $advance_time,
                        'cancellation_time' => $cancellation_time,
                        'created_at' => new MongoDB\BSON\UTCDateTime(time() * 1000),
                        'last_updated' => new MongoDB\BSON\UTCDateTime(time() * 1000)
                    ]);
                }
                
                if ($result->getModifiedCount() > 0 || $result->getInsertedCount() > 0) {
                    $success = 'Paramètres de réservation mis à jour avec succès!';
                } else {
                    $error = 'Aucune modification n\'a été apportée.';
                }
            }
        }
    } catch (Exception $e) {
        $error = 'Erreur: ' . $e->getMessage();
    }
}

// Get settings from database
try {
    $db = connectDB();
    
    // Get general settings
    $generalSettings = $db->settings->findOne(['type' => 'general']);
    
    // Get mail settings
    $mailSettings = $db->settings->findOne(['type' => 'mail']);
    
    // Get reservation settings
    $reservationSettings = $db->settings->findOne(['type' => 'reservation']);
    
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
                <h1 class="h2">Paramètres du Système</h1>
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
            
            <!-- Settings Tabs -->
            <ul class="nav nav-tabs mb-4" id="settingsTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="general-tab" data-bs-toggle="tab" data-bs-target="#general" type="button" role="tab" aria-controls="general" aria-selected="true">
                        <i class="fas fa-cog me-1"></i> Général
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="mail-tab" data-bs-toggle="tab" data-bs-target="#mail" type="button" role="tab" aria-controls="mail" aria-selected="false">
                        <i class="fas fa-envelope me-1"></i> Email
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="reservation-tab" data-bs-toggle="tab" data-bs-target="#reservation" type="button" role="tab" aria-controls="reservation" aria-selected="false">
                        <i class="fas fa-calendar-alt me-1"></i> Sessions
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="system-tab" data-bs-toggle="tab" data-bs-target="#system" type="button" role="tab" aria-controls="system" aria-selected="false">
                        <i class="fas fa-server me-1"></i> Système
                    </button>
                </li>
            </ul>
            
            <!-- Tab Content -->
            <div class="tab-content" id="settingsTabsContent">
                <!-- General Settings Tab -->
                <div class="tab-pane fade show active" id="general" role="tabpanel" aria-labelledby="general-tab">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Paramètres généraux</h5>
                        </div>
                        <div class="card-body">
                            <form action="settings.php" method="post">
                                <input type="hidden" name="action" value="update_general">
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="app_name" class="form-label">Nom de l'application</label>
                                        <input type="text" class="form-control" id="app_name" name="app_name" value="<?php echo htmlspecialchars($generalSettings['app_name'] ?? APP_NAME); ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="app_url" class="form-label">URL de l'application</label>
                                        <input type="url" class="form-control" id="app_url" name="app_url" value="<?php echo htmlspecialchars($generalSettings['app_url'] ?? APP_URL); ?>" required>
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="notification_email" class="form-label">Email de notification</label>
                                        <input type="email" class="form-control" id="notification_email" name="notification_email" value="<?php echo htmlspecialchars($generalSettings['notification_email'] ?? ''); ?>">
                                        <div class="form-text">Les alertes système seront envoyées à cette adresse.</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="max_reservations" class="form-label">Nombre max. de réservations par utilisateur</label>
                                        <input type="number" class="form-control" id="max_reservations" name="max_reservations" min="1" value="<?php echo htmlspecialchars($generalSettings['max_reservations'] ?? 3); ?>" required>
                                    </div>
                                </div>
                                
                                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-1"></i> Enregistrer
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Mail Settings Tab -->
                <div class="tab-pane fade" id="mail" role="tabpanel" aria-labelledby="mail-tab">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Configuration des emails</h5>
                        </div>
                        <div class="card-body">
                            <form action="settings.php" method="post">
                                <input type="hidden" name="action" value="update_mail">
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="mail_host" class="form-label">Serveur SMTP</label>
                                        <input type="text" class="form-control" id="mail_host" name="mail_host" value="<?php echo htmlspecialchars($mailSettings['host'] ?? ''); ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="mail_port" class="form-label">Port SMTP</label>
                                        <input type="number" class="form-control" id="mail_port" name="mail_port" value="<?php echo htmlspecialchars($mailSettings['port'] ?? 587); ?>" required>
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="mail_username" class="form-label">Nom d'utilisateur SMTP</label>
                                        <input type="text" class="form-control" id="mail_username" name="mail_username" value="<?php echo htmlspecialchars($mailSettings['username'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="mail_password" class="form-label">Mot de passe SMTP</label>
                                        <input type="password" class="form-control" id="mail_password" name="mail_password" placeholder="<?php echo empty($mailSettings['password']) ? 'Aucun mot de passe défini' : '••••••••••'; ?>">
                                        <div class="form-text">Laissez vide pour conserver le mot de passe actuel.</div>
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="mail_from" class="form-label">Email expéditeur</label>
                                        <input type="email" class="form-control" id="mail_from" name="mail_from" value="<?php echo htmlspecialchars($mailSettings['from'] ?? ''); ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="mail_from_name" class="form-label">Nom expéditeur</label>
                                        <input type="text" class="form-control" id="mail_from_name" name="mail_from_name" value="<?php echo htmlspecialchars($mailSettings['from_name'] ?? APP_NAME); ?>">
                                    </div>
                                </div>
                                
                                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                    <button type="button" class="btn btn-outline-secondary me-md-2" id="testEmailBtn">
                                        <i class="fas fa-paper-plane me-1"></i> Tester la configuration
                                    </button>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-1"></i> Enregistrer
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Reservation Settings Tab -->
                <div class="tab-pane fade" id="reservation" role="tabpanel" aria-labelledby="reservation-tab">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Paramètres des sessions</h5>
                        </div>
                        <div class="card-body">
                            <form action="settings.php" method="post">
                                <input type="hidden" name="action" value="update_reservation">
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="min_duration" class="form-label">Durée minimale (minutes)</label>
                                        <input type="number" class="form-control" id="min_duration" name="min_duration" min="15" value="<?php echo htmlspecialchars($reservationSettings['min_duration'] ?? 30); ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="max_duration" class="form-label">Durée maximale (minutes)</label>
                                        <input type="number" class="form-control" id="max_duration" name="max_duration" min="30" value="<?php echo htmlspecialchars($reservationSettings['max_duration'] ?? 240); ?>" required>
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="advance_time" class="form-label">Temps de réservation à l'avance (heures)</label>
                                        <input type="number" class="form-control" id="advance_time" name="advance_time" min="1" value="<?php echo htmlspecialchars($reservationSettings['advance_time'] ?? 24); ?>" required>
                                        <div class="form-text">Combien de temps à l'avance un utilisateur peut réserver.</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="cancellation_time" class="form-label">Temps d'annulation (minutes)</label>
                                        <input type="number" class="form-control" id="cancellation_time" name="cancellation_time" min="15" value="<?php echo htmlspecialchars($reservationSettings['cancellation_time'] ?? 60); ?>" required>
                                        <div class="form-text">Temps minimum avant la réservation pour pouvoir annuler.</div>
                                    </div>
                                </div>
                                
                                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-1"></i> Enregistrer
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- System Information Tab -->
                <div class="tab-pane fade" id="system" role="tabpanel" aria-labelledby="system-tab">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Informations système</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6 class="text-primary">Application</h6>
                                    <table class="table table-bordered">
                                        <tbody>
                                            <tr>
                                                <th>Version</th>
                                                <td><?php echo APP_VERSION; ?></td>
                                            </tr>
                                            <tr>
                                                <th>URL</th>
                                                <td><?php echo APP_URL; ?></td>
                                            </tr>
                                            <tr>
                                                <th>Base de données</th>
                                                <td>MongoDB - <?php echo DB_NAME; ?></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="col-md-6">
                                    <h6 class="text-primary">Environnement</h6>
                                    <table class="table table-bordered">
                                        <tbody>
                                            <tr>
                                                <th>PHP Version</th>
                                                <td><?php echo phpversion(); ?></td>
                                            </tr>
                                            <tr>
                                                <th>Serveur Web</th>
                                                <td><?php echo $_SERVER['SERVER_SOFTWARE']; ?></td>
                                            </tr>
                                            <tr>
                                                <th>Extensions</th>
                                                <td>
                                                    <?php
                                                        $extensions = ['mongodb', 'json', 'curl'];
                                                        foreach ($extensions as $ext) {
                                                            $loaded = extension_loaded($ext);
                                                            echo '<span class="badge ' . ($loaded ? 'bg-success' : 'bg-danger') . '">' . $ext . '</span> ';
                                                        }
                                                    ?>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            
                            <div class="mt-4">
                                <h6 class="text-primary">Maintenance</h6>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="d-grid gap-2">
                                            <button type="button" class="btn btn-outline-secondary">
                                                <i class="fas fa-database me-1"></i> Vider le cache
                                            </button>
                                            <button type="button" class="btn btn-outline-secondary">
                                                <i class="fas fa-file-archive me-1"></i> Sauvegarder la base de données
                                            </button>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="alert alert-warning mb-0">
                                            <p class="mb-2"><i class="fas fa-exclamation-triangle me-1"></i> <strong>Zone de danger</strong></p>
                                            <div class="d-grid gap-2">
                                                <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#resetSystemModal">
                                                    <i class="fas fa-sync-alt me-1"></i> Réinitialiser le système
                                                </button>
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
    </div>
</main>

<!-- Test Email Modal -->
<div class="modal fade" id="testEmailModal" tabindex="-1" aria-labelledby="testEmailModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="testEmailModalLabel">Tester la configuration email</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="test_email" class="form-label">Adresse email de test</label>
                    <input type="email" class="form-control" id="test_email" placeholder="Votre adresse email">
                </div>
                <div id="test_email_result"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                <button type="button" class="btn btn-primary" id="sendTestEmailBtn">Envoyer un email de test</button>
            </div>
        </div>
    </div>
</div>

<!-- Reset System Modal -->
<div class="modal fade" id="resetSystemModal" tabindex="-1" aria-labelledby="resetSystemModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="resetSystemModalLabel">⚠️ Réinitialiser le système</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger">
                    <p><strong>Attention !</strong> Cette action est irréversible. Toutes les données du système seront supprimées.</p>
                    <p>Veuillez confirmer votre action en tapant "RÉINITIALISER" ci-dessous :</p>
                </div>
                <div class="mb-3">
                    <input type="text" class="form-control" id="resetConfirmation" placeholder="Tapez RÉINITIALISER">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-danger" id="confirmResetBtn" disabled>
                    <i class="fas fa-exclamation-triangle me-1"></i> Réinitialiser le système
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Test Email Button
    document.getElementById('testEmailBtn').addEventListener('click', function() {
        const testEmailModal = new bootstrap.Modal(document.getElementById('testEmailModal'));
        testEmailModal.show();
    });
    
    // Send Test Email Button
    document.getElementById('sendTestEmailBtn').addEventListener('click', function() {
        const testEmail = document.getElementById('test_email').value;
        const resultDiv = document.getElementById('test_email_result');
        
        if (!testEmail) {
            resultDiv.innerHTML = '<div class="alert alert-danger">Veuillez entrer une adresse email.</div>';
            return;
        }
        
        // In a real app, this would call an API to send a test email
        resultDiv.innerHTML = '<div class="alert alert-info">Envoi en cours...</div>';
        
        // Simulate API call
        setTimeout(function() {
            resultDiv.innerHTML = '<div class="alert alert-success">Email de test envoyé avec succès!</div>';
        }, 1500);
    });
    
    // Reset Confirmation
    document.getElementById('resetConfirmation').addEventListener('input', function() {
        const confirmBtn = document.getElementById('confirmResetBtn');
        confirmBtn.disabled = this.value !== 'RÉINITIALISER';
    });
    
    // Confirm Reset Button
    document.getElementById('confirmResetBtn').addEventListener('click', function() {
        // In a real app, this would call an API to reset the system
        alert('Cette fonctionnalité n\'est pas activée dans cette démo.');
        
        // Close the modal
        const resetModal = bootstrap.Modal.getInstance(document.getElementById('resetSystemModal'));
        resetModal.hide();
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>