<?php
// api/report_generator.php

// Active les erreurs pour le debug
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ob_start(); // Empêche toute sortie parasite

require_once 'config.php';
require_once __DIR__ . '/../vendor/autoload.php'; // Dompdf
require_once 'functions.php'; // get_user_id_from_token

use Dompdf\Dompdf;
use Dompdf\Options;

// 1. Authentification & autorisation
$userId = get_user_id_from_token();
if ($userId === null) {
    http_response_code(401);
    exit('Authentification requise.');
}

$conn = connect_db();
$stmt_check_role = $conn->prepare("SELECT user_type FROM users WHERE id = ?");
$stmt_check_role->bind_param("i", $userId);
$stmt_check_role->execute();
$user_role = $stmt_check_role->get_result()->fetch_assoc()['user_type'];
$stmt_check_role->close();

if ($user_role !== 'admin') {
    http_response_code(403);
    exit('Accès refusé.');
}

// 2. Données à afficher
$totalUsers = $conn->query("SELECT COUNT(*) as total FROM users")->fetch_assoc()['total'];
$totalMessages = $conn->query("SELECT COUNT(*) as total FROM chat_messages")->fetch_assoc()['total'];
$pendingTickets = $conn->query("SELECT COUNT(*) as total FROM reclamations WHERE status IN ('open', 'in_progress')")->fetch_assoc()['total'];

$latestTickets = $conn->query("
    SELECT r.id, r.description, r.created_at, u.email 
    FROM reclamations r 
    JOIN users u ON r.user_id = u.id 
    WHERE r.status = 'open' 
    ORDER BY r.created_at DESC 
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

$latestUsers = $conn->query("
    SELECT id, name, email, user_type, created_at 
    FROM users 
    ORDER BY created_at DESC 
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

$conn->close();

// 3. HTML du rapport
$html = '
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Rapport d\'Activité - Chatbot IAM</title>
    <style>
        body { font-family: Helvetica, sans-serif; font-size: 10px; }
        h1 { text-align: center; color: #333; }
        h2 { border-bottom: 1px solid #ccc; padding-bottom: 5px; color: #555; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .summary-box { display: inline-block; width: 30%; margin: 1%; padding: 10px; text-align: center; border: 1px solid #ccc; border-radius: 5px; }
        .summary-box .value { font-size: 24px; font-weight: bold; }
    </style>
</head>
<body>
    <h1>Rapport d\'Activité - Assistant IAM</h1>
    <p style="text-align:center;">Généré le : ' . date('d/m/Y H:i') . '</p>

    <h2>Résumé Statistique</h2>
    <div class="summary-container">
        <div class="summary-box"><div>Total Utilisateurs</div><div class="value">' . $totalUsers . '</div></div>
        <div class="summary-box"><div>Messages Échangés</div><div class="value">' . $totalMessages . '</div></div>
        <div class="summary-box"><div>Tickets en Attente</div><div class="value">' . $pendingTickets . '</div></div>
    </div>

    <h2>Derniers Tickets Ouverts</h2>
    <table>
        <thead><tr><th>ID</th><th>Email Utilisateur</th><th>Description</th><th>Date</th></tr></thead>
        <tbody>';

foreach ($latestTickets as $ticket) {
    $html .= '<tr><td>' . $ticket['id'] . '</td><td>' . $ticket['email'] . '</td><td>' . htmlspecialchars($ticket['description']) . '</td><td>' . $ticket['created_at'] . '</td></tr>';
}
if (empty($latestTickets)) {
    $html .= '<tr><td colspan="4">Aucun ticket ouvert.</td></tr>';
}

$html .= '
        </tbody>
    </table>

    <h2>Derniers Utilisateurs Inscrits</h2>
    <table>
        <thead><tr><th>ID</th><th>Nom</th><th>Email</th><th>Rôle</th><th>Date d\'inscription</th></tr></thead>
        <tbody>';

foreach ($latestUsers as $user) {
    $html .= '<tr><td>' . $user['id'] . '</td><td>' . htmlspecialchars($user['name']) . '</td><td>' . $user['email'] . '</td><td>' . $user['user_type'] . '</td><td>' . $user['created_at'] . '</td></tr>';
}

$html .= '
        </tbody>
    </table>
</body>
</html>';

// 4. DomPDF
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('defaultFont', 'Helvetica');

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// 5. Envoi
ob_end_clean(); // Nettoie le tampon de sortie
$filename = 'rapport_iam_' . date('d-m-Y') . '.pdf';
$dompdf->stream($filename, ["Attachment" => true]); // Téléchargement
exit;
