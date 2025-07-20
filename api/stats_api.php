<?php
// api/stats_api.php

header('Content-Type: application/json');

require_once 'config.php';
require_once 'functions.php';

// 1. SÉCURITÉ : On s'assure que seul un admin peut voir les statistiques
$userId = get_user_id_from_token();
if ($userId === null) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé.']);
    exit();
}

$conn = connect_db();
$stmt_check_role = $conn->prepare("SELECT user_type FROM users WHERE id = ?");
$stmt_check_role->bind_param("i", $userId);
$stmt_check_role->execute();
$user_role = $stmt_check_role->get_result()->fetch_assoc()['user_type'];
$stmt_check_role->close();

if ($user_role !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Accès refusé.']);
    $conn->close();
    exit();
}

// 2. CALCUL DES STATISTIQUES
try {
    $stats = [];

    // Nombre total d'utilisateurs
    $result = $conn->query("SELECT COUNT(*) as total FROM users");
    $stats['totalUsers'] = $result->fetch_assoc()['total'];

    // Nombre total de messages échangés (utilisateurs + bot)
    // Note : Cette table peut devenir très grande.
    $result = $conn->query("SELECT COUNT(*) as total FROM chat_messages");
    $stats['totalMessages'] = $result->fetch_assoc()['total'];

    // Nombre de réclamations en attente (ouvertes ou en cours)
    $result = $conn->query("SELECT COUNT(*) as total FROM reclamations WHERE status IN ('open', 'in_progress')");
    $stats['pendingReclamations'] = $result->fetch_assoc()['total'];

    // Nombre d'administrateurs
    $result = $conn->query("SELECT COUNT(*) as total FROM users WHERE user_type = 'admin'");
    $stats['adminUsers'] = $result->fetch_assoc()['total'];

    echo json_encode(['success' => true, 'stats' => $stats]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur lors du calcul des statistiques.', 'error' => $e->getMessage()]);
}

$conn->close();
?>