<?php
declare(strict_types=1);

class Storage {

    private ?PDO $pdo = null;
    private string $sessionId;
    private ?Logger $logger;
    private ?string $passphrase;
    private ?string $plainPath;
    private ?string $encPath;
    private const SCHEMA_VERSION = '1';

    public function __construct(string $dbPath, string $sessionId, ?Logger $logger = null, ?string $passphrase = null) {
        $this->sessionId = $sessionId;
        $this->logger = $logger;
        $this->passphrase = $passphrase ? trim($passphrase) : null;
        $this->plainPath = $dbPath;
        $this->encPath = $dbPath . '.enc';

        $this->prepareDatabase();

        $this->pdo = new PDO("sqlite:" . $dbPath, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5,
        ]);

        $this->pdo->exec("PRAGMA foreign_keys = ON;");
        $this->pdo->exec("PRAGMA journal_mode = DELETE;");

        $this->initializeSchema();
        $this->logger?->info('Storage initialized', ['db_path' => $dbPath]);
    }

    private function prepareDatabase(): void {
        if (file_exists($this->encPath) && !file_exists($this->plainPath)) {
            if (!$this->passphrase) {
                throw new RuntimeException('Passphrase required to decrypt session data.');
            }
            Crypto::decryptFile($this->encPath, $this->plainPath, $this->passphrase, $this->sessionId);
            $this->logger?->info('Encrypted DB decrypted', ['session_id' => $this->sessionId]);
            return;
        }
    }

    private function initializeSchema(): void {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS schema_meta (
                key TEXT PRIMARY KEY,
                value TEXT NOT NULL
            );
        ");

        $stmt = $this->pdo->prepare("SELECT value FROM schema_meta WHERE key = 'schema_version'");
        $stmt->execute();
        $version = $stmt->fetchColumn();

        if ($version === false) {
            $this->createSchema();
            $this->pdo->prepare("
                INSERT INTO schema_meta (key, value) VALUES ('schema_version', ?)
            ")->execute([self::SCHEMA_VERSION]);

            $this->pdo->prepare("
                INSERT INTO schema_meta (key, value) VALUES ('created_at', datetime('now'))
            ")->execute();
        }

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS ruleset_state (
                session_id TEXT NOT NULL,
                ruleset_id TEXT NOT NULL,
                enabled INTEGER NOT NULL,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (session_id, ruleset_id)
            );
        ");
        $this->migrateRulesetState();
    }

    private function createSchema(): void {
        $this->pdo->exec("
            CREATE TABLE mappings (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                session_id TEXT NOT NULL,
                ruleset_id TEXT NOT NULL,
                rule_id TEXT NOT NULL,
                original_value TEXT NOT NULL,
                placeholder TEXT NOT NULL,
                hash TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(session_id, hash)
            );
        ");

        $this->pdo->exec("CREATE INDEX idx_mappings_placeholder ON mappings(placeholder);");
        $this->pdo->exec("CREATE INDEX idx_mappings_session ON mappings(session_id);");

        $this->pdo->exec("
            CREATE TABLE history (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                session_id TEXT NOT NULL,
                original_prompt TEXT,
                scrubbed_prompt TEXT,
                llm_response TEXT,
                restored_response TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
        ");

        $this->pdo->exec("CREATE INDEX idx_history_session ON history(session_id);");

        $this->pdo->exec("
            CREATE TABLE ruleset_state (
                session_id TEXT NOT NULL,
                ruleset_id TEXT NOT NULL,
                enabled INTEGER NOT NULL,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (session_id, ruleset_id)
            );
        ");
    }

    private function migrateRulesetState(): void {
        $info = $this->pdo->query("PRAGMA table_info(ruleset_state);")->fetchAll(PDO::FETCH_ASSOC);
        if (!$info) {
            return;
        }
        $columns = array_map(fn($row) => $row['name'], $info);
        if (in_array('session_id', $columns, true)) {
            return;
        }

        $this->pdo->exec("ALTER TABLE ruleset_state RENAME TO ruleset_state_legacy;");
        $this->pdo->exec("
            CREATE TABLE ruleset_state (
                session_id TEXT NOT NULL,
                ruleset_id TEXT NOT NULL,
                enabled INTEGER NOT NULL,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (session_id, ruleset_id)
            );
        ");
        $stmt = $this->pdo->prepare("
            INSERT INTO ruleset_state (session_id, ruleset_id, enabled, updated_at)
            SELECT ?, ruleset_id, enabled, updated_at FROM ruleset_state_legacy
        ");
        $stmt->execute([$this->sessionId]);
        $this->pdo->exec("DROP TABLE ruleset_state_legacy;");
        $this->logger?->info('Ruleset state migrated to session scope', ['session_id' => $this->sessionId]);
    }

    public function saveMapping(
        string $ruleset,
        string $ruleId,
        string $original,
        string $placeholder,
        string $hash
    ): void {
        $stmt = $this->pdo->prepare("
            INSERT OR IGNORE INTO mappings
            (session_id, ruleset_id, rule_id, original_value, placeholder, hash)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $this->sessionId,
            $ruleset,
            $ruleId,
            $original,
            $placeholder,
            $hash
        ]);

        $update = $this->pdo->prepare("
            UPDATE mappings
            SET ruleset_id = ?, rule_id = ?, original_value = ?, placeholder = ?
            WHERE session_id = ? AND hash = ?
        ");

        $update->execute([
            $ruleset,
            $ruleId,
            $original,
            $placeholder,
            $this->sessionId,
            $hash
        ]);

        $this->logger?->info('Mapping saved', [
            'ruleset_id' => $ruleset,
            'rule_id' => $ruleId,
            'hash' => $hash
        ]);
    }

    public function getMappings(): array {
        $stmt = $this->pdo->prepare("
            SELECT placeholder, original_value
            FROM mappings
            WHERE session_id = ?
        ");
        $stmt->execute([$this->sessionId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function saveHistory(
        ?string $original,
        ?string $scrubbed,
        ?string $llm,
        ?string $restored
    ): void {
        $stmt = $this->pdo->prepare("
            INSERT INTO history
            (session_id, original_prompt, scrubbed_prompt, llm_response, restored_response)
            VALUES (?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $this->sessionId,
            $original,
            $scrubbed,
            $llm,
            $restored
        ]);

        $this->logger?->info('History saved', ['session_id' => $this->sessionId]);
    }

    public function updateLatestHistory(?string $llm, ?string $restored): void {
        $stmt = $this->pdo->prepare("
            SELECT id FROM history
            WHERE session_id = ?
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $stmt->execute([$this->sessionId]);
        $latestId = $stmt->fetchColumn();

        if ($latestId === false) {
            $this->saveHistory(null, null, $llm, $restored);
            return;
        }

        $update = $this->pdo->prepare("
            UPDATE history
            SET llm_response = ?, restored_response = ?
            WHERE id = ?
        ");
        $update->execute([$llm, $restored, $latestId]);
        $this->logger?->info('History updated', ['session_id' => $this->sessionId, 'id' => $latestId]);
    }

    public function getHistory(): array {
        $stmt = $this->pdo->prepare("
            SELECT original_prompt, scrubbed_prompt,
                   llm_response, restored_response,
                   created_at
            FROM history
            WHERE session_id = ?
            ORDER BY created_at DESC
        ");
        $stmt->execute([$this->sessionId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getRulesetStates(): array {
        $stmt = $this->pdo->prepare("SELECT ruleset_id, enabled FROM ruleset_state WHERE session_id = ?");
        $stmt->execute([$this->sessionId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $map = [];
        foreach ($rows as $row) {
            $map[$row['ruleset_id']] = (bool)$row['enabled'];
        }
        return $map;
    }

    public function setRulesetState(string $rulesetId, bool $enabled): void {
        $stmt = $this->pdo->prepare("
            INSERT INTO ruleset_state (session_id, ruleset_id, enabled, updated_at)
            VALUES (?, ?, ?, datetime('now'))
            ON CONFLICT(session_id, ruleset_id) DO UPDATE SET
                enabled = excluded.enabled,
                updated_at = datetime('now')
        ");
        $stmt->execute([$this->sessionId, $rulesetId, $enabled ? 1 : 0]);
        $this->logger?->info('Ruleset state updated', ['session_id' => $this->sessionId, 'ruleset_id' => $rulesetId, 'enabled' => $enabled]);
    }

    public function deleteRulesetState(string $rulesetId): void {
        $stmt = $this->pdo->prepare("DELETE FROM ruleset_state WHERE session_id = ? AND ruleset_id = ?");
        $stmt->execute([$this->sessionId, $rulesetId]);
        $this->logger?->info('Ruleset state deleted', ['session_id' => $this->sessionId, 'ruleset_id' => $rulesetId]);
    }

    public function setPassphrase(?string $passphrase): void {
        $value = is_string($passphrase) ? trim($passphrase) : '';
        $this->passphrase = $value !== '' ? $value : null;
    }

    public function finalize(): void {
        if (!$this->passphrase) {
            return;
        }
        if (!$this->plainPath || !$this->encPath) {
            return;
        }

        $this->pdo = null;

        if (file_exists($this->plainPath)) {
            Crypto::encryptFile($this->plainPath, $this->encPath, $this->passphrase, $this->sessionId);
            unlink($this->plainPath);
            $this->logger?->info('Encrypted DB stored', ['session_id' => $this->sessionId]);
        }
    }
}
