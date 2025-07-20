<?php
// api/chat.php (version complète avec aide à l'orientation)
error_log("[TEST] Log initial\n", 3, __DIR__ . '/../logs/chatbot.log');
header('Content-Type: application/json');

require_once 'config.php';
require_once 'functions.php';

$conn = connect_db();
$method = $_SERVER['REQUEST_METHOD'];
$userId = get_user_id_from_token();

switch ($method) {
    case 'GET':
        handle_get_requests($conn, $userId);
        break;
    case 'POST':
        handle_post_requests($conn, $userId);
        break;
     case 'PUT':
        handle_put_requests($conn, $userId);
        break;
    case 'DELETE':
        handle_delete_requests($conn, $userId); // ✅ Il faut cette ligne
        break;
    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Méthode non autorisée.']);
        break;
}

$conn->close();
function handle_get_requests($conn, $userId) {
    $action = $_GET['action'] ?? '';
    switch ($action) {
        case 'getConversations':
            if (!$userId) {
                http_response_code(401);
                echo json_encode(['success' => false, 'message' => 'Auth requis']);
                exit();
            }

            $stmt = $conn->prepare("SELECT id, title, start_time FROM conversations WHERE user_id = ? ORDER BY start_time DESC");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $conversations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            echo json_encode(['success' => true, 'conversations' => $conversations]);
            break;

        case 'getChatHistory':
            if (!$userId) {
                http_response_code(401);
                echo json_encode(['success' => false, 'message' => 'Auth requis']);
                exit();
            }

            $conversationId = $_GET['conversationId'] ?? 0;
            if (!$conversationId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'ID de conversation manquant.']);
                exit();
            }

            $stmt = $conn->prepare("
                SELECT ch.* FROM chat_messages ch 
                JOIN conversations c ON ch.conversation_id = c.id 
                WHERE c.id = ? AND c.user_id = ? 
                ORDER BY ch.timestamp ASC
            ");
            $stmt->bind_param("ii", $conversationId, $userId);
            $stmt->execute();
            $messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            echo json_encode(['success' => true, 'history' => $messages]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Action GET non reconnue']);
            break;
    }
}

function handle_post_requests($conn, $userId) {
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('Connection: keep-alive');
    ob_flush(); flush();

    $data = json_decode(file_get_contents('php://input'), true);
    $userMessage = $data['message'] ?? '';
    $history = $data['history'] ?? [];

    if (empty(trim($userMessage))) {
        error_log("[CHAT] Message vide ignoré.\n", 3, __DIR__ . '/../logs/chatbot.log');
        exit();
    }

    // 1. Reformulation courte
    $messageForEmbedding = $userMessage;
    if (str_word_count($userMessage) < 4 && !in_array(strtolower($userMessage), ['oui', 'non', 'ok', 'merci'])) {
        $reformulation_prompt = "Reformule cette phrase pour en faire une question complète destinée à un assistant d'université : \"$userMessage\"";
        $messageForEmbedding = call_openai_api($reformulation_prompt, false);
        error_log("[REFORMULATION] => $messageForEmbedding\n", 3, __DIR__ . '/../logs/chatbot.log');
    }

    // 2. Détection d'intention (orientation)
    $orientation_prompt = <<<EOT
Analyse la question suivante. Si l'utilisateur cherche une orientation ou demande de l'aide pour choisir une filière universitaire, réponds seulement par le mot "OUI". Sinon, réponds "NON".
Question : "$userMessage"
EOT;
    $orientationIntent = trim(call_openai_api($orientation_prompt, false));
    error_log("[INTENTION] Orientation ? => $orientationIntent\n", 3, __DIR__ . '/../logs/chatbot.log');

    // 3. Si orientation détectée, on propose de continuer
    if (strtoupper($orientationIntent) === 'OUI') {
        $response = "Pour vous aider à choisir une filière adaptée, pouvez-vous me dire quelles matières vous aimez le plus ? (ex : maths, économie, informatique, langues)";
        echo "data: " . json_encode(['text' => $response]) . "\n\n";
        echo "event: done\ndata: {}\n\n";
        exit();
    }

    // 4. Si l'utilisateur dit un mot-clé reconnu (préférence matière)
    $matieres = [
        'maths' => "🎓 Merci pour votre réponse ! Sur cette base, nous vous suggérons : Licence en Finance, Mathématiques Appliquées ou Data Science.",
        'mathématiques' => "🎓 Merci pour votre réponse ! Sur cette base, nous vous suggérons : Licence en Finance, Mathématiques Appliquées ou Data Science.",
        'informatique' => "🎓 Merci pour votre réponse ! Sur cette base, nous vous suggérons : Licence en Informatique de Gestion ou Développement Web.",
        'économie' => "🎓 Merci pour votre réponse ! Une Licence en Économie, Finance ou Commerce International pourrait vous convenir.",
        'langues' => "🎓 Merci ! Vous pourriez envisager une formation en Communication, Relations Internationales ou Marketing.",
    ];

    foreach ($matieres as $motcle => $suggestion) {
        if (stripos($userMessage, $motcle) !== false) {
            echo "data: " . json_encode(['text' => $suggestion]) . "\n\n";
            echo "event: done\ndata: {}\n\n";
            exit();
        }
    }

    // 5. Sinon on continue avec le système vectoriel normal
    $userMessageVector = get_embedding($messageForEmbedding);
    $similarDocs = find_similar_documents($conn, $userMessageVector, 0.60, 3);

    if (!empty($similarDocs)) {
        $context = "Informations pertinentes :\n";
        foreach ($similarDocs as $doc) {
            $context .= "- " . $doc['content'] . "\n";
        }

        $history_string = "";
        foreach ($history as $msg) {
            $history_string .= ($msg['role'] === 'user' ? 'Utilisateur' : 'Assistant') . ": " . $msg['content'] . "\n";
        }

        $prompt = "Tu es un assistant IAM. Réponds en français, de manière claire. Voici le contexte :\n$context\n\nHistorique :\n$history_string\n\nQuestion : $userMessage";
        $fullResponse = call_openai_api($prompt, true, function ($chunk) {
            echo "data: " . json_encode(['text' => $chunk]) . "\n\n";
            ob_flush(); flush();
        });

        echo "event: done\ndata: {}\n\n";
    } else {
        // Décision escalade / hors-sujet
        $prompt = "Tu es un arbitre logique. Analyse la question de l'utilisateur : \"$userMessage\". Si elle ne concerne pas IAM, réponds 'HORS-SUJET'. Sinon, si elle est vague, réponds 'ESCALATE'.";
        $decision = trim(call_openai_api($prompt, false));

        if ($decision === 'ESCALATE') {
            echo "data: " . json_encode([
                'action_required' => 'propose_escalation',
                'response' => "Je n'ai pas trouvé de réponse précise. Souhaitez-vous transmettre votre question à un conseiller ?"
            ]) . "\n\n";
        } elseif ($decision === 'HORS-SUJET') {
            $response = "🤖 Je suis l’assistant virtuel de l’IAM, spécialisé dans les questions liées à l’établissement (formations, frais, admission, etc.). La question posée semble sortir de ce cadre. 😊";
            echo "data: " . json_encode(['text' => $response]) . "\n\n";
        } else {
            $fallback = "Désolé, je n’ai pas pu répondre à votre demande. Veuillez reformuler votre question.";
            echo "data: " . json_encode(['text' => $fallback]) . "\n\n";
        }
        echo "event: done\ndata: {}\n\n";
    }
}
?>
