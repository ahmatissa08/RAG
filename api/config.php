<?php
// api/config.php (Version finale complète)
require_once __DIR__ . '/../vendor/autoload.php';
// == LOGGING POUR DEBUG ==
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/chatbot.log'); // Crée le dossier logs/ manuellement si besoin

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();
// --- CONNEXION À LA BASE DE DONNÉES ---
define('DB_HOST', '127.0.0.1');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'cha_db');

function connect_db() {
    $conn = new mysqli('localhost', 'root', '', 'cha_db', null, '/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock');
    if ($conn->connect_error) {
        die("Échec de la connexion à la base de données: " . $conn->connect_error);
    }
    $conn->set_charset("utf8mb4");
    return $conn;
}

// --- SÉCURITÉ JWT ---
define('JWT_SECRET', 'c7b31aaaa8591ff29c8d0e77029e51006295ac608fd9a27900d962680b8ae1cf13ddb227ec67d0b3cc3415099b5bddf4b1c68d07bc1ef142c4baf13b016f511a');

// --- CHEMINS VERS LES SCRIPTS PYTHON ---
// !! VÉRIFIEZ BIEN QUE CES CHEMINS SONT CORRECTS SUR VOTRE ORDINATEUR !!
define('EMBEDDER_PATH', __DIR__ . '/../embedder.py');
define('EMBEDDER_BATCH_PATH', __DIR__ . '/../embedder_batch.py');
?>