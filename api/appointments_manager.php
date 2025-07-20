
<?php
// api/appointments_manager.php (Version finale, corrigée et sécurisée)

header('Content-Type: application/json');

require_once 'config.php';
require_once 'functions.php';

$conn = connect_db();
$method = $_SERVER['REQUEST_METHOD'];

// On essaie d'identifier l'utilisateur. Sera `null` pour un prospect.
$userId = get_user_id_from_token();

// =========================================================================
// == ROUTEUR D'ACTIONS AMÉLIORÉ
// =========================================================================

// On lit les données une seule fois
$data = [];
if ($method === 'POST' || $method === 'PUT' || $method === 'DELETE') {
    $raw_data = file_get_contents('php://input');
    if (!empty($raw_data)) {
        $data = json_decode($raw_data, true);
    }
    if (empty($data) && !empty($_POST)) {
        $data = $_POST;
    }
}
if ($method === 'GET') {
    $data = $_GET;
}
$action = $data['action'] ?? '';

// CAS 1 : C'est une requête POST pour créer un RDV (action implicite ou explicite)
if ($method === 'POST' && ($action === 'create' || ($action === '' && !$userId))) {
    
    if (empty($data['name']) || empty($data['email']) || empty($data['date'])) {
        http_response_code(400);
        exit(json_encode(['success' => false, 'message' => 'Les champs nom, email et date sont obligatoires.']));
    }

    $name = trim($data['name']);
    $email = trim($data['email']);
    $phone = trim($data['phone'] ?? null);
    $date = trim($data['date']);
    $subject = trim($data['subject'] ?? null);
    
    $stmt = $conn->prepare("INSERT INTO appointments (prospect_name, prospect_email, prospect_phone, appointment_date, subject) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $name, $email, $phone, $date, $subject);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Votre demande de rendez-vous a bien été enregistrée.']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erreur serveur lors de l\'enregistrement.']);
    }
    $stmt->close();

} 
// CAS 2 : Toutes les autres actions nécessitent une authentification
else {
    if (!$userId) { 
        http_response_code(401); 
        exit(json_encode(['success' => false, 'message' => 'Authentification requise pour cette action.'])); 
    }
    
    $stmt_role = $conn->prepare("SELECT user_type, email FROM users WHERE id = ?");
    $stmt_role->bind_param("i", $userId);
    $stmt_role->execute();
    $user_info = $stmt_role->get_result()->fetch_assoc();
    $user_role = $user_info['user_type'];
    $user_email = $user_info['email'];
    $stmt_role->close();

    switch ($action) {
        case 'get_my_appointments':
            $stmt = $conn->prepare("SELECT id, appointment_date, subject, status FROM appointments WHERE prospect_email = ? ORDER BY created_at DESC");
            $stmt->bind_param("s", $user_email);
            $stmt->execute();
            $appointments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            echo json_encode(['success' => true, 'data' => $appointments]);
            break;
        
        case 'cancel_my_appointment':
            $rdvId = $data['id'] ?? 0;
            $stmt = $conn->prepare("UPDATE appointments SET status = 'cancelled' WHERE id = ? AND prospect_email = ?");
            $stmt->bind_param("is", $rdvId, $user_email);
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                echo json_encode(['success' => true, 'message' => 'Rendez-vous annulé.']);
            } else {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Impossible d\'annuler ce rendez-vous.']);
            }
            break;

        case 'get_all':
        case 'update_status':
        case 'delete':
            if ($user_role !== 'admin') { http_response_code(403); exit(json_encode(['success' => false, 'message' => 'Accès refusé.'])); }
            
            if ($action === 'get_all') {
                $sql = "SELECT * FROM appointments ORDER BY created_at DESC";
                $all_appointments = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
                echo json_encode(['success' => true, 'data' => $all_appointments]);
            }
            elseif ($action === 'update_status') {
            $id = $data['id'] ?? 0;
            $status = $data['status'] ?? '';
            if ($id > 0 && !empty($status)) {
                $stmt = $conn->prepare("UPDATE appointments SET status = ? WHERE id = ?");
                $stmt->bind_param("si", $status, $id);
                $stmt->execute();
                echo json_encode(['success' => true, 'message' => 'Statut mis à jour.']);
            } else {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Données manquantes pour la mise à jour.']);
            }
        }
        elseif ($action === 'delete') {
            $id = $data['id'] ?? 0;
            if ($id > 0) {
                $stmt = $conn->prepare("DELETE FROM appointments WHERE id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                echo json_encode(['success' => true, 'message' => 'Rendez-vous supprimé.']);
            } else {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'ID manquant pour la suppression.']);
            }
        }
        break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Action non reconnue.']);
            break;
    }
}

$conn->close();
?>
