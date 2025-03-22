<?php
require_once '../config.php';

$pageTitle = "Inscription";
$error = '';
$success = '';

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    $userRole = $_SESSION['user_role'] ?? 'user';
    
    if ($userRole === 'admin') {
        header('Location: ../pages/admin/dashboard.php');
    } else {
        header('Location: ../pages/user/dashboard.php');
    }
    exit;
}

// Process registration form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = filter_input(INPUT_POST, 'nom', FILTER_SANITIZE_STRING);
    $prenom = filter_input(INPUT_POST, 'prenom', FILTER_SANITIZE_STRING);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $vehicule_marque = filter_input(INPUT_POST, 'vehicule_marque', FILTER_SANITIZE_STRING);
    $vehicule_modele = filter_input(INPUT_POST, 'vehicule_modele', FILTER_SANITIZE_STRING);
    $vehicule_batterie = filter_input(INPUT_POST, 'vehicule_batterie', FILTER_VALIDATE_FLOAT);
    
    // Validate input
    if (empty($nom) || empty($prenom) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = 'Veuillez remplir tous les champs obligatoires';
    } elseif ($password !== $confirm_password) {
        $error = 'Les mots de passe ne correspondent pas';
    } elseif (strlen($password) < 8) {
        $error = 'Le mot de passe doit contenir au moins 8 caractères';
    } else {
        try {
            // Connect to MongoDB
            $db = connectDB();
            
            // Check if email already exists
            $existingUser = $db->utilisateurs->findOne(['email' => $email]);
            if ($existingUser) {
                $error = 'Cet email est déjà utilisé';
            } else {
                // Hash password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Create user object
                $newUser = [
                    'nom' => $nom,
                    'prenom' => $prenom,
                    'email' => $email,
                    'password' => $hashed_password,
                    'role' => 'user', // Default role is user
                    'vehicule' => [
                        'marque' => $vehicule_marque,
                        'modele' => $vehicule_modele,
                        'batterie' => (float)$vehicule_batterie
                    ],
                    'historique_recharges' => [],
                    'date_creation' => new MongoDB\BSON\UTCDateTime()
                ];
                
                // Insert user into database
                $result = $db->utilisateurs->insertOne($newUser);
                
                if ($result->getInsertedCount() > 0) {
                    $success = 'Inscription réussie! Vous pouvez maintenant vous connecter.';
                    
                    // Auto-login option
                    // $_SESSION['user_id'] = (string)$result->getInsertedId();
                    // $_SESSION['user_email'] = $email;
                    // $_SESSION['user_name'] = $prenom . ' ' . $nom;
                    // $_SESSION['user_role'] = 'user';
                    // 
                    // header('Location: ../pages/user/dashboard.php');
                    // exit;
                } else {
                    $error = 'Erreur lors de l\'inscription';
                }
            }
        } catch (Exception $e) {
            $error = 'Erreur de connexion à la base de données';
            error_log($e->getMessage());
        }
    }
}

// Include the header
require_once '../includes/header.php';
?>

<main class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h3 class="card-title mb-0">Inscription</h3>
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
                            <p class="mt-2 mb-0">
                                <a href="login.php" class="btn btn-primary btn-sm">Se connecter</a>
                            </p>
                        </div>
                    <?php else: ?>
                        <form action="register.php" method="post" novalidate>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="prenom" class="form-label">Prénom <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="prenom" name="prenom" required 
                                           value="<?php echo isset($prenom) ? htmlspecialchars($prenom) : ''; ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="nom" class="form-label">Nom <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="nom" name="nom" required
                                           value="<?php echo isset($nom) ? htmlspecialchars($nom) : ''; ?>">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="email" name="email" required
                                       value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>">
                                <div class="form-text">
                                    Cet email sera utilisé pour vous connecter et recevoir des notifications.
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="password" class="form-label">Mot de passe <span class="text-danger">*</span></label>
                                    <input type="password" class="form-control" id="password" name="password" minlength="8" required>
                                    <div class="form-text">
                                        Au moins 8 caractères.
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="confirm_password" class="form-label">Confirmer le mot de passe <span class="text-danger">*</span></label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                </div>
                            </div>
                            
                            <hr>
                            <h5>Information sur votre véhicule</h5>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="vehicule_marque" class="form-label">Marque du véhicule</label>
                                    <input type="text" class="form-control" id="vehicule_marque" name="vehicule_marque"
                                           value="<?php echo isset($vehicule_marque) ? htmlspecialchars($vehicule_marque) : ''; ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="vehicule_modele" class="form-label">Modèle du véhicule</label>
                                    <input type="text" class="form-control" id="vehicule_modele" name="vehicule_modele"
                                           value="<?php echo isset($vehicule_modele) ? htmlspecialchars($vehicule_modele) : ''; ?>">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="vehicule_batterie" class="form-label">Capacité de la batterie (kWh)</label>
                                <input type="number" class="form-control" id="vehicule_batterie" name="vehicule_batterie" step="0.1" min="0"
                                       value="<?php echo isset($vehicule_batterie) ? htmlspecialchars($vehicule_batterie) : ''; ?>">
                            </div>
                            
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="terms" name="terms" required>
                                <label class="form-check-label" for="terms">
                                    J'accepte les <a href="#">conditions d'utilisation</a> et la <a href="#">politique de confidentialité</a>
                                </label>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">S'inscrire</button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
                <div class="card-footer text-center">
                    <p class="mb-0">Vous avez déjà un compte? <a href="login.php">Connectez-vous</a></p>
                </div>
            </div>
        </div>
    </div>
</main>

<?php require_once '../includes/footer.php'; ?>