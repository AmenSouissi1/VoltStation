<?php
// Inclusion de l'autoloader Composer
require_once __DIR__ . '/vendor/autoload.php';

// Configuration de la base de données
define('DB_HOST', 'localhost');
define('DB_PORT', '27017');
define('DB_NAME', 'voltstation');
define('DB_USER', '');
define('DB_PASS', '');

// Configuration de l'application
define('APP_NAME', 'VoltStation');
define('APP_URL', 'http://voltstation.localhost');
define('APP_VERSION', '1.0.0');

// Authentification JWT
define('JWT_SECRET', 'volt_secret_key_change_in_production');
define('JWT_EXPIRY', 3600); // 1 heure

// Configuration de session
session_start();

// Fonction de connexion à MongoDB
function connectDB() {
    try {
        $connectionString = 'mongodb://'.DB_HOST.':'.DB_PORT;
        $client = new MongoDB\Client($connectionString);
        return $client->selectDatabase(DB_NAME);
    } catch (Exception $e) {
        die("Échec de connexion à la base de données: " . $e->getMessage());
    }
}

// Gestion des erreurs
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Fuseau horaire
date_default_timezone_set('Europe/Paris');
?>