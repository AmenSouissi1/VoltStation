<?php
require_once '../../config.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../../auth/login.php');
    exit;
}

$pageTitle = "Gestion des Tarifs";
$success = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    try {
        $db = connectDB();
        
        if ($action === 'update_tarif') {
            $tarifId = $_POST['tarif_id'] ?? '';
            $prix_kwh = filter_input(INPUT_POST, 'prix_kwh', FILTER_VALIDATE_FLOAT);
            $prix_minute = filter_input(INPUT_POST, 'prix_minute', FILTER_VALIDATE_FLOAT);
            $frais_service = filter_input(INPUT_POST, 'frais_service', FILTER_VALIDATE_FLOAT);
            
            if (empty($tarifId) || $prix_kwh === false || $prix_minute === false || $frais_service === false) {
                $error = 'Veuillez remplir correctement tous les champs.';
            } else {
                // Update tarif
                $result = $db->tarifications->updateOne(
                    ['_id' => new MongoDB\BSON\ObjectId($tarifId)],
                    ['$set' => [
                        'prix_kwh' => (float)$prix_kwh,
                        'prix_minute' => (float)$prix_minute,
                        'frais_service' => (float)$frais_service,
                        'date_modification' => new MongoDB\BSON\UTCDateTime(time() * 1000)
                    ]]
                );
                
                if ($result->getModifiedCount() > 0) {
                    $success = 'Tarif mis à jour avec succès!';
                } else {
                    $error = 'Aucune modification n\'a été apportée.';
                }
            }
        } elseif ($action === 'add_tarif') {
            $type_borne = $_POST['type_borne'] ?? '';
            $prix_kwh = filter_input(INPUT_POST, 'prix_kwh', FILTER_VALIDATE_FLOAT);
            $prix_minute = filter_input(INPUT_POST, 'prix_minute', FILTER_VALIDATE_FLOAT);
            $frais_service = filter_input(INPUT_POST, 'frais_service', FILTER_VALIDATE_FLOAT);
            
            if (empty($type_borne) || $prix_kwh === false || $prix_minute === false || $frais_service === false) {
                $error = 'Veuillez remplir correctement tous les champs.';
            } else {
                // Check if tarif for this type already exists
                $existingTarif = $db->tarifications->findOne(['type_borne' => $type_borne]);
                
                if ($existingTarif) {
                    $error = 'Un tarif pour ce type de borne existe déjà.';
                } else {
                    // Create new tarif
                    $newTarif = [
                        'type_borne' => $type_borne,
                        'prix_kwh' => (float)$prix_kwh,
                        'prix_minute' => (float)$prix_minute,
                        'frais_service' => (float)$frais_service,
                        'date_effet' => new MongoDB\BSON\UTCDateTime(time() * 1000)
                    ];
                    
                    $result = $db->tarifications->insertOne($newTarif);
                    
                    if ($result->getInsertedCount() > 0) {
                        $success = 'Tarif ajouté avec succès!';
                    } else {
                        $error = 'Erreur lors de l\'ajout du tarif.';
                    }
                }
            }
        } elseif ($action === 'add_subscription') {
            $nom = $_POST['nom'] ?? '';
            $description = $_POST['description'] ?? '';
            $prix_mensuel = filter_input(INPUT_POST, 'prix_mensuel', FILTER_VALIDATE_FLOAT);
            $reduction_kwh = filter_input(INPUT_POST, 'reduction_kwh', FILTER_VALIDATE_INT);
            $duree_engagement = filter_input(INPUT_POST, 'duree_engagement', FILTER_VALIDATE_INT);
            
            if (empty($nom) || $prix_mensuel === false || $reduction_kwh === false) {
                $error = 'Veuillez remplir correctement tous les champs.';
            } else {
                // Create new subscription
                $newSubscription = [
                    'nom' => $nom,
                    'description' => $description,
                    'prix_mensuel' => (float)$prix_mensuel,
                    'reduction_kwh' => (int)$reduction_kwh,
                    'duree_engagement' => (int)$duree_engagement,
                    'date_creation' => new MongoDB\BSON\UTCDateTime(time() * 1000),
                    'actif' => true
                ];
                
                $result = $db->abonnements->insertOne($newSubscription);
                
                if ($result->getInsertedCount() > 0) {
                    $success = 'Abonnement ajouté avec succès!';
                } else {
                    $error = 'Erreur lors de l\'ajout de l\'abonnement.';
                }
            }
        }
    } catch (Exception $e) {
        $error = 'Erreur: ' . $e->getMessage();
    }
}

// Get tarifs from database
try {
    $db = connectDB();
    $tarifs = $db->tarifications->find([], ['sort' => ['type_borne' => 1]])->toArray();
    
    // Get subscriptions
    $subscriptions = $db->abonnements->find([], ['sort' => ['prix_mensuel' => 1]])->toArray();
    
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
                <h1 class="h2">Gestion des Tarifs</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTarifModal">
                            <i class="fas fa-plus me-1"></i> Ajouter un tarif
                        </button>
                    </div>
                    <div class="btn-group">
                        <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addSubscriptionModal">
                            <i class="fas fa-plus me-1"></i> Ajouter un abonnement
                        </button>
                    </div>
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
            
            <!-- Pricing Tabs -->
            <ul class="nav nav-tabs mb-4" id="pricingTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="standard-tab" data-bs-toggle="tab" data-bs-target="#standard" type="button" role="tab" aria-controls="standard" aria-selected="true">
                        Tarifs standard
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="subscriptions-tab" data-bs-toggle="tab" data-bs-target="#subscriptions" type="button" role="tab" aria-controls="subscriptions" aria-selected="false">
                        Abonnements
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="promotions-tab" data-bs-toggle="tab" data-bs-target="#promotions" type="button" role="tab" aria-controls="promotions" aria-selected="false">
                        Promotions
                    </button>
                </li>
            </ul>
            
            <!-- Tab Content -->
            <div class="tab-content" id="pricingTabsContent">
                <!-- Standard Pricing Tab -->
                <div class="tab-pane fade show active" id="standard" role="tabpanel" aria-labelledby="standard-tab">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Tarifs par type de borne</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Type de borne</th>
                                            <th>Prix par kWh</th>
                                            <th>Prix par minute</th>
                                            <th>Frais de service</th>
                                            <th>Date d'effet</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($tarifs as $tarif): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($tarif['type_borne']); ?></td>
                                                <td><?php echo number_format($tarif['prix_kwh'], 2); ?> €</td>
                                                <td><?php echo number_format($tarif['prix_minute'], 2); ?> €</td>
                                                <td><?php echo number_format($tarif['frais_service'], 2); ?> €</td>
                                                <td>
                                                    <?php 
                                                        echo isset($tarif['date_effet']) 
                                                            ? date('d/m/Y', $tarif['date_effet']->toDateTime()->getTimestamp()) 
                                                            : 'N/A';
                                                    ?>
                                                </td>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editTarifModal" 
                                                            data-tarif-id="<?php echo $tarif['_id']; ?>"
                                                            data-tarif-type="<?php echo htmlspecialchars($tarif['type_borne']); ?>"
                                                            data-tarif-kwh="<?php echo htmlspecialchars($tarif['prix_kwh']); ?>"
                                                            data-tarif-minute="<?php echo htmlspecialchars($tarif['prix_minute']); ?>"
                                                            data-tarif-service="<?php echo htmlspecialchars($tarif['frais_service']); ?>">
                                                        <i class="fas fa-edit"></i> Modifier
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <?php if (count($tarifs) === 0): ?>
                                            <tr>
                                                <td colspan="6" class="text-center">Aucun tarif défini</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Example Calculation Card -->
                            <div class="card mt-4">
                                <div class="card-header">
                                    <h6 class="card-title mb-0">Simulateur de coût</h6>
                                </div>
                                <div class="card-body">
                                    <form id="costCalculator">
                                        <div class="row">
                                            <div class="col-md-3 mb-3">
                                                <label for="chargeType" class="form-label">Type de borne</label>
                                                <select class="form-select" id="chargeType">
                                                    <?php foreach ($tarifs as $tarif): ?>
                                                        <option value="<?php echo $tarif['_id']; ?>" 
                                                                data-kwh="<?php echo $tarif['prix_kwh']; ?>" 
                                                                data-minute="<?php echo $tarif['prix_minute']; ?>" 
                                                                data-service="<?php echo $tarif['frais_service']; ?>">
                                                            <?php echo htmlspecialchars($tarif['type_borne']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-3 mb-3">
                                                <label for="energyAmount" class="form-label">Énergie (kWh)</label>
                                                <input type="number" class="form-control" id="energyAmount" min="0" step="0.1" value="20">
                                            </div>
                                            <div class="col-md-3 mb-3">
                                                <label for="chargeDuration" class="form-label">Durée (minutes)</label>
                                                <input type="number" class="form-control" id="chargeDuration" min="0" value="60">
                                            </div>
                                            <div class="col-md-3 mb-3">
                                                <label for="hasSubscription" class="form-label">Abonnement</label>
                                                <select class="form-select" id="hasSubscription">
                                                    <option value="0">Sans abonnement</option>
                                                    <?php foreach ($subscriptions as $subscription): ?>
                                                        <option value="<?php echo $subscription['reduction_kwh']; ?>">
                                                            <?php echo htmlspecialchars($subscription['nom']); ?> (-<?php echo $subscription['reduction_kwh']; ?>%)
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="text-center">
                                            <button type="button" class="btn btn-primary" id="calculateButton">Calculer</button>
                                        </div>
                                    </form>
                                    
                                    <div class="mt-4" id="calculationResult" style="display: none;">
                                        <div class="alert alert-info">
                                            <h6 class="alert-heading">Coût estimé:</h6>
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <p class="mb-1">Coût de l'énergie: <strong id="energyCost">0.00 €</strong></p>
                                                    <p class="mb-1">Coût du temps: <strong id="timeCost">0.00 €</strong></p>
                                                    <p class="mb-1">Frais de service: <strong id="serviceCost">0.00 €</strong></p>
                                                </div>
                                                <div class="col-md-6">
                                                    <p class="mb-1">Remise abonnement: <strong id="subscriptionDiscount">0.00 €</strong></p>
                                                    <p class="mb-1">Sous-total: <strong id="subtotal">0.00 €</strong></p>
                                                    <p class="mb-1">TVA (20%): <strong id="taxAmount">0.00 €</strong></p>
                                                </div>
                                            </div>
                                            <hr>
                                            <div class="text-center">
                                                <h4>Total: <strong id="totalCost">0.00 €</strong></h4>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Subscriptions Tab -->
                <div class="tab-pane fade" id="subscriptions" role="tabpanel" aria-labelledby="subscriptions-tab">
                    <div class="row">
                        <?php foreach ($subscriptions as $subscription): ?>
                            <div class="col-md-4 mb-4">
                                <div class="card h-100">
                                    <div class="card-header bg-primary text-white">
                                        <h5 class="card-title mb-0"><?php echo htmlspecialchars($subscription['nom']); ?></h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="text-center mb-3">
                                            <h2 class="text-primary"><?php echo number_format($subscription['prix_mensuel'], 2); ?> €</h2>
                                            <p class="text-muted">par mois</p>
                                        </div>
                                        
                                        <hr>
                                        
                                        <p><?php echo htmlspecialchars($subscription['description']); ?></p>
                                        
                                        <ul class="list-group list-group-flush mb-3">
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                <span>Réduction sur kWh</span>
                                                <span class="badge bg-success"><?php echo htmlspecialchars($subscription['reduction_kwh']); ?>%</span>
                                            </li>
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                <span>Engagement</span>
                                                <span><?php echo $subscription['duree_engagement'] > 0 ? $subscription['duree_engagement'] . ' mois' : 'Sans engagement'; ?></span>
                                            </li>
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                <span>Statut</span>
                                                <?php
                                                    $activeClass = isset($subscription['actif']) && $subscription['actif'] ? 'bg-success' : 'bg-secondary';
                                                    $activeText = isset($subscription['actif']) && $subscription['actif'] ? 'Actif' : 'Inactif';
                                                ?>
                                                <span class="badge <?php echo $activeClass; ?>"><?php echo $activeText; ?></span>
                                            </li>
                                        </ul>
                                        
                                        <div class="d-grid">
                                            <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editSubscriptionModal"
                                                    data-sub-id="<?php echo $subscription['_id']; ?>"
                                                    data-sub-name="<?php echo htmlspecialchars($subscription['nom']); ?>"
                                                    data-sub-description="<?php echo htmlspecialchars($subscription['description']); ?>"
                                                    data-sub-price="<?php echo htmlspecialchars($subscription['prix_mensuel']); ?>"
                                                    data-sub-reduction="<?php echo htmlspecialchars($subscription['reduction_kwh']); ?>"
                                                    data-sub-engagement="<?php echo htmlspecialchars($subscription['duree_engagement']); ?>"
                                                    data-sub-active="<?php echo isset($subscription['actif']) && $subscription['actif'] ? '1' : '0'; ?>">
                                                <i class="fas fa-edit"></i> Modifier
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <?php if (count($subscriptions) === 0): ?>
                            <div class="col-12">
                                <div class="alert alert-info">
                                    <p class="mb-0">Aucun abonnement défini. Utilisez le bouton "Ajouter un abonnement" pour créer de nouveaux plans.</p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Promotions Tab -->
                <div class="tab-pane fade" id="promotions" role="tabpanel" aria-labelledby="promotions-tab">
                    <div class="card">
                        <div class="card-body">
                            <div class="alert alert-info">
                                <p class="mb-0">La gestion des promotions sera disponible dans une prochaine mise à jour.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Add Tarif Modal -->
<div class="modal fade" id="addTarifModal" tabindex="-1" aria-labelledby="addTarifModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addTarifModalLabel">Ajouter un nouveau tarif</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="tarifs.php" method="post">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_tarif">
                    
                    <div class="mb-3">
                        <label for="type_borne" class="form-label">Type de borne</label>
                        <select class="form-select" id="type_borne" name="type_borne" required>
                            <option value="">Sélectionnez un type</option>
                            <option value="Standard">Standard</option>
                            <option value="Rapide">Rapide</option>
                            <option value="Ultra-rapide">Ultra-rapide</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="prix_kwh" class="form-label">Prix par kWh (€)</label>
                        <input type="number" class="form-control" id="prix_kwh" name="prix_kwh" min="0" step="0.01" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="prix_minute" class="form-label">Prix par minute (€)</label>
                        <input type="number" class="form-control" id="prix_minute" name="prix_minute" min="0" step="0.01" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="frais_service" class="form-label">Frais de service (€)</label>
                        <input type="number" class="form-control" id="frais_service" name="frais_service" min="0" step="0.01" required>
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

<!-- Edit Tarif Modal -->
<div class="modal fade" id="editTarifModal" tabindex="-1" aria-labelledby="editTarifModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editTarifModalLabel">Modifier le tarif</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="tarifs.php" method="post">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_tarif">
                    <input type="hidden" name="tarif_id" id="edit_tarif_id">
                    
                    <div class="mb-3">
                        <label class="form-label">Type de borne</label>
                        <p class="form-control-static" id="edit_tarif_type"></p>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_prix_kwh" class="form-label">Prix par kWh (€)</label>
                        <input type="number" class="form-control" id="edit_prix_kwh" name="prix_kwh" min="0" step="0.01" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_prix_minute" class="form-label">Prix par minute (€)</label>
                        <input type="number" class="form-control" id="edit_prix_minute" name="prix_minute" min="0" step="0.01" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_frais_service" class="form-label">Frais de service (€)</label>
                        <input type="number" class="form-control" id="edit_frais_service" name="frais_service" min="0" step="0.01" required>
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

<!-- Add Subscription Modal -->
<div class="modal fade" id="addSubscriptionModal" tabindex="-1" aria-labelledby="addSubscriptionModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addSubscriptionModalLabel">Ajouter un nouvel abonnement</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="tarifs.php" method="post">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_subscription">
                    
                    <div class="mb-3">
                        <label for="nom" class="form-label">Nom de l'abonnement</label>
                        <input type="text" class="form-control" id="nom" name="nom" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="prix_mensuel" class="form-label">Prix mensuel (€)</label>
                        <input type="number" class="form-control" id="prix_mensuel" name="prix_mensuel" min="0" step="0.01" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="reduction_kwh" class="form-label">Réduction sur le prix kWh (%)</label>
                        <input type="number" class="form-control" id="reduction_kwh" name="reduction_kwh" min="0" max="100" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="duree_engagement" class="form-label">Durée d'engagement (mois)</label>
                        <input type="number" class="form-control" id="duree_engagement" name="duree_engagement" min="0" value="0">
                        <small class="form-text text-muted">0 = Sans engagement</small>
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

<!-- Edit Subscription Modal -->
<div class="modal fade" id="editSubscriptionModal" tabindex="-1" aria-labelledby="editSubscriptionModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editSubscriptionModalLabel">Modifier l'abonnement</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="tarifs.php" method="post">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_subscription">
                    <input type="hidden" name="subscription_id" id="edit_sub_id">
                    
                    <div class="mb-3">
                        <label for="edit_sub_name" class="form-label">Nom de l'abonnement</label>
                        <input type="text" class="form-control" id="edit_sub_name" name="nom" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_sub_description" class="form-label">Description</label>
                        <textarea class="form-control" id="edit_sub_description" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_sub_price" class="form-label">Prix mensuel (€)</label>
                        <input type="number" class="form-control" id="edit_sub_price" name="prix_mensuel" min="0" step="0.01" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_sub_reduction" class="form-label">Réduction sur le prix kWh (%)</label>
                        <input type="number" class="form-control" id="edit_sub_reduction" name="reduction_kwh" min="0" max="100" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_sub_engagement" class="form-label">Durée d'engagement (mois)</label>
                        <input type="number" class="form-control" id="edit_sub_engagement" name="duree_engagement" min="0">
                        <small class="form-text text-muted">0 = Sans engagement</small>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="edit_sub_active" name="actif">
                        <label class="form-check-label" for="edit_sub_active">Abonnement actif</label>
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

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Edit Tarif Modal
    const editTarifModal = document.getElementById('editTarifModal');
    editTarifModal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        const tarifId = button.getAttribute('data-tarif-id');
        const tarifType = button.getAttribute('data-tarif-type');
        const tarifKwh = button.getAttribute('data-tarif-kwh');
        const tarifMinute = button.getAttribute('data-tarif-minute');
        const tarifService = button.getAttribute('data-tarif-service');
        
        const modal = this;
        modal.querySelector('#edit_tarif_id').value = tarifId;
        modal.querySelector('#edit_tarif_type').textContent = tarifType;
        modal.querySelector('#edit_prix_kwh').value = tarifKwh;
        modal.querySelector('#edit_prix_minute').value = tarifMinute;
        modal.querySelector('#edit_frais_service').value = tarifService;
    });
    
    // Edit Subscription Modal
    const editSubModal = document.getElementById('editSubscriptionModal');
    editSubModal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        const subId = button.getAttribute('data-sub-id');
        const subName = button.getAttribute('data-sub-name');
        const subDescription = button.getAttribute('data-sub-description');
        const subPrice = button.getAttribute('data-sub-price');
        const subReduction = button.getAttribute('data-sub-reduction');
        const subEngagement = button.getAttribute('data-sub-engagement');
        const subActive = button.getAttribute('data-sub-active') === '1';
        
        const modal = this;
        modal.querySelector('#edit_sub_id').value = subId;
        modal.querySelector('#edit_sub_name').value = subName;
        modal.querySelector('#edit_sub_description').value = subDescription;
        modal.querySelector('#edit_sub_price').value = subPrice;
        modal.querySelector('#edit_sub_reduction').value = subReduction;
        modal.querySelector('#edit_sub_engagement').value = subEngagement;
        modal.querySelector('#edit_sub_active').checked = subActive;
    });
    
    // Cost Calculator
    const calculateButton = document.getElementById('calculateButton');
    calculateButton.addEventListener('click', function() {
        const tarifSelect = document.getElementById('chargeType');
        const selectedOption = tarifSelect.options[tarifSelect.selectedIndex];
        
        const priceKwh = parseFloat(selectedOption.getAttribute('data-kwh'));
        const priceMinute = parseFloat(selectedOption.getAttribute('data-minute'));
        const priceService = parseFloat(selectedOption.getAttribute('data-service'));
        
        const energy = parseFloat(document.getElementById('energyAmount').value);
        const duration = parseFloat(document.getElementById('chargeDuration').value);
        
        const subscriptionSelect = document.getElementById('hasSubscription');
        const discountPercent = parseInt(subscriptionSelect.value);
        
        // Calculate costs
        const energyCost = energy * priceKwh;
        const timeCost = duration * priceMinute;
        const serviceCost = priceService;
        
        const discountAmount = (energyCost * discountPercent / 100);
        const subtotal = energyCost + timeCost + serviceCost - discountAmount;
        const taxAmount = subtotal * 0.2; // 20% VAT
        const total = subtotal + taxAmount;
        
        // Update the display
        document.getElementById('energyCost').textContent = energyCost.toFixed(2) + ' €';
        document.getElementById('timeCost').textContent = timeCost.toFixed(2) + ' €';
        document.getElementById('serviceCost').textContent = serviceCost.toFixed(2) + ' €';
        document.getElementById('subscriptionDiscount').textContent = '-' + discountAmount.toFixed(2) + ' €';
        document.getElementById('subtotal').textContent = subtotal.toFixed(2) + ' €';
        document.getElementById('taxAmount').textContent = taxAmount.toFixed(2) + ' €';
        document.getElementById('totalCost').textContent = total.toFixed(2) + ' €';
        
        // Show the result
        document.getElementById('calculationResult').style.display = 'block';
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>