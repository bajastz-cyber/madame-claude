<?php
require_once __DIR__ . '/config.php';

class Database {
    private static ?Database $instance = null;
    private PDO $pdo;

    private function __construct() {
        $dir = dirname(DB_PATH);
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $this->pdo = new PDO('sqlite:' . DB_PATH, null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->pdo->exec('PRAGMA journal_mode=WAL');
        $this->pdo->exec('PRAGMA foreign_keys=ON');
        $this->migrate();
    }

    public static function getInstance(): Database {
        if (!self::$instance) self::$instance = new Database();
        return self::$instance;
    }

    private function migrate(): void {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS users (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                username    TEXT NOT NULL UNIQUE,
                password    TEXT NOT NULL,
                email       TEXT,
                mistral_key TEXT DEFAULT '',
                pref_model  TEXT DEFAULT 'mistral-small-latest',
                created_at  TEXT DEFAULT (datetime('now')),
                updated_at  TEXT DEFAULT (datetime('now'))
            );
            CREATE TABLE IF NOT EXISTS conversations (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                title      TEXT DEFAULT 'Nouvelle conversation',
                model_used TEXT DEFAULT 'mistral-small-latest',
                created_at TEXT DEFAULT (datetime('now')),
                updated_at TEXT DEFAULT (datetime('now'))
            );
            CREATE TABLE IF NOT EXISTS messages (
                id              INTEGER PRIMARY KEY AUTOINCREMENT,
                conversation_id INTEGER NOT NULL REFERENCES conversations(id) ON DELETE CASCADE,
                role            TEXT NOT NULL CHECK(role IN ('user','assistant')),
                content         TEXT NOT NULL,
                has_file        INTEGER DEFAULT 0,
                created_at      TEXT DEFAULT (datetime('now'))
            );
            CREATE INDEX IF NOT EXISTS idx_conv_user ON conversations(user_id);
            CREATE INDEX IF NOT EXISTS idx_msg_conv  ON messages(conversation_id);
        ");
    }

    public function fetch(string $sql, array $params = []): ?array {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function fetchAll(string $sql, array $params = []): array {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function execute(string $sql, array $params = []): bool {
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    public function lastId(): int {
        return (int) $this->pdo->lastInsertId();
    }
}
