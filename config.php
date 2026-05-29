<?php
/**
 * VoAnh - Configuration Principale
 * Compatible PHP 8.3 mutualisé - sans exec/shell_exec/putenv
 */

if (!defined('VOANH_INIT')) {
    define('VOANH_INIT', true);
}

// Chemins (tout à la racine)
define('ROOT_PATH',    dirname(__FILE__));
define('DATA_PATH',    ROOT_PATH . '/data');
define('SANDBOX_PATH', ROOT_PATH . '/sandbox');

// Base de données SQLite
define('DB_FILE', DATA_PATH . '/voanh.sqlite');

// API Keys Mistral (rotation automatique)
define('DEFAULT_MISTRAL_API_KEYS', [
    'ppqZ1KdJfDofSCd6EEcM9zemgbB6ir28',   // brain
    'aBSFTa3LbriiLqNcdysFnSKKjIqADyCq',   // rl
    'G6YSz97PPgQ3iJXdkFl9f8VFdB3I2Euj',   // main
]);

// Endpoint Mistral
define('MISTRAL_API_ENDPOINT', 'https://api.mistral.ai/v1/chat/completions');

// Modèles organisés par catégorie
define('MISTRAL_MODELS', [
    'flagship' => [
        ['id' => 'mistral-large-2512', 'name' => 'Mistral Brain Ultra',          'desc' => 'Raisonnement avancé, contextes massifs'],
        ['id' => 'mistral-large-2411', 'name' => 'Mistral Brain Legacy',          'desc' => 'Version stable entreprise'],
    ],
    'medium' => [
        ['id' => 'mistral-medium-2508', 'name' => 'Corporate Engine Pro',         'desc' => 'Analyse textuelle, rédaction'],
        ['id' => 'mistral-medium-2505', 'name' => 'Corporate Engine Standard',    'desc' => 'RAG, synthèse documents'],
    ],
    'small' => [
        ['id' => 'mistral-small-2603', 'name' => 'Fast Automate Turbo',           'desc' => 'Extraction masse, pipelines'],
        ['id' => 'mistral-small-2506', 'name' => 'Fast Automate Standard',        'desc' => 'Classification, tagging'],
    ],
    'code' => [
        ['id' => 'codestral-2508',       'name' => 'Code Master Ultimate',        'desc' => 'Auto-complétion, FIM temps réel'],
        ['id' => 'devstral-2512',        'name' => 'Dev Agent Pro',               'desc' => 'Architecture, déploiement, refactoring'],
        ['id' => 'devstral-medium-2507', 'name' => 'Dev Agent Medium',            'desc' => 'Débogage, patterns complexes'],
        ['id' => 'devstral-small-2507',  'name' => 'Dev Agent Light',             'desc' => 'Tests unitaires, CI/CD'],
    ],
    'agent' => [
        ['id' => 'magistral-medium-2509', 'name' => 'Agent Router Medium',        'desc' => 'Orchestration multi-agents'],
        ['id' => 'magistral-small-2509',  'name' => 'Agent Router Small',         'desc' => 'Routage rapide prompts'],
    ],
    'vision' => [
        ['id' => 'pixtral-large-2411', 'name' => 'Vision Analyzer Max',           'desc' => 'UI, plans, diagrammes complexes'],
        ['id' => 'pixtral-12b-2409',   'name' => 'Vision Analyzer Light',         'desc' => 'OCR, détection objets'],
    ],
    'creative' => [
        ['id' => 'labs-mistral-small-creative', 'name' => 'Creative Writer',      'desc' => 'Storytelling, brainstorming'],
    ],
    'edge' => [
        ['id' => 'ministral-14b-2512', 'name' => 'Local Engine Heavy',            'desc' => 'Modèle compact puissant'],
        ['id' => 'ministral-8b-2512',  'name' => 'Local Engine Medium',           'desc' => 'All-rounder mobile'],
        ['id' => 'ministral-3b-2512',  'name' => 'Local Engine Micro',            'desc' => 'Ultra-léger, commande vocale'],
    ],
    'audio' => [
        ['id' => 'voxtral-small-2507', 'name' => 'Audio Core Small',              'desc' => 'Analyse sémantique audio'],
        ['id' => 'voxtral-mini-2507',  'name' => 'Audio Core Mini',               'desc' => 'Traitement flux rapide'],
    ],
]);

// Modèles par défaut
define('MASTER_AGENT_MODEL',   'mistral-large-2512');
define('CODE_AGENT_MODEL',     'devstral-2512');
define('VISION_AGENT_MODEL',   'pixtral-large-2411');
define('PLANNER_AGENT_MODEL',  'magistral-medium-2509');
define('CREATIVE_AGENT_MODEL', 'labs-mistral-small-creative');

// Sécurité
define('PASSWORD_MIN_LENGTH', 8);
define('MAX_LOGIN_ATTEMPTS',  5);
define('LOCKOUT_TIME',        900);
define('SESSION_LIFETIME',    86400);
define('CSRF_TOKEN_LENGTH',   32);

// Logs
define('LOG_FILE',  DATA_PATH . '/voanh.log');
define('LOG_LEVEL', 3);

// Créer les dossiers si inexistants
foreach ([DATA_PATH, SANDBOX_PATH] as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

// Session sécurisée
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_strict_mode', 1);
    session_start();
}

// Logging
function voanh_log($message, $level = 3) {
    if ($level > LOG_LEVEL) return;
    $levels = [1 => 'ERROR', 2 => 'WARNING', 3 => 'INFO', 4 => 'DEBUG'];
    $entry  = '[' . date('Y-m-d H:i:s') . '] [' . ($levels[$level] ?? 'INFO') . '] ' . $message . "\n";
    @file_put_contents(LOG_FILE, $entry, FILE_APPEND | LOCK_EX);
}

set_exception_handler(function($e) {
    voanh_log("Exception: " . $e->getMessage(), 1);
    if (!headers_sent()) {
        http_response_code(500);
        if (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false) {
            echo json_encode(['success' => false, 'error' => 'Erreur interne']);
        }
    }
});
