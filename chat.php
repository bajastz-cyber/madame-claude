<?php
error_reporting(0); ini_set("display_errors", 0);
error_reporting(0); ini_set("display_errors", 0);
/**
 * MadameClaude - API Chat
 * Mistral AI + Recherche web Serper + Mémoire utilisateur + Vision + Images Pollinations
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

// ── Authentification ──────────────────────────────────────────────
session_start();
$userId = $_SESSION['user_id'] ?? null;

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

$visionModels  = ['pixtral-large-2411', 'pixtral-12b-2409'];
$isVisionModel = in_array($model, $visionModels);

// ── DÉTECTION DEMANDE D'IMAGE ─────────────────────────────────────
function isImageRequest($message) {
    $keywords = [
        'génère une image', 'générer une image', 'crée une image', 'créer une image',
        'génère moi une image', 'fais moi une image', 'dessine', 'génère un dessin',
        'génère une photo', 'crée une photo', 'montre moi une image', 'une illustration de',
        'génère une illustration',
    ];
    $msg = mb_strtolower($message);
    foreach ($keywords as $kw) {
        if (strpos($msg, $kw) !== false) return true;
    }
    return false;
}

// ── RECHERCHE WEB SERPER ──────────────────────────────────────────
function needsWebSearch($message) {
    $keywords = [
        'actualité', 'aujourd\'hui', 'maintenant', 'récent', 'dernier', 'dernière',
        'prix', 'météo', 'temps qu\'il fait', 'résultat', 'match', 'score', 'élection',
        'qui est', 'c\'est quoi', 'qu\'est-ce que', 'quand', 'où se trouve',
        'comment aller', 'horaire', 'ouvert', 'fermé',
        '2024', '2025', '2026',
        'news', 'info', 'dernières nouvelles', 'cours de', 'bourse',
    ];
    $msg = mb_strtolower($message);
    foreach ($keywords as $kw) {
        if (strpos($msg, $kw) !== false) return true;
    }
    return false;
}

function searchWebSerper($query) {
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
        CURLOPT_TIMEOUT => 8,
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    if (!$response) return '';
    $data = json_decode($response, true);
    if (!$data) return '';
    $results = [];
    if (!empty($data['answerBox']['answer'])) {
        $results[] = '📌 ' . $data['answerBox']['answer'];
    }
    if (!empty($data['answerBox']['snippet'])) {
        $results[] = '📌 ' . $data['answerBox']['snippet'];
    }
    if (!empty($data['organic'])) {
        foreach (array_slice($data['organic'], 0, 4) as $r) {
            if (!empty($r['snippet'])) {
                $results[] = '🔍 ' . $r['title'] . ' : ' . $r['snippet'];
            }
        }
    }
    return implode("\n", $results);
}

// ── MÉMOIRE UTILISATEUR ───────────────────────────────────────────
function getUserMemory($db, $userId) {
    if (!$userId) return '';
    try {
        $memories = $db->fetchAll(
            "SELECT memory_key, memory_value FROM user_memory WHERE user_id = ? ORDER BY updated_at DESC LIMIT 20",
            [$userId]
        );
        if (!$memories) return '';
        $lines = [];
        foreach ($memories as $m) {
            $lines[] = '- ' . $m['memory_key'] . ' : ' . $m['memory_value'];
        }
        return implode("\n", $lines);
    } catch (Exception $e) {
        return '';
    }
}

// ── SAUVEGARDE MÉMOIRE ────────────────────────────────────────────
function saveMemoryFromConversation($db, $userId, $userMsg, $assistantReply) {
    if (!$userId) return;
    // Détecter si l'utilisateur donne une info sur lui
    $patterns = [
        '/je m\'appelle ([A-ZÀ-Ÿa-zà-ÿ\s]+)/i'        => 'prénom',
        '/mon prénom est ([A-ZÀ-Ÿa-zà-ÿ\s]+)/i'        => 'prénom',
        '/j\'habite (à |en |au )?([A-ZÀ-Ÿa-zà-ÿ\s]+)/i'=> 'ville',
        '/je travaille (comme |en tant que )?(.+)/i'    => 'métier',
        '/j\'aime (.+)/i'                               => 'aime',
    ];
    foreach ($patterns as $pattern => $key) {
        if (preg_match($pattern, $userMsg, $matches)) {
            $value = trim(end($matches));
            if (strlen($value) < 50) {
                try {
                    $db->execute(
                        "INSERT INTO user_memory (user_id, memory_key, memory_value, updated_at)
                         VALUES (?, ?, ?, datetime('now'))
                         ON CONFLICT(user_id, memory_key) DO UPDATE SET memory_value=excluded.memory_value, updated_at=excluded.updated_at",
                        [$userId, $key, $value]
                    );
                } catch (Exception $e) { /* ignore */ }
            }
        }
    }
}

// ── HISTORIQUE CONVERSATION ───────────────────────────────────────
$db = Database::getInstance();
$history = [];

if ($convId) {
    try {
        $msgs = $db->fetchAll(
            "SELECT role, content FROM messages WHERE conversation_id = ? ORDER BY created_at ASC LIMIT 40",
            [$convId]
        );
        foreach ($msgs as $m) {
            $history[] = ['role' => $m['role'], 'content' => $m['content']];
        }
    } catch (Exception $e) { /* ignore */ }
}

// ── CONSTRUIRE LE CONTEXTE SYSTÈME ───────────────────────────────
$userMemory  = getUserMemory($db, $userId);
$memoryBlock = $userMemory ? "\n\nCe que tu sais sur cet utilisateur :\n" . $userMemory : '';

$webBlock = '';
if ($message) {
    $webResults = searchWebSerper($message);
    if ($webResults) {
        $webBlock = "\n\nRésultats de recherche web en temps réel :\n" . $webResults;
    }
}

$systemPrompt = "Tu es MadameClaude, un assistant IA avancé, intelligent, précis, créatif et bienveillant. "
    . "Tu réponds toujours en français sauf si l'utilisateur parle une autre langue. "
    . "Tu peux coder, analyser, créer et planifier des tâches complexes. "
    . "Quand tu crées du code HTML/CSS/JS, mets-le TOUJOURS dans un bloc ```html. "
    . "Quand tu as des résultats de recherche web, utilise-les pour répondre avec des informations à jour et précise la source."
    . $memoryBlock
    . $webBlock;

// ── CONSTRUCTION DES MESSAGES ─────────────────────────────────────
$mistral = new MistralClient();

// Construire le message utilisateur (avec fichier si présent)
if ($fileBase64 && $fileMime && $isVisionModel) {
    $userContent = [
        [
            'type'      => 'image_url',
            'image_url' => ['url' => 'data:' . $fileMime . ';base64,' . $fileBase64],
        ],
    ];
    if ($message) {
        $userContent[] = ['type' => 'text', 'text' => $message];
    } else {
        $userContent[] = ['type' => 'text', 'text' => 'Analyse cette image.'];
    }
} else {
    $userContent = $message ?: 'Analyse ce fichier.';
}

// Construire le tableau complet des messages API
$apiMessages = [['role' => 'system', 'content' => $systemPrompt]];
foreach ($history as $h) {
    $apiMessages[] = ['role' => $h['role'], 'content' => $h['content']];
}
$apiMessages[] = ['role' => 'user', 'content' => $userContent];

// ── SAUVEGARDER LE MESSAGE UTILISATEUR ───────────────────────────
if ($convId && $message) {
    try {
        $db->execute(
            "INSERT INTO messages (conversation_id, role, content, has_file, created_at)
             VALUES (?, 'user', ?, ?, datetime('now'))",
            [$convId, $message, $fileBase64 ? 1 : 0]
        );
    } catch (Exception $e) { /* ignore */ }
}

// ── APPEL MISTRAL ─────────────────────────────────────────────────
// Si c'est une demande d'image, on retourne directement l'URL Pollinations
if (isImageRequest($message)) {
    $prompt = preg_replace('/génère une image|créer? une image|fais moi une image|dessine|génère moi une image/i', '', $message);
    $prompt = trim($prompt);
    $url = 'https://image.pollinations.ai/prompt/' . urlencode($prompt)
         . '?width=800&height=600&nologo=true&seed=' . rand(1000, 9999);

    $reply = "🎨 Voici ton image !\n\n![image générée](" . $url . ")\n\n[⬇️ Télécharger l'image](" . $url . ")";

    if ($convId) {
        try {
            $db->execute(
                "INSERT INTO messages (conversation_id, role, content, created_at)
                 VALUES (?, 'assistant', ?, datetime('now'))",
                [$convId, $reply]
            );
            $db->execute(
                "UPDATE conversations SET updated_at = datetime('now') WHERE id = ?",
                [$convId]
            );
        } catch (Exception $e) { /* ignore */ }
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

// Appel normal Mistral
$result = $mistral->chat($apiMessages, $model, [
    'temperature' => 0.7,
    'max_tokens'  => 4096,
]);

if ($result['success']) {
    $reply = $result['content'];

    // Sauvegarder la réponse assistant
    if ($convId) {
        try {
            $db->execute(
                "INSERT INTO messages (conversation_id, role, content, model_used, tokens_used, created_at)
                 VALUES (?, 'assistant', ?, ?, ?, datetime('now'))",
                [$convId, $reply, $model, $result['usage']['total_tokens'] ?? 0]
            );
            $db->execute(
                "UPDATE conversations SET updated_at = datetime('now') WHERE id = ?",
                [$convId]
            );
        } catch (Exception $e) { /* ignore */ }
    }

    // Tenter de sauvegarder infos mémoire
    if ($userId && $message) {
        saveMemoryFromConversation($db, $userId, $message, $reply);
    }

    echo json_encode([
        'success'         => true,
        'content'         => $reply,
        'model'           => $model,
        'conversation_id' => $convId,
        'usage'           => $result['usage'] ?? [],
        'web_search_used' => !empty($webBlock),
    ]);

} else {
    error_log("MadameClaude chat error: " . ($result['error'] ?? 'unknown'));
    echo json_encode([
        'success' => false,
        'error'   => $result['error'] ?? "Erreur de l'API Mistral",
    ]);
}
