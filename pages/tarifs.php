<?php
require_once '../config.php';

$pageTitle = "Nos tarifs";

// Récupérer les tarifs depuis la base de données
try {
    $db = connectDB();
    $tarifications = $db->tarifications->find()->toArray();
} catch (Exception $e) {
    $error = 'Erreur de connexion à la base de données: ' . $e->getMessage();
    $tarifications = [];
}

require_once '../includes/header.php';
?>

<main class="container mt-5">
    <div class="row mb-4">
        <div class="col-12">
            <h1 class="text-center mb-4">Nos tarifs de recharge</h1>
            <p class="lead text-center">Des tarifs transparents et adaptés à tous les besoins</p>
        </div>
    </div>
    
    <div class="row justify-content-center mb-5">
        <?php if (empty($tarifications)): ?>
        <div class="col-md-8">
            <div class="alert alert-info">
                Nos tarifs sont temporairement indisponibles. Veuillez nous excuser pour ce désagrément.
            </div>
        </div>
        <?php else: ?>
            <?php 
            // Trier les tarifications par type (standard, rapide, ultra-rapide)
            usort($tarifications, function($a, $b) {
                $order = [
                    'Standard' => 1,
                    'Rapide' => 2,
                    'Ultra-rapide' => 3
                ];
                $aOrder = $order[$a['type_borne']] ?? 99;
                $bOrder = $order[$b['type_borne']] ?? 99;
                return $aOrder - $bOrder;
            });
            
            // Définir les classes de couleur pour chaque type
            $colorClasses = [
                'Standard' => 'bg-primary',
                'Rapide' => 'bg-success',
                'Ultra-rapide' => 'bg-danger'
            ];
            
            foreach ($tarifications as $tarif): 
                $colorClass = $colorClasses[$tarif['type_borne']] ?? 'bg-secondary';
            ?>
            <div class="col-md-4 mb-4">
                <div class="card h-100 shadow">
                    <div class="card-header <?php echo $colorClass; ?> text-white">
                        <h5 class="card-title mb-0">Borne <?php echo htmlspecialchars($tarif['type_borne']); ?></h5>
                    </div>
                    <div class="card-body">
                        <div class="text-center mb-4">
                            <span class="display-4"><?php echo number_format($tarif['prix_kwh'], 2); ?> €</span>
                            <p class="text-muted">par kWh</p>
                        </div>
                        
                        <ul class="list-group list-group-flush mb-4">
                            <?php if (isset($tarif['prix_minute']) && $tarif['prix_minute'] > 0): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Coût par minute
                                <span class="badge bg-secondary rounded-pill"><?php echo number_format($tarif['prix_minute'], 2); ?> €</span>
                            </li>
                            <?php endif; ?>
                            <?php if (isset($tarif['frais_service']) && $tarif['frais_service'] > 0): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Frais de service
                                <span class="badge bg-secondary rounded-pill"><?php echo number_format($tarif['frais_service'], 2); ?> €</span>
                            </li>
                            <?php endif; ?>
                            <?php if (isset($tarif['puissance']) && $tarif['puissance'] > 0): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Puissance max
                                <span class="badge bg-primary rounded-pill"><?php echo $tarif['puissance']; ?> kW</span>
                            </li>
                            <?php endif; ?>
                        </ul>
                        
                        <div class="text-center">
                            <a href="<?php echo APP_URL; ?>/pages/stations.php" class="btn btn-outline-primary">Trouver une borne</a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <div class="row justify-content-center mb-5">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-light">
                    <h5 class="card-title mb-0">Abonnements et réductions</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Abonnement Mensuel</h6>
                            <p>9,90 € / mois</p>
                            <ul>
                                <li>Réduction de 10% sur tous les tarifs</li>
                                <li>Pas de frais de service</li>
                                <li>Priorité de réservation</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6>Abonnement Annuel</h6>
                            <p>99 € / an (économisez 19,80 €)</p>
                            <ul>
                                <li>Réduction de 15% sur tous les tarifs</li>
                                <li>Pas de frais de service</li>
                                <li>Priorité de réservation</li>
                                <li>Support client premium</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-light">
                    <h5 class="card-title mb-0">Questions fréquentes</h5>
                </div>
                <div class="card-body">
                    <div class="accordion" id="accordionFAQ">
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingOne">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne">
                                    Comment sont facturées les recharges ?
                                </button>
                            </h2>
                            <div id="collapseOne" class="accordion-collapse collapse" aria-labelledby="headingOne" data-bs-parent="#accordionFAQ">
                                <div class="accordion-body">
                                    Les recharges sont facturées selon le volume d'énergie consommée (kWh) et, dans certains cas, selon le temps d'occupation de la borne (minutes).
                                    Les frais de service sont appliqués une fois par session, sauf pour les abonnés.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingTwo">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo">
                                    Quels moyens de paiement acceptez-vous ?
                                </button>
                            </h2>
                            <div id="collapseTwo" class="accordion-collapse collapse" aria-labelledby="headingTwo" data-bs-parent="#accordionFAQ">
                                <div class="accordion-body">
                                    Nous acceptons les cartes bancaires (Visa, MasterCard, American Express), les paiements mobiles (Apple Pay, Google Pay) et les paiements via notre application mobile.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingThree">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseThree">
                                    Comment réserver une borne de recharge ?
                                </button>
                            </h2>
                            <div id="collapseThree" class="accordion-collapse collapse" aria-labelledby="headingThree" data-bs-parent="#accordionFAQ">
                                <div class="accordion-body">
                                    Vous pouvez réserver une borne de recharge via notre application mobile ou sur notre site web. La réservation garantit la disponibilité de la borne pendant 15 minutes à partir de l'heure spécifiée.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php require_once '../includes/footer.php'; ?>