<?php
// api/functions.php (Version corrigée avec logs redirigés vers logs/chatbot.log)
session_start();

require_once __DIR__ . '/../vendor/autoload.php';
require_once 'config.php';

use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;

function get_user_id_from_token() {
    $headers = getallheaders();
    if (isset($headers['Authorization'])) {
        @list($jwt) = sscanf($headers['Authorization'], 'Bearer %s');
        if ($jwt) {
            try {
                $decoded = JWT::decode($jwt, new Key(JWT_SECRET, 'HS256'));
                error_log("[AUTH] Utilisateur identifié : ID={$decoded->data->id}\n", 3, __DIR__ . '/../logs/chatbot.log');
                return $decoded->data->id;
            } catch (Exception $e) {
                error_log("[AUTH] Échec décodage JWT : " . $e->getMessage() . "\n", 3, __DIR__ . '/../logs/chatbot.log');
                return null;
            }
        }
    }
    error_log("[AUTH] Aucun token trouvé\n", 3, __DIR__ . '/../logs/chatbot.log');
    return null;
}




function handle_delete_requests($conn, $userId) {
    $data = json_decode(file_get_contents('php://input'), true);
    $conversationId = $data['conversationId'] ?? null;

    if (!$conversationId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'conversationId manquant.']);
        return;
    }

    $stmt = $conn->prepare("DELETE FROM conversations WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $conversationId, $userId);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Conversation supprimée.']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la suppression.']);
    }
}




function get_embedding($text) {
    error_log("[EMBEDDING] Texte à vectoriser : $text\n", 3, __DIR__ . '/../logs/chatbot.log');
    $url = 'http://127.0.0.1:5000/embed';
    $data = json_encode(['text' => $text]);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

    $response = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpcode == 200) {
        $result = json_decode($response, true);
        error_log("[EMBEDDING] Réponse reçue : " . substr(json_encode($result), 0, 100) . "...\n", 3, __DIR__ . '/../logs/chatbot.log');
        return $result['embedding'];
    }

    error_log("[EMBEDDING] Erreur HTTP $httpcode : $response\n", 3, __DIR__ . '/../logs/chatbot.log');
    return null;
}

function cosine_similarity(array $vec1, array $vec2) {
    $dotProduct = 0.0; $normA = 0.0; $normB = 0.0;
    $count = count($vec1);
    if ($count === 0 || $count !== count($vec2)) return 0;
    for ($i = 0; $i < $count; $i++) {
        $dotProduct += ($vec1[$i] ?? 0) * ($vec2[$i] ?? 0);
        $normA += ($vec1[$i] ?? 0) ** 2;
        $normB += ($vec2[$i] ?? 0) ** 2;
    }
    $magnitude = sqrt($normA) * sqrt($normB);
    return $magnitude == 0 ? 0 : $dotProduct / $magnitude;
}

function find_similar_documents($conn, $vector, $threshold = 0.55, $max_results = 3) {
    if ($vector === null) return [];

    $results = [];
    $sql = "SELECT id, content, question_variation, content_embedding FROM knowledge_base WHERE content_embedding IS NOT NULL";
    $allDocs = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

    error_log("[SIMILARITY] Nb total documents scannés : " . count($allDocs) . "\n", 3, __DIR__ . '/../logs/chatbot.log');

    foreach ($allDocs as $doc) {
        if (!empty($doc['content_embedding'])) {
            $serializedVector = base64_decode($doc['content_embedding']);
            $docVector = unserialize($serializedVector);

            if (is_array($docVector)) {
                $similarity = cosine_similarity($vector, $docVector);
                if ($similarity >= $threshold) {
                    error_log("[SIMILARITY] Match ID={$doc['id']} Score=" . round($similarity, 3) . "\n", 3, __DIR__ . '/../logs/chatbot.log');
                    $results[] = [
                        'id' => $doc['id'],
                        'content' => $doc['content'],
                        'similarity' => $similarity
                    ];
                }
            }
        }
    }

    usort($results, fn($a, $b) => $b['similarity'] <=> $a['similarity']);
    error_log("[SIMILARITY] Résultats pertinents trouvés : " . count($results) . "\n", 3, __DIR__ . '/../logs/chatbot.log');
    return array_slice($results, 0, $max_results);
}

function call_openai_api(string $prompt, bool $stream = false, ?callable $callback = null) {
    $apiKey = $_ENV['OPENAI_API_KEY'] ?? '';
    if (empty($apiKey)) {
        error_log("[OPENAI] Clé API manquante.\n", 3, __DIR__ . '/../logs/chatbot.log');
        return "Erreur: La clé API OpenAI n'est pas configurée sur le serveur.";
    }

    $apiUrl = "https://api.openai.com/v1/chat/completions";
    $fullResponse = "";

    $payload_data = [
        "model" => "gpt-3.5-turbo",
        "messages" => [["role" => "user", "content" => $prompt]],
        "temperature" => 0.5,
    ];

    if ($stream) {
        $payload_data["stream"] = true;
    } else {
        $payload_data["max_tokens"] = 400;
    }

    $payload = json_encode($payload_data);
    $headers = ['Content-Type: application/json', 'Authorization: Bearer ' . $apiKey];
    error_log("[OPENAI] Prompt envoyé : " . substr($prompt, 0, 150) . "...\n", 3, __DIR__ . '/../logs/chatbot.log');

    $ch = curl_init();
    $curl_opts = [
        CURLOPT_URL => $apiUrl,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => !$stream
    ];

    if ($stream && $callback) {
        $curl_opts[CURLOPT_WRITEFUNCTION] = function($curl, $data) use ($callback, &$fullResponse) {
            $chunks = explode("\n\n", $data);
            foreach ($chunks as $chunk) {
                if (strpos($chunk, 'data: ') === 0) {
                    $json_str = substr($chunk, 6);
                    if (trim($json_str) === '[DONE]') continue;
                    $decoded = json_decode($json_str, true);
                    $text_chunk = $decoded['choices'][0]['delta']['content'] ?? '';
                    if (!empty($text_chunk)) {
                        $fullResponse .= $text_chunk;
                        $callback($text_chunk);
                    }
                }
            }
            return strlen($data);
        };
    }

    curl_setopt_array($ch, $curl_opts);
    $response = curl_exec($ch);

    if (!$stream) {
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($httpcode == 200) {
            $result = json_decode($response, true);
            $fullResponse = $result['choices'][0]['message']['content'] ?? "Désolé, la réponse de l'IA est mal formée.";
            error_log("[OPENAI] Réponse brute : " . substr($response, 0, 200) . "...\n", 3, __DIR__ . '/../logs/chatbot.log');
        } else {
            error_log("[OPENAI] Erreur API (HTTP $httpcode) : $response\n", 3, __DIR__ . '/../logs/chatbot.log');
            $fullResponse = "Désolé, une erreur de communication avec l'assistant IA est survenue.";
        }
    }

    curl_close($ch);
    return $fullResponse;
}


