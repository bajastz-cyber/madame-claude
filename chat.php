<?php
/**
 * VoAnh - API Chat avec recherche web Serper
 * Hostinger/AlwaysData compatible
 */

require_once dirname(__FILE__) . '/config.php';
require_once dirname(__FILE__) . '/database.php';
require_once dirname(__FILE__) . '/auth.php';
require_once dirname(__FILE__) . '/mistral.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
    exit;
}

$raw    = file_get_contents('php://input');
$input  = json_decode($raw, true);

$message    = trim($input['message']           ?? '');
$model      = trim($input['model']             ?? MASTER_AGENT_MODEL);
$convId     = isset($input['conversation_id']) ? (int)$input['conversation_id'] : null;
$fileBase64 = $input['file_base64']            ?? null;
$fileMime   = $input['file_mime']              ?? null;
$fileName   = $input['file_name']              ?? null;

if ($message === '' && !$fileBase64) {
    echo json_encode(['success' => false, 'error' => 'Message vide']);
    exit;
}

// Valider le modèle
$allModels = [];
foreach (MISTRAL_MODELS as $cat) {
    foreach ($cat as $m) {
        $allModels[] = $m['id'];
    }
}
if (!in_array($model, $allModels)) {
    $model = MASTER_AGENT_MODEL;
}

// ----------------------------------------------------------------
// RECHERCHE WEB SERPER
// ----------------------------------------------------------------
function searchWebSerper(string $query): string {
    $apiKey = '19b25786089632f79ebaa25225fbddc89a462e3c';
    $ch = curl_init('https://google.serper.dev/search');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            'X-API-KEY: ' . $apiKey,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'q'   => $query,
            'gl'  => 'fr',
            'hl'  => 'fr',
            'num' => 5,
        ]),
        CURLOPT_TIMEOUT    => 8,
        CURLOPT_USERAGENT  => 'VoAnh/1.0',
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    if (!$response) return '';
    $data = json_decode($response, true);
    if (!$data) return '';
    $results = [];
    if (!empty($data['answerBox']['answer'])) {
        $results[] = $data['answerBox']['answer'];
    }
    if (!empty($data['organic'])) {
        foreach (array_slice($data['organic'], 0, 3) as $r) {
            if (!empty($r['snippet'])) {
                $results[] = ($r['title'] ?? '') . ' : ' . $r['snippet'];
            }
        }
    }
    return implode("\n", $results);
}

function needsWebSearch(string $message): bool {
    $keywords = [
        'actualité', 'aujourd\'hui', 'maintenant', 'récent', 'dernier',
        'prix', 'météo', 'résultat', 'match', 'score', 'élection',
        'qui est', 'c\'est quoi', 'quand', 'où', '2025', '2026',
        'news', 'info', 'dernières nouvelles', 'que se passe',
    ];
    $msg = mb_strtolower($message);
    foreach ($keywords as $kw) {
        if (strpos($msg, $kw) !== false) return true;
    }
    return false;
}

// ----------------------------------------------------------------
// MAIN
// ----------------------------------------------------------------
try {
    $auth   = new Auth();
    $user   = $auth->getCurrentUser();
    $userId = $user ? (int)$user['id'] : null;
    $apiKey = ($user && !empty($user['mistral_api_key'])) ? $user['mistral_api_key'] : null;

    $db      = Database::getInstance();
    $mistral = getMistralClient($apiKey);

    // Créer ou vérifier la conversation
    if ($userId) {
        if (!$convId) {
            $convId = $db->insert('conversations', [
                'user_id'    => $userId,
                'title'      => mb_substr($message ?: $fileName ?: 'Nouvelle conversation', 0, 60),
                'model_used' => $model,
            ]);
        } else {
            $conv = $db->fetch(
                "SELECT id FROM conversations WHERE id = ? AND user_id = ?",
                [$convId, $userId]
            );
            if (!$conv) $convId = null;
        }
    }

    // Sauvegarder le message utilisateur
    if ($convId) {
        $db->insert('messages', [
            'conversation_id' => $convId,
            'role'            => 'user',
            'content'         => $message ?: "[$fileName]",
            'model_used'      => $model,
            'has_file'        => $fileBase64 ? 1 : 0,
        ]);
    }

    // Recherche web si nécessaire
    $webBlock = '';
    if ($message && needsWebSearch($message)) {
        $webResults = searchWebSerper($message);
        if ($webResults) {
            $webBlock = "\n\nRésultats de recherche web en temps réel :\n" . $webResults;
        }
    }

    // Construire les messages pour l'API
    $apiMessages = [];
    $apiMessages[] = [
        'role'    => 'system',
        'content' => "Tu es VoAnh, un assistant IA avancé basé sur Mistral AI. Tu es intelligent, précis, créatif et bienveillant. Tu réponds toujours en français sauf si l'utilisateur parle une autre langue. Tu peux coder, analyser, créer et planifier des tâches complexes. Quand tu crées un site web ou un fichier HTML complet, mets TOUJOURS le code dans un bloc de code markdown avec ```html au début et ``` à la fin, afin que l'utilisateur puisse le télécharger facilement." . $webBlock,
    ];

    // Historique
    if ($convId) {
        $history = $db->fetchAll(
            "SELECT role, content FROM messages
             WHERE conversation_id = ?
             ORDER BY created_at DESC
             LIMIT 20",
            [$convId]
        );
        $history = array_reverse($history);
        $historyWithoutLast = array_slice($history, 0, count($history) - 1);
        foreach ($historyWithoutLast as $msg) {
            if (in_array($msg['role'], ['user', 'assistant'])) {
                $apiMessages[] = [
                    'role'    => $msg['role'],
                    'content' => $msg['content'],
                ];
            }
        }
    }

    // Message actuel (avec fichier si présent)
    if ($fileBase64 && strpos($fileMime ?? '', 'image/') === 0) {
        $apiMessages[] = [
            'role'    => 'user',
            'content' => [
                ['type' => 'text',      'text'      => $message ?: 'Analyse cette image.'],
                ['type' => 'image_url', 'image_url' => ['url' => 'data:' . $fileMime . ';base64,' . $fileBase64]],
            ],
        ];
    } elseif ($fileBase64 && $fileMime === 'application/pdf') {
        $apiMessages[] = [
            'role'    => 'user',
            'content' => "Fichier PDF joint : $fileName\n\n$message",
        ];
    } else {
        $apiMessages[] = [
            'role'    => 'user',
            'content' => $message,
        ];
    }

    // Appel Mistral
    $result = $mistral->chat($apiMessages, $model, [
        'temperature' => 0.7,
        'max_tokens'  => 4096,
    ]);

    if ($result['success']) {
        $reply = $result['content'];

        if ($convId) {
            $db->insert('messages', [
                'conversation_id' => $convId,
                'role'            => 'assistant',
                'content'         => $reply,
                'model_used'      => $model,
                'tokens_used'     => $result['usage']['total_tokens'] ?? 0,
            ]);
            $db->update(
                'conversations',
                ['updated_at' => date('Y-m-d H:i:s')],
                'id = ?',
                [$convId]
            );
        }

        echo json_encode([
            'success'         => true,
            'content'         => $reply,
            'model'           => $model,
            'conversation_id' => $convId,
            'usage'           => $result['usage'] ?? [],
        ]);

    } else {
        voanh_log("Chat error for user $userId: " . $result['error'], 2);
        echo json_encode([
            'success' => false,
            'error'   => $result['error'] ?? "Erreur de l'API Mistral",
        ]);
    }

} catch (Exception $e) {
    voanh_log("Chat exception: " . $e->getMessage(), 1);
    echo json_encode(['success' => false, 'error' => 'Erreur interne du serveur']);
}
