<?php
/**
 * VoAnh - Base de Données SQLite (Hostinger Compatible)
 */

require_once dirname(__FILE__) . '/config.php';

class Database {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        try {
            $this->pdo = new PDO('sqlite:' . DB_FILE);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->pdo->exec('PRAGMA journal_mode=WAL');
            $this->pdo->exec('PRAGMA synchronous=NORMAL');
            $this->pdo->exec('PRAGMA cache_size=5000');
            $this->pdo->exec('PRAGMA temp_store=MEMORY');
            $this->initTables();
        } catch (PDOException $e) {
            voanh_log("DB error: " . $e->getMessage(), 1);
            throw $e;
        }
    }

    public static function getInstance() {
        if (!self::$instance) self::$instance = new self();
        return self::$instance;
    }

    private function initTables() {
        $sql = "
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            email TEXT UNIQUE NOT NULL,
            password_hash TEXT NOT NULL,
            mistral_api_key TEXT,
            role TEXT DEFAULT 'user',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            last_login DATETIME,
            login_attempts INTEGER DEFAULT 0,
            locked_until DATETIME,
            settings TEXT DEFAULT '{}'
        );
        CREATE TABLE IF NOT EXISTS sessions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            token TEXT UNIQUE NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            expires_at DATETIME NOT NULL,
            last_activity DATETIME DEFAULT CURRENT_TIMESTAMP,
            ip_address TEXT,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        );
        CREATE TABLE IF NOT EXISTS conversations (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            title TEXT,
            model_used TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            is_archived INTEGER DEFAULT 0,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        );
        CREATE TABLE IF NOT EXISTS messages (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            conversation_id INTEGER NOT NULL,
            role TEXT NOT NULL,
            content TEXT NOT NULL,
            model_used TEXT,
            tokens_used INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE
        );
        CREATE INDEX IF NOT EXISTS idx_sessions_token ON sessions(token);
        CREATE INDEX IF NOT EXISTS idx_conv_user ON conversations(user_id);
        CREATE INDEX IF NOT EXISTS idx_msg_conv ON messages(conversation_id);
        ";
        foreach (explode(';', $sql) as $q) {
            $q = trim($q);
            if ($q) $this->pdo->exec($q);
        }
    }

    public function query($sql, $params = []) {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function fetch($sql, $params = []) {
        return $this->query($sql, $params)->fetch();
    }

    public function fetchAll($sql, $params = []) {
        return $this->query($sql, $params)->fetchAll();
    }

    public function insert($table, $data) {
        $cols = implode(', ', array_keys($data));
        $vals = implode(', ', array_fill(0, count($data), '?'));
        $this->query("INSERT INTO $table ($cols) VALUES ($vals)", array_values($data));
        return $this->pdo->lastInsertId();
    }

    public function update($table, $data, $where, $whereParams = []) {
        $set = implode(', ', array_map(fn($k) => "$k = ?", array_keys($data)));
        $this->query("UPDATE $table SET $set WHERE $where", array_merge(array_values($data), $whereParams));
    }

    public function delete($table, $where, $params = []) {
        $this->query("DELETE FROM $table WHERE $where", $params);
    }

    public function beginTransaction() { $this->pdo->beginTransaction(); }
    public function commit() { $this->pdo->commit(); }
    public function rollback() { $this->pdo->rollBack(); }
}
