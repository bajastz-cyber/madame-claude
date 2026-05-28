<?php
define('MISTRAL_API_KEY', 'VOTRE_CLE_API_MISTRAL_ICI');
define('DEFAULT_MODEL', 'mistral-small-latest');
define('MISTRAL_MODELS', [
    'Rapides' => [
        ['id' => 'mistral-small-latest',  'name' => '⚡ Mistral Small (rapide)'],
        ['id' => 'open-mistral-7b',       'name' => '🆓 Mistral 7B (gratuit)'],
    ],
    'Puissants' => [
        ['id' => 'mistral-large-latest',  'name' => '🧠 Mistral Large (puissant)'],
    ],
    'Vision' => [
        ['id' => 'pixtral-12b-2409',      'name' => '👁️ Pixtral 12B (vision)'],
        ['id' => 'pixtral-large-2411',    'name' => '👁️ Pixtral Large (vision HD)'],
    ],
]);
define('VISION_MODELS', ['pixtral-12b-2409', 'pixtral-large-2411']);
define('DB_PATH', __DIR__ . '/data/madameclaude.sqlite');
define('SESSION_KEY', 'mc_user_id');
define('LOG_FILE', __DIR__ . '/data/error.log');
define('LOG_LEVEL', 2);
define('MAX_HISTORY', 40);

function mc_log(string $msg, int $level = 3): void {
    if ($level <= LOG_LEVEL) {
        $prefix = ['', '[ERR]', '[WARN]', '[DBG]'][$level] ?? '[LOG]';
        error_log(date('[Y-m-d H:i:s] ') . $prefix . ' ' . $msg . PHP_EOL, 3, LOG_FILE);
    }
}
function mc_json(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
if (session_status() === PHP_SESSION_NONE) session_start();
