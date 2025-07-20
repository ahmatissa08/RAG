<?php
// api/reclamations_manager.php (Version complète avec gestion admin)
header('Content-Type: application/json');

require_once 'config.php';
require_once 'functions.php';

// --- Étape de Sécurité ---
$userId = get_user_id_from_token();
if ($userId === null) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentification requise.']);
    exit();
}

$conn = connect_db();

// On vérifie le rôle de l'utilisateur qui fait l'action
$stmt_check_role = $conn->prepare("SELECT user_type FROM users WHERE id = ?");
$stmt_check_role->bind_param("i", $userId);
$stmt_check_role->execute();
$user_role = $stmt_check_role->get_result()->fetch_assoc()['user_type'];
$stmt_check_role->close();

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'create_ticket_from_bot':
        // Cette action est autorisée pour tous les utilisateurs connectés
        $description = $_POST['description'];
        $stmt = $conn->prepare("INSERT INTO reclamations (user_id, type, description, priority, status) VALUES (?, 'Question non répondue', ?, 'low', 'open')");
        $stmt->bind_param("is", $userId, $description);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Ticket créé.', 'ticket_id' => $stmt->insert_id]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erreur création ticket.']);
        }
        $stmt->close();
        break;

    // --- ACTIONS RÉSERVÉES AUX ADMINS ---
    case 'get_all_reclamations':
        if ($user_role !== 'admin') { http_response_code(403); exit('Accès refusé.'); }
        
        // On joint la table 'users' pour récupérer l'email de l'utilisateur
        $sql = "SELECT r.id, r.description, r.status, r.created_at, u.email 
                FROM reclamations r
                JOIN users u ON r.user_id = u.id
                ORDER BY r.status ASC, r.created_at DESC";
        
        $reclamations = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
        echo json_encode(['success' => true, 'data' => $reclamations]);
        break;

    case 'update_status':
        if ($user_role !== 'admin') { http_response_code(403); exit('Accès refusé.'); }

        $id = $_POST['id'];
        $status = $_POST['status'];
        $stmt = $conn->prepare("UPDATE reclamations SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $id);
        $stmt->execute();
        echo json_encode(['success' => true, 'message' => 'Statut mis à jour.']);
        $stmt->close();
        break;

    case 'delete_reclamation':
        if ($user_role !== 'admin') { http_response_code(403); exit('Accès refusé.'); }
        
        $id = $_POST['id'];
        $stmt = $conn->prepare("DELETE FROM reclamations WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        echo json_encode(['success' => true, 'message' => 'Réclamation supprimée.']);
        $stmt->close();
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Action non reconnue.']);
        break;
}

$conn->close();
?>