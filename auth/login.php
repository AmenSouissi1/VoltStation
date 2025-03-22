<?php
require_once '../config.php';

$pageTitle = "Connexion";
$error = '';

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

// Process login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);
    
    if (empty($email) || empty($password)) {
        $error = 'Veuillez remplir tous les champs';
    } else {
        try {
            // Connect to MongoDB
            $db = connectDB();
            
            // Find user by email
            $user = $db->utilisateurs->findOne(['email' => $email]);
            
            // Check if user exists and has a password field
            if (!$user) {
                $error = 'Email ou mot de passe incorrect';
            } else if (!isset($user['password']) || empty($user['password'])) {
                $error = 'Erreur d\'authentification. Veuillez contacter l\'administrateur.';
                error_log("User {$email} has no password field set in database");
            } else if (password_verify($password, $user['password'])) {
                // Set session variables
                $_SESSION['user_id'] = (string)$user['_id'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_name'] = $user['prenom'] . ' ' . $user['nom'];
                $_SESSION['user_role'] = $user['role'] ?? 'user';
                
                // Set remember me cookie if checked
                if ($remember) {
                    $token = bin2hex(random_bytes(32));
                    $expiry = time() + 30 * 24 * 60 * 60; // 30 days
                    
                    // Store token in database
                    $db->utilisateurs->updateOne(
                        ['_id' => $user['_id']],
                        ['$set' => [
                            'remember_token' => $token,
                            'token_expiry' => new MongoDB\BSON\UTCDateTime($expiry * 1000)
                        ]]
                    );
                    
                    // Set cookie
                    setcookie('remember_token', $token, $expiry, '/', '', false, true);
                }
                
                // Redirect based on role
                if ($_SESSION['user_role'] === 'admin') {
                    header('Location: ../pages/admin/dashboard.php');
                } else {
                    header('Location: ../pages/user/dashboard.php');
                }
                exit;
            } else {
                $error = 'Email ou mot de passe incorrect';
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
        <div class="col-md-6">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h3 class="card-title mb-0">Connexion</h3>
                </div>
                <div class="card-body">
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger" role="alert">
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>
                    
                    <form action="login.php" method="post">
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Mot de passe</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="remember" name="remember">
                            <label class="form-check-label" for="remember">Se souvenir de moi</label>
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">Se connecter</button>
                        </div>
                    </form>
                    
                </div>
                <div class="card-footer text-center">
                    <p class="mb-0">Vous n'avez pas de compte? <a href="register.php">Inscrivez-vous</a></p>
                </div>
            </div>
        </div>
    </div>
</main>

<?php require_once '../includes/footer.php'; ?>