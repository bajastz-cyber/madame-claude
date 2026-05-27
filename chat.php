<?php
/**
 * VoAnh - API Chat avec streaming (Hostinger compatible)
 */
require_once dirname(__FILE__) . '/config.php';
require_once dirname(__FILE__) . '/database.php';
require_once dirname(__FILE__) . '/auth.php';
require_once dirname(__FILE__) . '/mistral.php';

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

$message    = trim($input['message'] ?? '');
$model      = trim($input['model'] ?? MASTER_AGENT_MODEL);
$convId     = isset($input['conversation_id']) ? (int)$input['conversation_id'] : null;
$fileBase64 = $input['file_base64'] ?? null;
$fileMime   = $input['file_mime']   ?? null;
$fileName   = $input['file_name']   ?? null;
$stream     = (bool)($input['stream'] ?? true);

if ($message === '' && !$fileBase64) {
    header('Content-Type: application/json');
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

    $apiMessages = [];
    $apiMessages[] = [
        'role'    => 'system',
        'content' => "Tu es VoAnh, un assistant IA avancé basé sur Mistral AI. Tu es intelligent, précis, créatif et bienveillant. Tu réponds toujours en français sauf si l'utilisateur parle une autre langue. Tu peux coder, analyser, créer et planifier des tâches complexes. Quand tu crées un site web ou un fichier HTML complet, mets TOUJOURS le code dans un bloc de code markdown avec ```html au début et ``` à la fin, afin que l'utilisateur puisse le télécharger facilement.",
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

    // ── STREAMING ──
    if ($stream) {
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('X-Accel-Buffering: no');

        $fullReply = '';

        $payload = [
            'model'       => $model,
            'messages'    => $apiMessages,
            'temperature' => 0.7,
            'max_tokens'  => 4096,
            'stream'      => true,
        ];

        $keys = $mistral->getKeys();
        $ch = curl_init(MISTRAL_API_ENDPOINT);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $keys[0],
            ],
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_WRITEFUNCTION  => function($ch, $data) use (&$fullReply) {
                $lines = explode("\n", $data);
                foreach ($lines as $line) {
                    $line = trim($line);
                    if ($line === '' || $line === 'data: [DONE]') continue;
                    if (strpos($line, 'data: ') === 0) {
                        $json = json_decode(substr($line, 6), true);
                        $delta = $json['choices'][0]['delta']['content'] ?? '';
                        if ($delta !== '') {
                            $fullReply .= $delta;
                            echo 'data: ' . json_encode(['delta' => $delta]) . "\n\n";
                            if (ob_get_level()) ob_flush();
                            flush();
                        }
                    }
                }
                return strlen($data);
            },
            CURLOPT_TIMEOUT        => 120,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);
        curl_exec($ch);
        curl_close($ch);

        // Sauvegarder la réponse complète
        if ($fullReply && $convId) {
            $db->insert('messages', [
                'conversation_id' => $convId,
                'role'        => 'assistant',
                'content'     => $fullReply,
                'model_used'  => $model,
                'tokens_used' => 0,
            ]);
            $db->update('conversations', ['updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$convId]);
        }

        echo 'data: ' . json_encode(['done' => true, 'conversation_id' => $convId]) . "\n\n";
        if (ob_get_level()) ob_flush();
        flush();

    } else {
        // ── MODE NORMAL (fallback) ──
        header('Content-Type: application/json');
        $result = $mistral->chat($apiMessages, $model, ['temperature' => 0.7, 'max_tokens' => 4096]);

        if ($result['success']) {
            $reply = $result['content'];
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
            echo json_encode(['success' => true, 'content' => $reply, 'model' => $model, 'conversation_id' => $convId]);
        } else {
            echo json_encode(['success' => false, 'error' => $result['error'] ?? "Erreur Mistral"]);
        }
    }

} catch (Exception $e) {
    voanh_log("Chat exception: " . $e->getMessage(), 1);
    if (!headers_sent()) header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Erreur interne du serveur']);
}
