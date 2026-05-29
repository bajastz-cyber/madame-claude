<?php
/**
 * VoAnh - API Chat (Hostinger compatible)
 * Endpoint : /chat.php (POST JSON)
 * Corrigé : gestion historique + fichier joint + noms de colonnes alignés
 */

require_once dirname(__FILE__) . '/config.php';
require_once dirname(__FILE__) . '/database.php';
require_once dirname(__FILE__) . '/auth.php';
require_once dirname(__FILE__) . '/mistral.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// CORS preflight
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

$message     = trim($input['message']          ?? '');
$model       = trim($input['model']            ?? MASTER_AGENT_MODEL);
$convId      = isset($input['conversation_id']) ? (int)$input['conversation_id'] : null;
$fileBase64  = $input['file_base64']           ?? null;
$fileMime    = $input['file_mime']             ?? null;
$fileName    = $input['file_name']             ?? null;

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

try {
    $auth   = new Auth();
    $user   = $auth->getCurrentUser();
    $userId = $user ? (int)$user['id'] : null;

    // Clé API : priorité à la clé perso de l'utilisateur
    $apiKey = ($user && !empty($user['mistral_api_key'])) ? $user['mistral_api_key'] : null;

    $db      = Database::getInstance();
    $mistral = getMistralClient($apiKey);

    // ----------------------------------------------------------------
    // Créer ou vérifier la conversation (utilisateurs connectés seulement)
    // ----------------------------------------------------------------
    if ($userId) {
        if (!$convId) {
            // Nouvelle conversation
            $convId = $db->insert('conversations', [
                'user_id'   => $userId,
                'title'     => mb_substr($message ?: $fileName ?: 'Nouvelle conversation', 0, 60),
                'model_used' => $model,
            ]);
        } else {
            // Vérifier que la conversation appartient bien à cet utilisateur
            $conv = $db->fetch(
                "SELECT id FROM conversations WHERE id = ? AND user_id = ?",
                [$convId, $userId]
            );
            if (!$conv) {
                $convId = null; // conversation invalide → on ignore
            }
        }
    }

    // ----------------------------------------------------------------
    // Sauvegarder le message utilisateur
    // ----------------------------------------------------------------
    if ($convId) {
        $db->insert('messages', [
            'conversation_id' => $convId,
            'role'            => 'user',
            'content'         => $message ?: "[$fileName]",
            'model_used'      => $model,
            'has_file'        => $fileBase64 ? 1 : 0,
        ]);
    }

    // ----------------------------------------------------------------
    // Construire les messages pour l'API Mistral
    // ----------------------------------------------------------------
    $apiMessages = [];

    // Prompt système
    $apiMessages[] = [
        'role'    => 'system',
        'content' => "Tu es VoAnh, un assistant IA avancé basé sur Mistral AI. Tu es intelligent, précis, créatif et bienveillant. Tu réponds toujours en français sauf si l'utilisateur parle une autre langue. Tu peux coder, analyser, créer et planifier des tâches complexes. Quand tu crées un site web ou un fichier HTML complet, mets TOUJOURS le code dans un bloc de code markdown avec ```html au début et ``` à la fin, afin que l'utilisateur puisse le télécharger facilement.",
    ];

    // Historique de la conversation (max 20 derniers messages, ordre chronologique)
    if ($convId) {
        $history = $db->fetchAll(
            "SELECT role, content FROM messages
             WHERE conversation_id = ?
             ORDER BY created_at DESC
             LIMIT 20",
            [$convId]
        );
        $history = array_reverse($history); // Remettre dans l'ordre chronologique

        // Ajouter l'historique SAUF le dernier message (le message actuel qu'on vient d'insérer)
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
        // Image → format multimodal
        $apiMessages[] = [
            'role'    => 'user',
            'content' => [
                ['type' => 'text',      'text'      => $message ?: 'Analyse cette image.'],
                ['type' => 'image_url', 'image_url' => ['url' => 'data:' . $fileMime . ';base64,' . $fileBase64]],
            ],
        ];
    } elseif ($fileBase64 && $fileMime === 'application/pdf') {
        // PDF → on ajoute le nom en contexte texte
        $apiMessages[] = [
            'role'    => 'user',
            'content' => "Fichier PDF joint : $fileName\n\n$message",
        ];
    } else {
        // Texte simple
        $apiMessages[] = [
            'role'    => 'user',
            'content' => $message,
        ];
    }

    // ----------------------------------------------------------------
    // Appel Mistral
    // ----------------------------------------------------------------
    $result = $mistral->chat($apiMessages, $model, [
        'temperature' => 0.7,
        'max_tokens'  => 4096,
    ]);

    if ($result['success']) {
        $reply = $result['content'];

        // Sauvegarder la réponse
        if ($convId) {
            $db->insert('messages', [
                'conversation_id' => $convId,
                'role'            => 'assistant',
                'content'         => $reply,
                'model_used'      => $model,
                'tokens_used'     => $result['usage']['total_tokens'] ?? 0,
            ]);

            // Mettre à jour le timestamp de la conversation
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
