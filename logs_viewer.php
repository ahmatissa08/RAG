<?php
// logs_viewer.php — Visualiseur de logs chatbot

$logFile = __DIR__ . '/logs/chatbot.log';

// Vider les logs si demandé
if (isset($_GET['clear']) && $_GET['clear'] === '1') {
    file_put_contents($logFile, '');
    header("Location: logs_viewer.php");
    exit;
}

// Lire les logs
$logs = file_exists($logFile) ? file_get_contents($logFile) : "Fichier introuvable.";
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>📋 Logs du Chatbot</title>
    <meta http-equiv="refresh" content="10"> <!-- Auto-refresh -->
    <style>
        body {
            font-family: "Segoe UI", sans-serif;
            background: #f5f7fa;
            padding: 40px;
            color: #333;
        }
        h1 {
            text-align: center;
            color: #2f80ed;
        }
        pre {
            background: #222;
            color: #0f0;
            padding: 20px;
            overflow-x: auto;
            border-radius: 8px;
            max-height: 75vh;
        }
        .actions {
            text-align: center;
            margin-bottom: 20px;
        }
        .btn {
            background: #2f80ed;
            color: white;
            border: none;
            padding: 10px 20px;
            font-size: 15px;
            border-radius: 6px;
            cursor: pointer;
            margin: 0 10px;
        }
        .btn:hover {
            background: #1b60c4;
        }
    </style>
</head>
<body>
    <h1>📊 Visualisation des Logs du Chatbot</h1>

    <div class="actions">
        <a href="logs_viewer.php?clear=1" class="btn">🧹 Vider les logs</a>
        <a href="logs_viewer.php" class="btn">🔄 Actualiser</a>
    </div>

    <pre><?php echo htmlspecialchars($logs); ?></pre>
</body>
</html>
