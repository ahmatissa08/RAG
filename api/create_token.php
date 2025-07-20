<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once 'config.php';

use \Firebase\JWT\JWT;

$payload = [
    "iss" => "localhost",               // émetteur du token
    "iat" => time(),                   // date de création
    "exp" => time() + 3600 * 24 * 7,   // expiration dans 7 jours
    "data" => [
        "id" => 1                      // identifiant de l'utilisateur (adapter si besoin)
    ]
];

$token = JWT::encode($payload, JWT_SECRET, 'HS256');

echo "Votre token JWT valide :<br><textarea style='width:100%; height:100px;'>$token</textarea>";
?>
