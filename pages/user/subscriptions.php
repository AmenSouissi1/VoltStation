<?php
require_once '../../config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../auth/login.php');
    exit;
}

$pageTitle = "Gérer mon abonnement";

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
    
    // Récupération des plans d'abonnement depuis la base de données
    try {
        $db = connectDB();
        $abonnementsCollection = $db->abonnements;
        $cursor = $abonnementsCollection->find();
        $subscriptionPlans = [];
        
        foreach ($cursor as $plan) {
            $subscriptionPlans[] = [
                'id' => $plan['id'] ?? $plan['type'] ?? (string)$plan['_id'],
                'nom' => $plan['nom'] ?? $plan['type'] ?? 'Standard',
                'prix' => $plan['prix'] ?? 19.99,
                'description' => $plan['description'] ?? 'Accès privilégié et réduction sur tous les tarifs',
                'avantages' => $plan['avantages'] ?? [
                    'Réservation de sessions prioritaire',
                    'Accès à l\'historique détaillé',
                    'Support client'
                ],
                'couleur' => $plan['couleur'] ?? 'primary',
                'recommande' => $plan['recommande'] ?? false
            ];
        }
        
        // Si aucun plan n'existe en base de données, utiliser des plans par défaut
        if (empty($subscriptionPlans)) {
            $subscriptionPlans = [
                [
                    'id' => 'standard (bug bdd)',
                    'nom' => 'Standard (bug bdd)',
                    'prix' => 19.99,
                    'description' => 'Accès privilégié et réduction de 10% sur tous les tarifs',
                    'avantages' => [
                        'Réservation de sessions prioritaire',
                        'Réduction de 10% sur tous les tarifs',
                        'Accès à l\'historique détaillé',
                        'Support client par email et téléphone'
                    ],
                    'couleur' => 'success',
                    'recommande' => true
                ]
            ];
        }
    } catch (Exception $e) {
        error_log("Erreur lors de la récupération des plans d'abonnement: " . $e->getMessage());
        // Plan par défaut en cas d'erreur
        $subscriptionPlans = [
            [
                'id' => 'standard (bug bdd)',
                    'nom' => 'Standard (bug bdd)',
                'prix' => 19.99,
                'description' => 'Accès privilégié et réduction de 10% sur tous les tarifs',
                'avantages' => [
                    'Réservation de sessions prioritaire',
                    'Réduction de 10% sur tous les tarifs',
                    'Accès à l\'historique détaillé',
                    'Support client par email et téléphone'
                ],
                'couleur' => 'success',
                'recommande' => true
            ]
        ];
    }
    
    // Process subscription change if form submitted
    $successMessage = '';
    $errorMessage = '';
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['subscribe'])) {
        $newPlanId = $_POST['plan_id'] ?? '';
        
        if (empty($newPlanId)) {
            $errorMessage = 'Veuillez sélectionner un abonnement valide.';
        } else {
            // Find the selected plan
            $selectedPlan = null;
            foreach ($subscriptionPlans as $plan) {
                if ($plan['id'] === $newPlanId) {
                    $selectedPlan = $plan;
                    break;
                }
            }
            
            if ($selectedPlan) {
                // Calculate subscription dates
                $today = new DateTime();
                $endDate = clone $today;
                $endDate->modify('+30 days');
                
                // Add a transaction record for this subscription change
                try {
                    $db->abonnement_transactions->insertOne([
                        'utilisateur_id' => new MongoDB\BSON\ObjectId($user_id),
                        'date' => new MongoDB\BSON\UTCDateTime($today->getTimestamp() * 1000),
                        'montant' => $selectedPlan['prix'],
                        'description' => 'Changement d\'abonnement vers ' . $selectedPlan['nom'],
                        'statut' => 'Payé'
                    ]);
                } catch (Exception $e) {
                    error_log("Error creating subscription transaction: " . $e->getMessage());
                    // Continue anyway, this is not critical
                }
                
                // Update user subscription in database
                try {
                    $result = $userCollection->updateOne(
                        ['_id' => new MongoDB\BSON\ObjectId($user_id)],
                        ['$set' => [
                            'abonnement' => [
                                'type' => $newPlanId,
                                'nom' => $selectedPlan['nom'],
                                'prix' => $selectedPlan['prix'],
                                'date_debut' => $today->format('Y-m-d'),
                                'date_fin' => $endDate->format('Y-m-d'),
                                'statut' => 'actif'
                            ]
                        ]]
                    );
                    
                    // Log the operation for debugging
                    error_log("MongoDB update operation for user {$user_id} - Result: " . ($result->getModifiedCount() > 0 ? 'Success' : 'No changes'));
                } catch (Exception $e) {
                    error_log("MongoDB error when updating subscription: " . $e->getMessage());
                    $result = null;
                }
                
                if ($result && $result->getModifiedCount() > 0) {
                    $successMessage = 'Votre abonnement a été mis à jour avec succès.';
                    
                    // Update the user data after modification
                    $user = $userCollection->findOne(['_id' => new MongoDB\BSON\ObjectId($user_id)]);
                } else if ($result && $result->getMatchedCount() > 0) {
                    // Document was matched but not modified (potentially same data)
                    $successMessage = 'Abonnement confirmé.';
                    $user = $userCollection->findOne(['_id' => new MongoDB\BSON\ObjectId($user_id)]);
                } else {
                    $errorMessage = 'Une erreur est survenue lors de la mise à jour de votre abonnement.';
                }
            } else {
                $errorMessage = 'Abonnement sélectionné invalide.';
            }
        }
    }
    
    // Get user's current subscription
    $currentSubscription = isset($user['abonnement']) ? $user['abonnement'] : [
        'type' => 'aucun',
        'nom' => 'Aucun abonnement',
        'date_debut' => '',
        'date_fin' => '',
        'statut' => 'inactif'
    ];
    
    // Format subscription dates
    $dateDebut = !empty($currentSubscription['date_debut']) ? 
        date('d/m/Y', strtotime($currentSubscription['date_debut'])) : 'N/A';
    
    $dateFin = !empty($currentSubscription['date_fin']) ? 
        date('d/m/Y', strtotime($currentSubscription['date_fin'])) : 'N/A';
    
    // Récupération de l'historique des transactions depuis la collection abonnement_transactions
    try {
        // Vérifier si la collection des transactions existe
        $transactionsCollection = $db->abonnement_transactions;
        $transactionsCount = $transactionsCollection->countDocuments(['utilisateur_id' => new MongoDB\BSON\ObjectId($user_id)]);
        
        // Get all transactions for this user
        $transactions = $transactionsCollection->find(
            ['utilisateur_id' => new MongoDB\BSON\ObjectId($user_id)],
            ['sort' => ['date' => -1]]
        )->toArray();
    } catch (Exception $e) {
        error_log("Error accessing abonnement_transactions: " . $e->getMessage());
        // Fallback to sample data
        $transactions = [
            [
                'date' => new MongoDB\BSON\UTCDateTime(strtotime('-30 days') * 1000),
                'montant' => $currentSubscription['prix'] ?? 19.99,
                'description' => 'Renouvellement abonnement ' . ($currentSubscription['nom'] ?? 'Standard'),
                'statut' => 'Payé'
            ],
            [
                'date' => new MongoDB\BSON\UTCDateTime(strtotime('-60 days') * 1000),
                'montant' => $currentSubscription['prix'] ?? 19.99,
                'description' => 'Renouvellement abonnement ' . ($currentSubscription['nom'] ?? 'Standard'),
                'statut' => 'Payé'
            ]
        ];
    }
    
} catch (Exception $e) {
    // Log the error but don't expose details to the user
    error_log("Database error: " . $e->getMessage());
    
    // Provide fallback data
    $errorMessage = "Une erreur est survenue lors de la récupération de vos données d'abonnement.";
    $user = [];
    $subscriptionPlans = [];
    $currentSubscription = [
        'type' => 'aucun',
        'nom' => 'Aucun abonnement',
        'date_debut' => '',
        'date_fin' => '',
        'statut' => 'inactif'
    ];
    $dateDebut = 'N/A';
    $dateFin = 'N/A';
    $transactions = [];
}

// Include header
require_once '../../includes/header.php';
?>

<main class="container mt-4">
    <div class="row">
        <?php include_once '../../includes/user_sidebar.php'; ?>
        
        <!-- Main content -->
        <div class="col-md-9">
            <?php if (!empty($successMessage)): ?>
                <div class="alert alert-success" role="alert">
                    <?php echo htmlspecialchars($successMessage); ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($errorMessage)): ?>
                <div class="alert alert-danger" role="alert">
                    <?php echo htmlspecialchars($errorMessage); ?>
                </div>
            <?php endif; ?>
            
            <!-- Current Subscription -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">Mon abonnement actuel</h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-info mb-3">
                        <i class="fas fa-info-circle me-2"></i> Ce module de gestion d'abonnements est une simulation pour ce projet scolaire. Aucun paiement réel n'est traité.
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <h5><?php echo htmlspecialchars($currentSubscription['nom'] ?? 'Aucun abonnement'); ?></h5>
                            <p>
                                <?php if (($currentSubscription['statut'] ?? '') === 'actif'): ?>
                                    <span class="badge bg-success">Actif</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Inactif</span>
                                <?php endif; ?>
                            </p>
                            <p>Début: <?php echo $dateDebut; ?></p>
                            <p>Expiration: <?php echo $dateFin; ?></p>
                            <?php if (isset($currentSubscription['prix'])): ?>
                                <p>Prix mensuel: <?php echo number_format($currentSubscription['prix'], 2); ?> €</p>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6 text-end">
                            <p>Vous pouvez changer d'abonnement à tout moment.</p>
                            <a href="#subscription-plans" class="btn btn-primary">
                                Modifier mon abonnement
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Subscription Plans -->
            <div class="card mb-4" id="subscription-plans">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">Formules d'abonnement</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($subscriptionPlans as $plan): ?>
                            <div class="col-md-4 mb-4">
                                <div class="card h-100 <?php echo !empty($plan['recommande']) ? 'border-success' : ''; ?>">
                                    <?php if (!empty($plan['recommande'])): ?>
                                        <div class="card-header bg-success text-white text-center">
                                            <strong>Recommandé</strong>
                                        </div>
                                    <?php endif; ?>
                                    <div class="card-body d-flex flex-column">
                                        <h5 class="card-title"><?php echo htmlspecialchars($plan['nom']); ?></h5>
                                        <h2 class="text-<?php echo $plan['couleur']; ?> mb-3"><?php echo number_format($plan['prix'], 2); ?> €<small class="text-muted">/mois</small></h2>
                                        <p class="card-text"><?php echo htmlspecialchars($plan['description']); ?></p>
                                        <ul class="list-group list-group-flush mb-4">
                                            <?php foreach ($plan['avantages'] as $avantage): ?>
                                                <li class="list-group-item border-0 ps-0">
                                                    <i class="fas fa-check text-success me-2"></i> <?php echo htmlspecialchars($avantage); ?>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                        <div class="mt-auto">
                                            <form method="post" action="subscriptions.php">
                                                <input type="hidden" name="plan_id" value="<?php echo $plan['id']; ?>">
                                                <?php if (($currentSubscription['type'] ?? '') === $plan['id']): ?>
                                                    <button type="button" class="btn btn-outline-secondary w-100" disabled>
                                                        Abonnement actuel
                                                    </button>
                                                <?php else: ?>
                                                    <button type="submit" name="subscribe" class="btn btn-<?php echo $plan['couleur']; ?> w-100">
                                                        Choisir cette formule
                                                    </button>
                                                <?php endif; ?>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <!-- Transaction History -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">Historique des paiements</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($transactions)): ?>
                        <p class="text-center text-muted my-4">Aucun paiement enregistré</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Description</th>
                                        <th>Montant</th>
                                        <th>Statut</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($transactions as $transaction): ?>
                                        <tr>
                                            <td>
                                                <?php 
                                                    // Handle MongoDB UTCDateTime or string date formats
                                                    if ($transaction['date'] instanceof MongoDB\BSON\UTCDateTime) {
                                                        echo $transaction['date']->toDateTime()->format('d/m/Y');
                                                    } else if (is_string($transaction['date'])) {
                                                        echo date('d/m/Y', strtotime($transaction['date']));
                                                    } else {
                                                        echo date('d/m/Y');
                                                    }
                                                ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($transaction['description']); ?></td>
                                            <td><?php echo number_format($transaction['montant'], 2); ?> €</td>
                                            <td>
                                                <?php if ($transaction['statut'] === 'Payé'): ?>
                                                    <span class="badge bg-success">Payé</span>
                                                <?php elseif ($transaction['statut'] === 'En attente'): ?>
                                                    <span class="badge bg-warning text-dark">En attente</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Échoué</span>
                                                <?php endif; ?>
                                            </td>
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