<?php
/**
 * VoAnh - Classe Database (SQLite, Hostinger compatible)
 * Corrigé : constante DB_FILE, méthodes insert/update/delete,
 * schéma complet (sessions, colonnes manquantes).
 */

require_once dirname(__FILE__) . '/config.php';

class Database {

    private static ?Database $instance = null;
    private PDO $pdo;

    private function __construct() {
        $dir = dirname(DB_FILE);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $this->pdo = new PDO('sqlite:' . DB_FILE, null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $this->pdo->exec('PRAGMA journal_mode=WAL');
        $this->pdo->exec('PRAGMA foreign_keys=ON');

        $this->migrate();
    }

    public static function getInstance(): Database {
        if (!self::$instance) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    // ----------------------------------------------------------------
    // MIGRATION — schéma complet aligné avec auth.php + chat.php
    // ----------------------------------------------------------------
    private function migrate(): void {
        $this->pdo->exec("

            CREATE TABLE IF NOT EXISTS users (
                id               INTEGER PRIMARY KEY AUTOINCREMENT,
                username         TEXT    NOT NULL UNIQUE,
                email            TEXT    NOT NULL UNIQUE,
                password_hash    TEXT    NOT NULL,
                mistral_api_key  TEXT    DEFAULT '',
                pref_model       TEXT    DEFAULT 'mistral-small-latest',
                login_attempts   INTEGER DEFAULT 0,
                locked_until     TEXT    DEFAULT NULL,
                last_login       TEXT    DEFAULT NULL,
                created_at       TEXT    DEFAULT (datetime('now')),
                updated_at       TEXT    DEFAULT (datetime('now'))
            );

            CREATE TABLE IF NOT EXISTS sessions (
                id            INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id       INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                token         TEXT    NOT NULL UNIQUE,
                expires_at    TEXT    NOT NULL,
                last_activity TEXT    DEFAULT (datetime('now')),
                ip_address    TEXT    DEFAULT ''
            );

            CREATE TABLE IF NOT EXISTS conversations (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id     INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                title       TEXT    DEFAULT 'Nouvelle conversation',
                model_used  TEXT    DEFAULT 'mistral-small-latest',
                is_archived INTEGER DEFAULT 0,
                created_at  TEXT    DEFAULT (datetime('now')),
                updated_at  TEXT    DEFAULT (datetime('now'))
            );

            CREATE TABLE IF NOT EXISTS messages (
                id              INTEGER PRIMARY KEY AUTOINCREMENT,
                conversation_id INTEGER NOT NULL REFERENCES conversations(id) ON DELETE CASCADE,
                role            TEXT    NOT NULL CHECK(role IN ('user','assistant','system')),
                content         TEXT    NOT NULL,
                model_used      TEXT    DEFAULT '',
                tokens_used     INTEGER DEFAULT 0,
                has_file        INTEGER DEFAULT 0,
                created_at      TEXT    DEFAULT (datetime('now'))
            );

            CREATE INDEX IF NOT EXISTS idx_sessions_token   ON sessions(token);
            CREATE INDEX IF NOT EXISTS idx_sessions_user    ON sessions(user_id);
            CREATE INDEX IF NOT EXISTS idx_conv_user        ON conversations(user_id);
            CREATE INDEX IF NOT EXISTS idx_msg_conv         ON messages(conversation_id);

        ");
    }

    // ----------------------------------------------------------------
    // MÉTHODES CRUD
    // ----------------------------------------------------------------

    /**
     * Récupère une seule ligne.
     */
    public function fetch(string $sql, array $params = []): ?array {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Récupère toutes les lignes.
     */
    public function fetchAll(string $sql, array $params = []): array {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Exécute une requête quelconque (INSERT brut, UPDATE, DELETE bruts…).
     */
    public function execute(string $sql, array $params = []): bool {
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * INSERT dans une table à partir d'un tableau associatif.
     * Retourne le nouvel ID.
     */
    public function insert(string $table, array $data): int {
        $cols   = implode(', ', array_keys($data));
        $places = implode(', ', array_fill(0, count($data), '?'));
        $sql    = "INSERT INTO $table ($cols) VALUES ($places)";
        $stmt   = $this->pdo->prepare($sql);
        $stmt->execute(array_values($data));
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * UPDATE une table.
     * $data  : colonnes à mettre à jour (tableau associatif)
     * $where : clause WHERE ex. "id = ?"
     * $binds : valeurs pour le WHERE
     */
    public function update(string $table, array $data, string $where, array $binds = []): bool {
        $set  = implode(', ', array_map(fn($k) => "$k = ?", array_keys($data)));
        $sql  = "UPDATE $table SET $set WHERE $where";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute(array_merge(array_values($data), $binds));
    }

    /**
     * DELETE depuis une table.
     * $where : clause WHERE ex. "token = ?"
     * $binds : valeurs pour le WHERE
     */
    public function delete(string $table, string $where, array $binds = []): bool {
        $sql  = "DELETE FROM $table WHERE $where";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($binds);
    }

    /**
     * Retourne le dernier ID inséré.
     */
    public function lastId(): int {
        return (int) $this->pdo->lastInsertId();
    }
}
