<?php
// populate_embeddings.php (Version finale - Basée sur les QUESTIONS)

set_time_limit(0); // Pas de limite de temps pour cette opération cruciale

require_once 'api/config.php';
require_once 'api/functions.php';

echo "Démarrage du peuplement par lot des embeddings (BASÉ SUR LES QUESTIONS)...\n";
$conn = connect_db();

// 1. On récupère TOUS les documents à traiter
$sql = "SELECT id, question_variation FROM knowledge_base WHERE content_embedding IS NULL";
$docs_to_process = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

if (empty($docs_to_process)) {
    echo "Aucun nouveau document à traiter. La base de connaissances est à jour.\n";
    $conn->close();
    exit;
}

echo count($docs_to_process) . " documents à envoyer au traitement par lot.\n";

// 2. On prépare les données pour Python en utilisant la colonne 'question_variation'
$data_for_python = [];
foreach ($docs_to_process as $doc) {
    // === LA MODIFICATION CLÉ EST ICI ===
    $data_for_python[] = ['id' => $doc['id'], 'text' => $doc['question_variation']];
}
$json_data = json_encode($data_for_python);

// 3. On appelle le script Python de traitement par lot (cette partie ne change pas)
$command = "python " . escapeshellarg(EMBEDDER_BATCH_PATH); // Utilise la constante de config.php

$descriptorspec = [0 => ["pipe", "r"], 1 => ["pipe", "w"], 2 => ["pipe", "w"]];
$process = proc_open($command, $descriptorspec, $pipes);
$embeddings_result = null;

if (is_resource($process)) {
    fwrite($pipes[0], $json_data);
    fclose($pipes[0]);
    $embeddings_json = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $errors = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    proc_close($process);

    if (!empty($errors)) die("Erreur du script Python: " . $errors);
    $embeddings_result = json_decode($embeddings_json, true);
}

if ($embeddings_result === null) die("Erreur: Aucune donnée d'embedding n'a été reçue de Python.\n");

echo "Résultats reçus. Mise à jour de la base de données...\n";

// 4. On met à jour la base de données (cette partie ne change pas)
$updateSql = "UPDATE knowledge_base SET content_embedding = ? WHERE id = ?";
$updateStmt = $conn->prepare($updateSql);
$updateStmt->bind_param("si", $base64Vector, $docId);

$success_count = 0;
foreach ($embeddings_result as $id => $vector) {
    $docId = $id;
    $serializedVector = serialize($vector);
    $base64Vector = base64_encode($serializedVector);
    if ($updateStmt->execute()) {
        $success_count++;
    }
}

echo "$success_count documents ont été mis à jour avec succès.\n";
$updateStmt->close();
$conn->close();
echo "Opération terminée.\n";
?>