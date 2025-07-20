<?php
// api/auth.php
header('Content-Type: application/json');

require_once 'config.php';
require_once __DIR__ . '/../vendor/autoload.php';

use \Firebase\JWT\JWT;

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['action'])) {
    echo json_encode(["success" => false, "message" => "Action non spécifiée."]);
    exit();
}

$conn = connect_db();

// --- GESTION DE L'INSCRIPTION ---
if ($data['action'] === 'register') {
    $name = $data['name'];
    $email = $data['email'];
    $phone = $data['phone'];
    $type = $data['type'];
    $password = $data['password'];

    // Hasher le mot de passe est une étape de sécurité CRUCIALE
    $hashed_password = password_hash($password, PASSWORD_BCRYPT);

    $stmt = $conn->prepare("INSERT INTO users (name, email, phone, user_type, password) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $name, $email, $phone, $type, $hashed_password);

    if ($stmt->execute()) {
        echo json_encode(["success" => true, "message" => "Inscription réussie."]);
    } else {
        echo json_encode(["success" => false, "message" => "Erreur lors de l'inscription: " . $stmt->error]);
    }
    $stmt->close();
}

// --- GESTION DE LA CONNEXION ---
if ($data['action'] === 'login') {
    $email = $data['email'];
    $password = $data['password'];

    $stmt = $conn->prepare("SELECT id, name, email, user_type, password FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        if (password_verify($password, $user['password'])) {
            // Mot de passe correct, on génère le Token JWT
            $issuedat_claim = time();
            $expire_claim = $issuedat_claim + (3600 * 24); // Expire dans 24 heures

            $token_payload = [
                "iss" => "localhost",
                "iat" => $issuedat_claim,
                "exp" => $expire_claim,
                "data" => ["id" => $user['id']]
            ];

            $jwt = JWT::encode($token_payload, JWT_SECRET, 'HS256');

            echo json_encode([
                "success" => true,
                "message" => "Connexion réussie.",
                "user" => [
                    "id" => $user['id'],
                    "name" => $user['name'],
                    "email" => $user['email'],
                    "type" => $user['user_type'],
                    "token" => $jwt // On envoie le vrai token !
                ]
            ]);
        } else {
            echo json_encode(["success" => false, "message" => "Email ou mot de passe incorrect."]);
        }
    } else {
        echo json_encode(["success" => false, "message" => "Email ou mot de passe incorrect."]);
    }
    $stmt->close();
}

$conn->close();
?>