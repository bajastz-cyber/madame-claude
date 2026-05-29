<?php
/**
 * VoAnh - API Chat
 * Mistral AI + Images Pollinations + Mémoire utilisateur
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

$raw   = file_get_contents('php://input');
$input = json_decode($raw, true);

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
    foreach ($cat as $m) $allModels[] = $m['id'];
}
if (!in_array($model, $allModels)) {
    $model = MASTER_AGENT_MODEL;
}

// ── DÉTECTION DEMANDE D'IMAGE ─────────────────────────────────────
function isImageRequest($message) {
    $keywords = [
        'génère une image', 'générer une image', 'crée une image', 'créer une image',
        'génère moi une image', 'fais moi une image', 'dessine', 'génère un dessin',
        'génère une photo', 'crée une photo', 'montre moi une image',
        'une illustration de', 'génère une illustration', 'image de', 'photo de',
    ];
    $msg = mb_strtolower($message);
    foreach ($keywords as $kw) {
        if (strpos($msg, $kw) !== false) return true;
    }
    return false;
}

try {
    $auth = new Auth();
    $user = $auth->getCurrentUser();
    $userId = $user ? (int)$user['id'] : null;
    $apiKey = $user ? ($user['mistral_api_key'] ?: null) : null;

    $db = Database::getInstance();
    $mistral = getMistralClient($apiKey);

    // Créer ou récupérer la conversation
    if (!$convId && $userId) {
        $convId = $db->insert('conversations', [
            'user_id'    => $userId,
            'title'      => mb_substr($message, 0, 60),
            'model_used' => $model,
        ]);
    } elseif ($convId && $userId) {
        $conv = $db->fetch("SELECT id FROM conversations WHERE id = ? AND user_id = ?", [$convId, $userId]);
        if (!$conv) $convId = null;
    }

    // Sauvegarder le message utilisateur
    if ($convId && $message) {
        $db->insert('messages', [
            'conversation_id' => $convId,
            'role'            => 'user',
            'content'         => $message,
            'model_used'      => $model,
        ]);
    }

    // ── DEMANDE D'IMAGE → Pollinations ───────────────────────────
    if (isImageRequest($message)) {
        $prompt = preg_replace(
            '/génère une image|générer une image|crée une image|créer une image|génère moi une image|fais moi une image|dessine|génère un dessin|génère une photo|crée une photo|montre moi une image|une illustration de|génère une illustration|image de|photo de/i',
            '', $message
        );
        $prompt = trim($prompt) ?: $message;

        $seed = rand(1000, 99999);
        $url  = 'https://image.pollinations.ai/prompt/' . urlencode($prompt)
              . '?width=800&height=600&nologo=true&seed=' . $seed;

        $reply = '__IMAGE__' . $url;

        if ($convId) {
            $db->insert('messages', [
                'conversation_id' => $convId,
                'role'            => 'assistant',
                'content'         => $reply,
                'model_used'      => $model,
            ]);
            $db->update('conversations', ['updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$convId]);
        }

        echo json_encode([
            'success'         => true,
            'content'         => $reply,
            'model'           => $model,
            'conversation_id' => $convId,
            'is_image'        => true,
            'image_url'       => $url,
        ]);
        exit;
    }

    // ── APPEL MISTRAL NORMAL ──────────────────────────────────────
    $apiMessages = [];

    $apiMessages[] = [
        'role'    => 'system',
        'content' => "Tu es VoAnh, un assistant IA avancé basé sur Mistral AI. Tu es intelligent, précis, créatif et bienveillant. Tu réponds toujours en français sauf si l'utilisateur parle une autre langue. Tu peux coder, analyser, créer et planifier des tâches complexes. Quand tu crées un site web ou un fichier HTML complet, mets TOUJOURS le code dans un bloc de code markdown avec ```html au début et ``` à la fin, afin que l'utilisateur puisse le télécharger facilement.",
    ];

    // Historique
    if ($convId) {
        $history = $db->fetchAll(
            "SELECT role, content FROM messages WHERE conversation_id = ? ORDER BY created_at DESC LIMIT 20",
            [$convId]
        );
        foreach (array_reverse($history) as $msg) {
            if (in_array($msg['role'], ['user', 'assistant'])) {
                $apiMessages[] = ['role' => $msg['role'], 'content' => $msg['content']];
            }
        }
    } else {
        if ($fileBase64 && strpos($fileMime ?? '', 'image/') === 0) {
            $apiMessages[] = ['role' => 'user', 'content' => [
                ['type' => 'text', 'text' => $message ?: 'Analyse cette image.'],
                ['type' => 'image_url', 'image_url' => ['url' => 'data:' . $fileMime . ';base64,' . $fileBase64]],
            ]];
        } else {
            $apiMessages[] = ['role' => 'user', 'content' => $message];
        }
    }

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
            $db->update('conversations', ['updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$convId]);
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
