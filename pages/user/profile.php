<?php
require_once '../../config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../auth/login.php');
    exit;
}

$pageTitle = "Mon Profil";
$success = '';
$error = '';

// Get user data from database
try {
    $db = connectDB();
    $userId = $_SESSION['user_id'];
    
    // Find user by ID
    $user = $db->utilisateurs->findOne(['_id' => new MongoDB\BSON\ObjectId($userId)]);
    
    if (!$user) {
        $error = 'Utilisateur non trouvé.';
    }
} catch (Exception $e) {
    $error = 'Erreur de connexion à la base de données.';
    error_log($e->getMessage());
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate inputs
    $nom = filter_input(INPUT_POST, 'nom', FILTER_SANITIZE_STRING);
    $prenom = filter_input(INPUT_POST, 'prenom', FILTER_SANITIZE_STRING);
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    $telephone = filter_input(INPUT_POST, 'telephone', FILTER_SANITIZE_STRING);
    $marque = filter_input(INPUT_POST, 'marque', FILTER_SANITIZE_STRING);
    $modele = filter_input(INPUT_POST, 'modele', FILTER_SANITIZE_STRING);
    $annee = filter_input(INPUT_POST, 'annee', FILTER_VALIDATE_INT);
    $immatriculation = filter_input(INPUT_POST, 'immatriculation', FILTER_SANITIZE_STRING);
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    
    // Verify all required fields are filled
    if (empty($nom) || empty($prenom) || empty($email) || empty($telephone)) {
        $error = 'Veuillez remplir tous les champs obligatoires.';
    } 
    // Verify email is valid
    elseif (!$email) {
        $error = 'Veuillez fournir une adresse email valide.';
    }
    // If password is provided, verify it matches confirmation
    elseif (!empty($password) && $password !== $password_confirm) {
        $error = 'Les mots de passe ne correspondent pas.';
    } 
    else {
        try {
            // Update user data
            $updateData = [
                'nom' => $nom,
                'prenom' => $prenom,
                'email' => $email,
                'telephone' => $telephone,
                'vehicule' => [
                    'marque' => $marque,
                    'modele' => $modele,
                    'annee' => $annee,
                    'immatriculation' => $immatriculation
                ]
            ];
            
            // Add password if it was provided
            if (!empty($password)) {
                $updateData['password'] = password_hash($password, PASSWORD_DEFAULT);
            }
            
            // Execute update
            $result = $db->utilisateurs->updateOne(
                ['_id' => new MongoDB\BSON\ObjectId($userId)],
                ['$set' => $updateData]
            );
            
            if ($result->getModifiedCount() > 0) {
                $success = 'Profil mis à jour avec succès!';
                
                // Update session data
                $_SESSION['user_name'] = $prenom . ' ' . $nom;
                
                // Refresh user data
                $user = $db->utilisateurs->findOne(['_id' => new MongoDB\BSON\ObjectId($userId)]);
            } else {
                $error = 'Aucune modification n\'a été apportée.';
            }
        } catch (Exception $e) {
            $error = 'Erreur lors de la mise à jour du profil.';
            error_log($e->getMessage());
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
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">Mon Profil</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger">
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($success)): ?>
                        <div class="alert alert-success">
                            <?php echo htmlspecialchars($success); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($user)): ?>
                        <form method="post" action="profile.php">
                            <h5 class="mb-3">Informations personnelles</h5>
                            <div class="row mb-4">
                                <div class="col-md-6 mb-3">
                                    <label for="prenom" class="form-label">Prénom</label>
                                    <input type="text" class="form-control" id="prenom" name="prenom" value="<?php echo htmlspecialchars($user['prenom'] ?? ''); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="nom" class="form-label">Nom</label>
                                    <input type="text" class="form-control" id="nom" name="nom" value="<?php echo htmlspecialchars($user['nom'] ?? ''); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="telephone" class="form-label">Téléphone</label>
                                    <input type="tel" class="form-control" id="telephone" name="telephone" value="<?php echo htmlspecialchars($user['telephone'] ?? ''); ?>" required>
                                </div>
                            </div>
                            
                            <h5 class="mb-3">Informations du véhicule</h5>
                            <div class="row mb-4">
                                <div class="col-md-6 mb-3">
                                    <label for="marque" class="form-label">Marque</label>
                                    <input type="text" class="form-control" id="marque" name="marque" value="<?php echo htmlspecialchars($user['vehicule']['marque'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="modele" class="form-label">Modèle</label>
                                    <input type="text" class="form-control" id="modele" name="modele" value="<?php echo htmlspecialchars($user['vehicule']['modele'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="annee" class="form-label">Année</label>
                                    <input type="number" class="form-control" id="annee" name="annee" value="<?php echo htmlspecialchars($user['vehicule']['annee'] ?? ''); ?>" min="1990" max="<?php echo date('Y'); ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="immatriculation" class="form-label">Immatriculation</label>
                                    <input type="text" class="form-control" id="immatriculation" name="immatriculation" value="<?php echo htmlspecialchars($user['vehicule']['immatriculation'] ?? ''); ?>">
                                </div>
                            </div>
                            
                            <h5 class="mb-3">Changer le mot de passe</h5>
                            <div class="row mb-4">
                                <div class="col-md-6 mb-3">
                                    <label for="password" class="form-label">Nouveau mot de passe</label>
                                    <input type="password" class="form-control" id="password" name="password">
                                    <div class="form-text">Laissez vide pour conserver le mot de passe actuel</div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="password_confirm" class="form-label">Confirmer le mot de passe</label>
                                    <input type="password" class="form-control" id="password_confirm" name="password_confirm">
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i> Enregistrer les modifications
                                </button>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="alert alert-warning">
                            Impossible de récupérer les informations de l'utilisateur.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<?php require_once '../../includes/footer.php'; ?>