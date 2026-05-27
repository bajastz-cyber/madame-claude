<?php
/**
 * VoAnh - API Chat avec mémoire + recherche web + génération d'images
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

$raw = file_get_contents('php://input');
$input = json_decode($raw, true);

$message    = trim($input['message'] ?? '');
$model      = trim($input['model'] ?? MASTER_AGENT_MODEL);
$convId     = isset($input['conversation_id']) ? (int)$input['conversation_id'] : null;
$fileBase64 = $input['file_base64'] ?? null;
$fileMime   = $input['file_mime']   ?? null;
$fileName   = $input['file_name']   ?? null;

if ($message === '' && !$fileBase64) {
    echo json_encode(['success' => false, 'error' => 'Message vide']);
    exit;
}

$allModels = [];
foreach (MISTRAL_MODELS as $cat) {
    foreach ($cat as $m) $allModels[] = $m['id'];
}
if (!in_array($model, $allModels)) $model = MASTER_AGENT_MODEL;

$visionModels  = ['pixtral-large-2411', 'pixtral-12b-2409'];
$isVisionModel = in_array($model, $visionModels);

// ── GÉNÉRATION D'IMAGES ──
function isImageRequest($message) {
    $keywords = [
        'génère une image', 'générer une image', 'crée une image', 'créer une image',
        'dessine', 'dessiner', 'génère moi', 'montre moi une image', 'fais moi une image',
        'génère un dessin', 'créer un dessin', 'illustration de', 'image de',
        'photo de', 'génère une photo', 'visualise', 'représente',
    ];
    $msg = mb_strtolower($message);
    foreach ($keywords as $kw) {
        if (strpos($msg, $kw) !== false) return true;
    }
    return false;
}

function generateImage($prompt) {
    // Traduire le prompt en anglais via Mistral serait idéal,
    // mais on utilise le prompt directement — Pollinations supporte le français
    $encodedPrompt = urlencode($prompt);
    $imageUrl = 'https://image.pollinations.ai/prompt/' . $encodedPrompt . '?width=800&height=600&nologo=true';
    return $imageUrl;
}

// ── RECHERCHE WEB ──
function searchWeb($query) {
    $url = 'https://api.duckduckgo.com/?q=' . urlencode($query) . '&format=json&no_html=1&skip_disambig=1';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_USERAGENT      => 'VoAnh/1.0',
        CURLOPT_FOLLOWLOCATION => true,
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    if (!$response) return '';
    $data = json_decode($response, true);
    if (!$data) return '';
    $results = [];
    if (!empty($data['AbstractText'])) $results[] = $data['AbstractText'];
    if (!empty($data['RelatedTopics'])) {
        foreach (array_slice($data['RelatedTopics'], 0, 3) as $topic) {
            if (!empty($topic['Text'])) $results[] = $topic['Text'];
        }
    }
    return implode("\n", $results);
}

function needsWebSearch($message) {
    $keywords = [
        'actualité', 'aujourd\'hui', 'maintenant', 'récent', 'dernier',
        'prix', 'météo', 'résultat', 'match', 'score', 'élection',
        'qui est', 'c\'est quoi', 'quand', 'où se trouve', '2025', '2026',
    ];
    $msg = mb_strtolower($message);
    foreach ($keywords as $kw) {
        if (strpos($msg, $kw) !== false) return true;
    }
    return false;
}

// ── MÉMOIRE UTILISATEUR ──
function getUserMemory($db, $userId) {
    if (!$userId) return '';
    $memories = $db->fetchAll(
        "SELECT memory_key, memory_value FROM user_memory WHERE user_id = ? ORDER BY updated_at DESC",
        [$userId]
    );
    if (!$memories) return '';
    $lines = [];
    foreach ($memories as $m) $lines[] = '- ' . $m['memory_key'] . ' : ' . $m['memory_value'];
    return implode("\n", $lines);
}

function extractAndSaveMemory($db, $userId, $message) {
    if (!$userId) return;
    $patterns = [
        '/je m\'appelle ([A-Za-zÀ-ÿ\s]+)/i'     => 'prénom',
        '/mon prénom est ([A-Za-zÀ-ÿ\s]+)/i'     => 'prénom',
        '/j\'habite à ([A-Za-zÀ-ÿ\s\-]+)/i'      => 'ville',
        '/je suis ([A-Za-zÀ-ÿ\s]+) de métier/i'  => 'métier',
        '/je travaille comme ([A-Za-zÀ-ÿ\s]+)/i' => 'métier',
        '/j\'aime ([A-Za-zÀ-ÿ\s]+)/i'            => 'intérêt',
        '/je parle ([A-Za-zÀ-ÿ\s]+)/i'           => 'langue',
    ];
    foreach ($patterns as $pattern => $key) {
        if (preg_match($pattern, $message, $matches)) {
            $value = trim($matches[1]);
            if (strlen($value) > 1 && strlen($value) < 100) {
                $existing = $db->fetch(
                    "SELECT id FROM user_memory WHERE user_id = ? AND memory_key = ?",
                    [$userId, $key]
                );
                if ($existing) {
                    $db->query(
                        "UPDATE user_memory SET memory_value = ?, updated_at = datetime('now') WHERE user_id = ? AND memory_key = ?",
                        [$value, $userId, $key]
                    );
                } else {
                    $db->query(
                        "INSERT INTO user_memory (user_id, memory_key, memory_value) VALUES (?, ?, ?)",
                        [$userId, $key, $value]
                    );
                }
            }
        }
    }
}

try {
    $auth   = new Auth();
    $user   = $auth->getCurrentUser();
    $userId = $user ? (int)$user['id'] : null;
    $apiKey = $user ? ($user['mistral_api_key'] ?: null) : null;

    $db      = Database::getInstance();
    $mistral = getMistralClient($apiKey);

    if (!$convId && $userId) {
        $convId = $db->insert('conversations', [
            'user_id'    => $userId,
            'title'      => mb_substr($message ?: ($fileName ?? 'Image'), 0, 60),
            'model_used' => $model,
        ]);
    } elseif ($convId && $userId) {
        $conv = $db->fetch("SELECT id FROM conversations WHERE id = ? AND user_id = ?", [$convId, $userId]);
        if (!$conv) $convId = null;
    }

    if ($convId) {
        $db->insert('messages', [
            'conversation_id' => $convId,
            'role'       => 'user',
            'content'    => $message,
            'model_used' => $model,
        ]);
    }

    // ── GÉNÉRATION D'IMAGE ──
    if ($message && isImageRequest($message)) {
        $imageUrl = generateImage($message);
        $reply = "![Image générée]($imageUrl)";

        if ($convId) {
            $db->insert('messages', [
                'conversation_id' => $convId,
                'role'        => 'assistant',
                'content'     => $reply,
                'model_used'  => $model,
                'tokens_used' => 0,
            ]);
            $db->update('conversations', ['updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$convId]);
        }

        echo json_encode([
            'success'         => true,
            'content'         => $reply,
            'image_url'       => $imageUrl,
            'model'           => $model,
            'conversation_id' => $convId,
        ]);
        exit;
    }

    // Mémoire utilisateur
    $userMemory  = getUserMemory($db, $userId);
    $memoryBlock = $userMemory ? "\n\nCe que tu sais sur cet utilisateur :\n" . $userMemory : '';

    // Recherche web
    $webBlock = '';
    if ($message && needsWebSearch($message)) {
        $webResults = searchWeb($message);
        if ($webResults) $webBlock = "\n\nRésultats web :\n" . $webResults;
    }

    $apiMessages = [];
    $apiMessages[] = [
        'role'    => 'system',
        'content' => "Tu es VoAnh, un assistant IA avancé basé sur Mistral AI. Tu es intelligent, précis, créatif et bienveillant. Tu réponds toujours en français sauf si l'utilisateur parle une autre langue. Tu peux coder, analyser, créer et planifier des tâches complexes. Quand tu crées un site web ou un fichier HTML complet, mets TOUJOURS le code dans un bloc de code markdown avec ```html au début et ``` à la fin." . $memoryBlock . $webBlock,
    ];

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
    }

    if ($fileBase64 && strpos($fileMime, 'image/') === 0) {
        if ($isVisionModel) {
            $apiMessages[] = [
                'role' => 'user',
                'content' => [
                    ['type' => 'text',      'text'      => $message ?: 'Analyse cette image.'],
                    ['type' => 'image_url', 'image_url' => ['url' => 'data:' . $fileMime . ';base64,' . $fileBase64]],
                ],
            ];
        } else {
            $apiMessages[] = [
                'role'    => 'user',
                'content' => ($message ?: 'Analyse cette image.') . "\n\n⚠️ Le modèle sélectionné ne supporte pas la vision. Sélectionne \"Vision Analyzer Max\" ou \"Vision Analyzer Light\" pour analyser des images.",
            ];
        }
    } elseif ($fileBase64 && $fileMime === 'application/pdf') {
        $apiMessages[] = ['role' => 'user', 'content' => "Fichier PDF joint : $fileName\n\n" . $message];
    } else {
        $apiMessages[] = ['role' => 'user', 'content' => $message];
    }

    $result = $mistral->chat($apiMessages, $model, [
        'temperature' => 0.7,
        'max_tokens'  => 4096,
    ]);

    if ($result['success']) {
        $reply = $result['content'];
        if ($userId && $message) extractAndSaveMemory($db, $userId, $message);
        if ($convId) {
            $db->insert('messages', [
                'conversation_id' => $convId,
                'role'        => 'assistant',
                'content'     => $reply,
                'model_used'  => $model,
                'tokens_used' => $result['usage']['total_tokens'] ?? 0,
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
        echo json_encode(['success' => false, 'error' => $result['error'] ?? "Erreur de l'API Mistral"]);
    }

} catch (Exception $e) {
    voanh_log("Chat exception: " . $e->getMessage(), 1);
    echo json_encode(['success' => false, 'error' => 'Erreur interne du serveur']);
}
