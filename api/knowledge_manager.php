<?php
// api/knowledge_manager.php (Version finale complète avec CRUD)
header('Content-Type: application/json');

require_once 'config.php';
require_once 'functions.php';

// 1. SÉCURITÉ : On vérifie que l'utilisateur est un admin authentifié
$userId = get_user_id_from_token();
if ($userId === null) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé.']);
    exit();
}

$conn = connect_db();
$stmt = $conn->prepare("SELECT user_type FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user_result = $stmt->get_result()->fetch_assoc();

if (!$user_result || $user_result['user_type'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Accès refusé.']);
    exit();
}
$stmt->close();

// 2. GESTION DES ACTIONS CRUD
$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'get_all':
        // On récupère les paramètres de pagination, avec des valeurs par défaut
        $page = isset($_POST['page']) ? (int)$_POST['page'] : 1;
        $limit = isset($_POST['limit']) ? (int)$_POST['limit'] : 20;
        $offset = ($page - 1) * $limit;

        // 1. On compte le nombre total d'entrées dans la table
        $total_results_query = $conn->query("SELECT COUNT(*) as total FROM knowledge_base");
        $total_entries = $total_results_query->fetch_assoc()['total'];
        $total_pages = ceil($total_entries / $limit);

        // 2. On récupère uniquement les entrées pour la page actuelle
        $stmt = $conn->prepare("SELECT id, category, question_variation, content FROM knowledge_base ORDER BY id DESC LIMIT ? OFFSET ?");
        $stmt->bind_param("ii", $limit, $offset);
        $stmt->execute();
        $docs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // 3. On renvoie les données ET les informations de pagination
        echo json_encode([
            'success' => true, 
            'data' => $docs,
            'pagination' => [
                'currentPage' => $page,
                'totalPages' => $total_pages,
                'totalEntries' => $total_entries
            ]
        ]);
        break;

    case 'get_one':
        $id = $_GET['id'];
        $stmt = $conn->prepare("SELECT id, category, question_variation, content FROM knowledge_base WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $doc = $stmt->get_result()->fetch_assoc();
        echo json_encode(['success' => true, 'data' => $doc]);
        break;

    case 'add_entry':
        $stmt = $conn->prepare("INSERT INTO knowledge_base (category, question_variation, content) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $_POST['category'], $_POST['question'], $_POST['content']);
        $stmt->execute();
        $newId = $stmt->insert_id;
        $stmt->close();
        
        // On génère l'embedding pour la nouvelle entrée
        $vector = get_embedding($_POST['content']);
        if ($vector) {
            $serializedVector = serialize($vector);
            $base64Vector = base64_encode($serializedVector);
            $updateStmt = $conn->prepare("UPDATE knowledge_base SET content_embedding = ? WHERE id = ?");
            $updateStmt->bind_param("si", $base64Vector, $newId);
            $updateStmt->execute();
            $updateStmt->close();
        }
        echo json_encode(['success' => true, 'message' => 'Entrée ajoutée avec succès.']);
        break;

    case 'update_entry':
        $stmt = $conn->prepare("UPDATE knowledge_base SET category = ?, question_variation = ?, content = ? WHERE id = ?");
        $stmt->bind_param("sssi", $_POST['category'], $_POST['question'], $_POST['content'], $_POST['id']);
        $stmt->execute();
        $stmt->close();
        
        // On regénère l'embedding car le contenu a changé
        $vector = get_embedding($_POST['content']);
        if ($vector) {
            $serializedVector = serialize($vector);
            $base64Vector = base64_encode($serializedVector);
            $updateStmt = $conn->prepare("UPDATE knowledge_base SET content_embedding = ? WHERE id = ?");
            $updateStmt->bind_param("si", $base64Vector, $_POST['id']);
            $updateStmt->execute();
            $updateStmt->close();
        }
        echo json_encode(['success' => true, 'message' => 'Entrée mise à jour avec succès.']);
        break;

    case 'delete_entry':
        $stmt = $conn->prepare("DELETE FROM knowledge_base WHERE id = ?");
        $stmt->bind_param("i", $_POST['id']);
        $stmt->execute();
        $stmt->close();
        echo json_encode(['success' => true, 'message' => 'Entrée supprimée avec succès.']);
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Action non reconnue.']);
        break;
}

$conn->close();
?>