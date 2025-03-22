<?php
require_once '../../config.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../../auth/login.php');
    exit;
}

$pageTitle = "Gestion des Utilisateurs";
$success = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    try {
        $db = connectDB();
        
        if ($action === 'update_user') {
            $userId = $_POST['user_id'] ?? '';
            $role = $_POST['role'] ?? '';
            $isActive = isset($_POST['is_active']) ? true : false;
            
            if (empty($userId)) {
                $error = 'ID utilisateur manquant.';
            } else {
                // Update user
                $result = $db->utilisateurs->updateOne(
                    ['_id' => new MongoDB\BSON\ObjectId($userId)],
                    ['$set' => [
                        'role' => $role,
                        'actif' => $isActive
                    ]]
                );
                
                if ($result->getModifiedCount() > 0) {
                    $success = 'Utilisateur mis à jour avec succès!';
                } else {
                    $error = 'Aucune modification n\'a été apportée.';
                }
            }
        } elseif ($action === 'add_user') {
            $nom = filter_input(INPUT_POST, 'nom', FILTER_SANITIZE_STRING);
            $prenom = filter_input(INPUT_POST, 'prenom', FILTER_SANITIZE_STRING);
            $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
            $role = $_POST['role'] ?? 'utilisateur';
            $password = $_POST['password'] ?? '';
            
            if (empty($nom) || empty($prenom) || empty($email) || empty($password)) {
                $error = 'Veuillez remplir tous les champs obligatoires.';
            } else {
                // Check if email already exists
                $existingUser = $db->utilisateurs->findOne(['email' => $email]);
                
                if ($existingUser) {
                    $error = 'Un utilisateur avec cette adresse email existe déjà.';
                } else {
                    // Create new user
                    $newUser = [
                        'nom' => $nom,
                        'prenom' => $prenom,
                        'email' => $email,
                        'password' => password_hash($password, PASSWORD_DEFAULT),
                        'role' => $role,
                        'actif' => true,
                        'date_inscription' => new MongoDB\BSON\UTCDateTime(time() * 1000),
                        'vehicule' => [
                            'marque' => '',
                            'modele' => '',
                            'annee' => null,
                            'immatriculation' => ''
                        ]
                    ];
                    
                    $result = $db->utilisateurs->insertOne($newUser);
                    
                    if ($result->getInsertedCount() > 0) {
                        $success = 'Utilisateur créé avec succès!';
                    } else {
                        $error = 'Erreur lors de la création de l\'utilisateur.';
                    }
                }
            }
        }
    } catch (Exception $e) {
        $error = 'Erreur: ' . $e->getMessage();
    }
}

// Get users from database
try {
    $db = connectDB();
    $users = $db->utilisateurs->find([], ['sort' => ['date_inscription' => -1]])->toArray();
    
    // Get usage statistics for each user
    foreach ($users as &$user) {
        // Get reservations count
        $reservationsCount = $db->reservations->count([
            'utilisateur_id' => $user['_id']
        ]);
        
        // Get sessions count and total energy
        $sessionsData = $db->sessions->aggregate([
            [
                '$match' => [
                    'utilisateur_id' => $user['_id']
                ]
            ],
            [
                '$group' => [
                    '_id' => null,
                    'count' => ['$sum' => 1],
                    'totalEnergy' => ['$sum' => '$energie_consommee'],
                    'totalCost' => ['$sum' => '$cout']
                ]
            ]
        ])->toArray();
        
        $user['stats'] = [
            'reservations' => $reservationsCount,
            'sessions' => $sessionsData[0]['count'] ?? 0,
            'energy' => $sessionsData[0]['totalEnergy'] ?? 0,
            'cost' => $sessionsData[0]['totalCost'] ?? 0
        ];
    }
    
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
                <h1 class="h2">Gestion des Utilisateurs</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                        <i class="fas fa-user-plus me-1"></i> Ajouter un utilisateur
                    </button>
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
            
            <!-- Users Overview Cards -->
            <div class="row mb-4">
                <div class="col-md-3 mb-4">
                    <div class="card bg-primary text-white h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-uppercase">Total Utilisateurs</h6>
                                    <h2 class="display-6"><?php echo count($users); ?></h2>
                                </div>
                                <i class="fas fa-users fa-3x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3 mb-4">
                    <div class="card bg-success text-white h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-uppercase">Utilisateurs Actifs</h6>
                                    <h2 class="display-6">
                                        <?php 
                                            $activeUsers = array_filter($users, function($user) {
                                                return isset($user['actif']) && $user['actif'] === true;
                                            });
                                            echo count($activeUsers);
                                        ?>
                                    </h2>
                                </div>
                                <i class="fas fa-user-check fa-3x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3 mb-4">
                    <div class="card bg-info text-white h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-uppercase">Administrateurs</h6>
                                    <h2 class="display-6">
                                        <?php 
                                            $adminUsers = array_filter($users, function($user) {
                                                return isset($user['role']) && $user['role'] === 'admin';
                                            });
                                            echo count($adminUsers);
                                        ?>
                                    </h2>
                                </div>
                                <i class="fas fa-user-shield fa-3x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3 mb-4">
                    <div class="card bg-warning text-white h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-uppercase">Nouveaux (30j)</h6>
                                    <h2 class="display-6">
                                        <?php 
                                            $thirtyDaysAgo = time() - (30 * 24 * 60 * 60);
                                            $newUsers = array_filter($users, function($user) use ($thirtyDaysAgo) {
                                                return isset($user['date_inscription']) && 
                                                       $user['date_inscription']->toDateTime()->getTimestamp() > $thirtyDaysAgo;
                                            });
                                            echo count($newUsers);
                                        ?>
                                    </h2>
                                </div>
                                <i class="fas fa-user-plus fa-3x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Users Table -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Liste des utilisateurs</h5>
                    <div>
                        <input type="text" id="userSearch" class="form-control form-control-sm" placeholder="Rechercher un utilisateur...">
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover" id="usersTable">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Nom</th>
                                    <th>Email</th>
                                    <th>Rôle</th>
                                    <th>Date d'inscription</th>
                                    <th>Sessions</th>
                                    <th>Énergie</th>
                                    <th>Statut</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td><?php echo isset($user['_id']) ? $user['_id'] : 'N/A'; ?></td>
                                        <td><?php echo htmlspecialchars($user['prenom'] . ' ' . $user['nom']); ?></td>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td>
                                            <?php 
                                                $roleClass = $user['role'] === 'admin' ? 'bg-danger' : 'bg-primary';
                                                echo '<span class="badge ' . $roleClass . '">' . htmlspecialchars($user['role']) . '</span>';
                                            ?>
                                        </td>
                                        <td>
                                            <?php 
                                                echo isset($user['date_inscription']) 
                                                    ? date('d/m/Y', $user['date_inscription']->toDateTime()->getTimestamp()) 
                                                    : 'N/A';
                                            ?>
                                        </td>
                                        <td><?php echo $user['stats']['sessions']; ?></td>
                                        <td><?php echo number_format($user['stats']['energy'], 1); ?> kWh</td>
                                        <td>
                                            <?php 
                                                $statusClass = (isset($user['actif']) && $user['actif'] === true) ? 'bg-success' : 'bg-secondary';
                                                $statusText = (isset($user['actif']) && $user['actif'] === true) ? 'Actif' : 'Inactif';
                                                echo '<span class="badge ' . $statusClass . '">' . $statusText . '</span>';
                                            ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#editUserModal" 
                                                        data-user-id="<?php echo $user['_id']; ?>"
                                                        data-user-name="<?php echo htmlspecialchars($user['prenom'] . ' ' . $user['nom']); ?>"
                                                        data-user-email="<?php echo htmlspecialchars($user['email']); ?>"
                                                        data-user-role="<?php echo htmlspecialchars($user['role']); ?>"
                                                        data-user-active="<?php echo (isset($user['actif']) && $user['actif'] === true) ? '1' : '0'; ?>">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button type="button" class="btn btn-info" data-bs-toggle="modal" data-bs-target="#viewUserModal"
                                                        data-user-id="<?php echo $user['_id']; ?>"
                                                        data-user-name="<?php echo htmlspecialchars($user['prenom'] . ' ' . $user['nom']); ?>"
                                                        data-user-email="<?php echo htmlspecialchars($user['email']); ?>"
                                                        data-user-phone="<?php echo htmlspecialchars($user['telephone'] ?? 'Non renseigné'); ?>"
                                                        data-user-role="<?php echo htmlspecialchars($user['role']); ?>"
                                                        data-user-active="<?php echo (isset($user['actif']) && $user['actif'] === true) ? '1' : '0'; ?>"
                                                        data-user-registration="<?php echo isset($user['date_inscription']) ? date('d/m/Y', $user['date_inscription']->toDateTime()->getTimestamp()) : 'N/A'; ?>"
                                                        data-user-vehicle-brand="<?php echo htmlspecialchars($user['vehicule']['marque'] ?? 'Non renseigné'); ?>"
                                                        data-user-vehicle-model="<?php echo htmlspecialchars($user['vehicule']['modele'] ?? 'Non renseigné'); ?>"
                                                        data-user-vehicle-year="<?php echo htmlspecialchars($user['vehicule']['annee'] ?? 'Non renseigné'); ?>"
                                                        data-user-vehicle-plate="<?php echo htmlspecialchars($user['vehicule']['immatriculation'] ?? 'Non renseigné'); ?>"
                                                        data-user-sessions="<?php echo $user['stats']['sessions']; ?>"
                                                        data-user-reservations="<?php echo $user['stats']['reservations']; ?>"
                                                        data-user-energy="<?php echo number_format($user['stats']['energy'], 1); ?> kWh"
                                                        data-user-cost="<?php echo number_format($user['stats']['cost'], 2); ?> €">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addUserModalLabel">Ajouter un nouvel utilisateur</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="users.php" method="post">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_user">
                    
                    <div class="mb-3">
                        <label for="prenom" class="form-label">Prénom</label>
                        <input type="text" class="form-control" id="prenom" name="prenom" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="nom" class="form-label">Nom</label>
                        <input type="text" class="form-control" id="nom" name="nom" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">Mot de passe</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="role" class="form-label">Rôle</label>
                        <select class="form-select" id="role" name="role" required>
                            <option value="utilisateur">Utilisateur</option>
                            <option value="admin">Administrateur</option>
                        </select>
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

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editUserModalLabel">Modifier l'utilisateur</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="users.php" method="post">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_user">
                    <input type="hidden" name="user_id" id="edit_user_id">
                    
                    <div class="mb-3">
                        <label class="form-label">Nom</label>
                        <p class="form-control-static" id="edit_user_name"></p>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <p class="form-control-static" id="edit_user_email"></p>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_role" class="form-label">Rôle</label>
                        <select class="form-select" id="edit_role" name="role" required>
                            <option value="utilisateur">Utilisateur</option>
                            <option value="admin">Administrateur</option>
                        </select>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="edit_is_active" name="is_active">
                        <label class="form-check-label" for="edit_is_active">Compte actif</label>
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

<!-- View User Modal -->
<div class="modal fade" id="viewUserModal" tabindex="-1" aria-labelledby="viewUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewUserModalLabel">Détails de l'utilisateur</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="text-primary">Informations personnelles</h6>
                        <table class="table table-bordered">
                            <tbody>
                                <tr>
                                    <th>ID</th>
                                    <td id="view_user_id"></td>
                                </tr>
                                <tr>
                                    <th>Nom</th>
                                    <td id="view_user_name"></td>
                                </tr>
                                <tr>
                                    <th>Email</th>
                                    <td id="view_user_email"></td>
                                </tr>
                                <tr>
                                    <th>Téléphone</th>
                                    <td id="view_user_phone"></td>
                                </tr>
                                <tr>
                                    <th>Rôle</th>
                                    <td id="view_user_role"></td>
                                </tr>
                                <tr>
                                    <th>Statut</th>
                                    <td id="view_user_active"></td>
                                </tr>
                                <tr>
                                    <th>Date d'inscription</th>
                                    <td id="view_user_registration"></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-primary">Informations du véhicule</h6>
                        <table class="table table-bordered">
                            <tbody>
                                <tr>
                                    <th>Marque</th>
                                    <td id="view_user_vehicle_brand"></td>
                                </tr>
                                <tr>
                                    <th>Modèle</th>
                                    <td id="view_user_vehicle_model"></td>
                                </tr>
                                <tr>
                                    <th>Année</th>
                                    <td id="view_user_vehicle_year"></td>
                                </tr>
                                <tr>
                                    <th>Immatriculation</th>
                                    <td id="view_user_vehicle_plate"></td>
                                </tr>
                            </tbody>
                        </table>
                        
                        <h6 class="text-primary mt-4">Statistiques d'utilisation</h6>
                        <table class="table table-bordered">
                            <tbody>
                                <tr>
                                    <th>Nombre de sessions de charge</th>
                                    <td id="view_user_sessions"></td>
                                </tr>
                                <tr>
                                    <th>Nombre de réservations de sessions</th>
                                    <td id="view_user_reservations"></td>
                                </tr>
                                <tr>
                                    <th>Énergie consommée</th>
                                    <td id="view_user_energy"></td>
                                </tr>
                                <tr>
                                    <th>Montant total dépensé</th>
                                    <td id="view_user_cost"></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                <button type="button" class="btn btn-primary" id="viewUserEdit">Modifier</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // User search functionality
    document.getElementById('userSearch').addEventListener('keyup', function() {
        const searchText = this.value.toLowerCase();
        const tableRows = document.querySelectorAll('#usersTable tbody tr');
        
        tableRows.forEach(row => {
            const name = row.cells[1].textContent.toLowerCase();
            const email = row.cells[2].textContent.toLowerCase();
            
            if (name.includes(searchText) || email.includes(searchText)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    });
    
    // Edit User Modal
    const editUserModal = document.getElementById('editUserModal');
    editUserModal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        const userId = button.getAttribute('data-user-id');
        const userName = button.getAttribute('data-user-name');
        const userEmail = button.getAttribute('data-user-email');
        const userRole = button.getAttribute('data-user-role');
        const userActive = button.getAttribute('data-user-active') === '1';
        
        const modal = this;
        modal.querySelector('#edit_user_id').value = userId;
        modal.querySelector('#edit_user_name').textContent = userName;
        modal.querySelector('#edit_user_email').textContent = userEmail;
        modal.querySelector('#edit_role').value = userRole;
        modal.querySelector('#edit_is_active').checked = userActive;
    });
    
    // View User Modal
    const viewUserModal = document.getElementById('viewUserModal');
    viewUserModal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        const userId = button.getAttribute('data-user-id');
        
        // Fill in all the user details
        document.getElementById('view_user_id').textContent = userId;
        document.getElementById('view_user_name').textContent = button.getAttribute('data-user-name');
        document.getElementById('view_user_email').textContent = button.getAttribute('data-user-email');
        document.getElementById('view_user_phone').textContent = button.getAttribute('data-user-phone');
        
        // Format the role with a badge
        const userRole = button.getAttribute('data-user-role');
        const roleClass = userRole === 'admin' ? 'bg-danger' : 'bg-primary';
        document.getElementById('view_user_role').innerHTML = `<span class="badge ${roleClass}">${userRole}</span>`;
        
        // Format the active status with a badge
        const userActive = button.getAttribute('data-user-active') === '1';
        const activeClass = userActive ? 'bg-success' : 'bg-secondary';
        const activeText = userActive ? 'Actif' : 'Inactif';
        document.getElementById('view_user_active').innerHTML = `<span class="badge ${activeClass}">${activeText}</span>`;
        
        document.getElementById('view_user_registration').textContent = button.getAttribute('data-user-registration');
        document.getElementById('view_user_vehicle_brand').textContent = button.getAttribute('data-user-vehicle-brand');
        document.getElementById('view_user_vehicle_model').textContent = button.getAttribute('data-user-vehicle-model');
        document.getElementById('view_user_vehicle_year').textContent = button.getAttribute('data-user-vehicle-year');
        document.getElementById('view_user_vehicle_plate').textContent = button.getAttribute('data-user-vehicle-plate');
        document.getElementById('view_user_sessions').textContent = button.getAttribute('data-user-sessions');
        document.getElementById('view_user_reservations').textContent = button.getAttribute('data-user-reservations');
        document.getElementById('view_user_energy').textContent = button.getAttribute('data-user-energy');
        document.getElementById('view_user_cost').textContent = button.getAttribute('data-user-cost');
        
        // Set the edit button to open the edit modal for this user
        document.getElementById('viewUserEdit').addEventListener('click', function() {
            // Hide this modal
            const viewModal = bootstrap.Modal.getInstance(viewUserModal);
            viewModal.hide();
            
            // Open the edit modal
            const editModal = new bootstrap.Modal(document.getElementById('editUserModal'));
            
            // Set data attributes on the edit modal same as those on the button
            document.getElementById('edit_user_id').value = userId;
            document.getElementById('edit_user_name').textContent = button.getAttribute('data-user-name');
            document.getElementById('edit_user_email').textContent = button.getAttribute('data-user-email');
            document.getElementById('edit_role').value = userRole;
            document.getElementById('edit_is_active').checked = userActive;
            
            editModal.show();
        });
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>