<?php
// api/users_manager.php (Version CRUD Complète)

header('Content-Type: application/json');
require_once 'config.php';
require_once 'functions.php';

// Sécurité : On vérifie que l'utilisateur est un administrateur
$userId = get_user_id_from_token();
if ($userId === null) { http_response_code(401); exit(json_encode(['success' => false, 'message' => 'Authentification requise.'])); }

$conn = connect_db();
$stmt_check_role = $conn->prepare("SELECT user_type FROM users WHERE id = ?");
$stmt_check_role->bind_param("i", $userId);
$stmt_check_role->execute();
$user_role = $stmt_check_role->get_result()->fetch_assoc()['user_type'];
$stmt_check_role->close();

if ($user_role !== 'admin') { http_response_code(403); exit(json_encode(['success' => false, 'message' => 'Accès refusé.'])); }

$method = $_SERVER['REQUEST_METHOD'];
$data = json_decode(file_get_contents('php://input'), true);

if ($method === 'POST') {
    $action = $data['action'] ?? $_POST['action'] ?? '';
    
    switch ($action) {
        case 'get_all':
            $stmt = $conn->prepare("SELECT id, name, email, phone, user_type, created_at FROM users ORDER BY id ASC");
            $stmt->execute();
            $users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            echo json_encode(['success' => true, 'data' => $users]);
            break;
            
        case 'add':
            $name = $data['name'];
            $email = $data['email'];
            $phone = $data['phone'];
            $password = password_hash($data['password'], PASSWORD_DEFAULT);
            $user_type = $data['user_type'];
            
            $stmt = $conn->prepare("INSERT INTO users (name, email, phone, password, user_type) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $name, $email, $phone, $password, $user_type);
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Utilisateur ajouté avec succès.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'ajout.']);
            }
            break;
            
        case 'update':
            $id_to_update = $data['id'];
            $name = $data['name'];
            $email = $data['email'];
            $phone = $data['phone'];
            $user_type = $data['user_type'];
            
            $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, phone = ?, user_type = ? WHERE id = ?");
            $stmt->bind_param("ssssi", $name, $email, $phone, $user_type, $id_to_update);
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Utilisateur mis à jour.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Erreur de mise à jour.']);
            }
            break;

        case 'delete':
            $id_to_delete = $data['id'];
            if ($id_to_delete == $userId) { // Un admin ne peut pas se supprimer lui-même
                 echo json_encode(['success' => false, 'message' => 'Vous ne pouvez pas supprimer votre propre compte.']);
                 exit();
            }
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param("i", $id_to_delete);
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Utilisateur supprimé.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Erreur de suppression.']);
            }
            break;
    }
}
$conn->close();
?>