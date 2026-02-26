<?php
declare(strict_types=1);

header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("Referrer-Policy: no-referrer");
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; base-uri 'none'; form-action 'self';");

require_once __DIR__ . '/lib/RulesRegistry.php';
require_once __DIR__ . '/lib/ScrubberEngine.php';
require_once __DIR__ . '/lib/Storage.php';
require_once __DIR__ . '/lib/Validator.php';
require_once __DIR__ . '/lib/Logger.php';
require_once __DIR__ . '/lib/Crypto.php';
require_once __DIR__ . '/lib/DataGenerator.php';

function getServerVar(string $key): string {
    return isset($_SERVER[$key]) ? (string)$_SERVER[$key] : '';
}

function getBasicAuthCredentials(): array {
    $user = getServerVar('PHP_AUTH_USER');
    $pass = getServerVar('PHP_AUTH_PW');
    if ($user !== '') {
        return [$user, $pass];
    }

    $authHeader = getServerVar('HTTP_AUTHORIZATION');
    if ($authHeader === '' && function_exists('getallheaders')) {
        $headers = getallheaders();
        foreach ($headers as $name => $value) {
            if (strcasecmp((string)$name, 'Authorization') === 0) {
                $authHeader = (string)$value;
                break;
            }
        }
    }

    if (stripos($authHeader, 'Basic ') === 0) {
        $encoded = substr($authHeader, 6);
        $decoded = base64_decode($encoded, true);
        if ($decoded !== false && str_contains($decoded, ':')) {
            [$u, $p] = explode(':', $decoded, 2);
            return [$u, $p];
        }
    }

    return ['', ''];
}

function enforceBasicAuth(string $expectedUser, string $expectedPass): void {
    if ($expectedUser === '' || $expectedPass === '') {
        return;
    }
    [$providedUser, $providedPass] = getBasicAuthCredentials();
    $ok = hash_equals($expectedUser, $providedUser) && hash_equals($expectedPass, $providedPass);
    if ($ok) {
        return;
    }
    header('WWW-Authenticate: Basic realm="Scrubber"');
    http_response_code(401);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Authentication required.';
    exit;
}

function applyRateLimit(string $key, int $maxRequests, int $windowSeconds): ?int {
    $now = time();
    if (!isset($_SESSION['rate_limits']) || !is_array($_SESSION['rate_limits'])) {
        $_SESSION['rate_limits'] = [];
    }
    $entry = $_SESSION['rate_limits'][$key] ?? null;
    if (!is_array($entry) || !isset($entry['start'], $entry['count'])) {
        $_SESSION['rate_limits'][$key] = ['start' => $now, 'count' => 1];
        return null;
    }

    $elapsed = $now - (int)$entry['start'];
    if ($elapsed >= $windowSeconds) {
        $_SESSION['rate_limits'][$key] = ['start' => $now, 'count' => 1];
        return null;
    }

    $count = (int)$entry['count'] + 1;
    $_SESSION['rate_limits'][$key]['count'] = $count;
    if ($count > $maxRequests) {
        return max(1, $windowSeconds - $elapsed);
    }
    return null;
}

$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (isset($_SERVER['SERVER_PORT']) && (string)$_SERVER['SERVER_PORT'] === '443');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $isHttps,
    'httponly' => true,
    'samesite' => 'Strict',
]);
session_start();

enforceBasicAuth(trim((string)getenv('APP_BASIC_AUTH_USER')), trim((string)getenv('APP_BASIC_AUTH_PASS')));

if (!isset($_SESSION['uuid'])) {
    $_SESSION['uuid'] = bin2hex(random_bytes(16));
}
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$sessionId = $_SESSION['uuid'];
$csrfToken = (string)$_SESSION['csrf_token'];
$dataDir = __DIR__ . '/data';
if (!is_dir($dataDir)) {
    mkdir($dataDir, 0700, true);
}

$dbPath = $dataDir . "/session_{$sessionId}.sqlite";
$encPath = $dbPath . '.enc';
$isEncrypted = file_exists($encPath);
$logDir = $dataDir . '/logs';
$logger = new Logger($logDir, true);
$retentionDaysRaw = (string)getenv('APP_RETENTION_DAYS');
$retentionDays = $retentionDaysRaw === '' ? 30 : max(0, (int)$retentionDaysRaw);
if ($retentionDays > 0) {
    $patterns = [$dataDir . '/session_*.sqlite', $dataDir . '/session_*.sqlite.enc'];
    $cutoff = time() - ($retentionDays * 86400);
    $deleted = 0;
    foreach ($patterns as $pattern) {
        foreach (glob($pattern) ?: [] as $path) {
            $base = basename($path);
            $isCurrent = $base === "session_{$sessionId}.sqlite" || $base === "session_{$sessionId}.sqlite.enc";
            if ($isCurrent) {
                continue;
            }
            $mtime = @filemtime($path);
            if ($mtime === false || $mtime >= $cutoff) {
                continue;
            }
            if (@unlink($path)) {
                $deleted++;
            }
        }
    }
    if ($deleted > 0) {
        $logger->info('Old session files cleaned', ['deleted_files' => $deleted, 'retention_days' => $retentionDays]);
    }
}

$passphrase = $_SESSION['session_key'] ?? null;
if ($passphrase === null && file_exists($encPath)) {
    http_response_code(500);
    echo 'Passphrase required for this session.';
    exit;
}
try {
    $storage = new Storage($dbPath, $sessionId, $logger, $passphrase);
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Failed to open session storage. Check session ID or passphrase.';
    exit;
}
$enabledMap = $storage->getRulesetStates();
$rulesRegistry = new RulesRegistry(__DIR__ . '/rules', $logger, $enabledMap);
$scrubber = new ScrubberEngine($rulesRegistry, $storage, $logger);

register_shutdown_function(function () use ($storage, $logger) {
    try {
        $storage->finalize();
    } catch (Throwable $e) {
        $logger->error('Finalize failed', ['error' => $e->getMessage()]);
    }
});

if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'download') {
    $rulesetId = $_GET['ruleset_id'] ?? '';
    $file = $rulesRegistry->getRulesetFile($rulesetId);
    if ($file && is_file($file)) {
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . basename($file) . '"');
        readfile($file);
        exit;
    }
    http_response_code(404);
    echo 'Ruleset not found';
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'download_rules_backup') {
    $rulesDir = __DIR__ . '/rules';
    $timestamp = gmdate('Ymd_His');
    $bundle = [
        'backup_version' => '1.0',
        'generated_at' => gmdate('Y-m-d\\TH:i:s\\Z'),
        'rulesets' => []
    ];
    foreach (glob($rulesDir . '/*.scrubrules.json') as $file) {
        $raw = file_get_contents($file);
        $content = json_decode($raw, true);
        $bundle['rulesets'][] = [
            'filename' => basename($file),
            'content' => $content
        ];
    }
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="rules_backup_' . $timestamp . '.json"');
    echo json_encode($bundle, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    try {
        $action = $_POST['action'] ?? '';
        $csrf = (string)($_POST['csrf_token'] ?? '');
        if ($csrf === '' || !hash_equals($csrfToken, $csrf)) {
            http_response_code(403);
            echo json_encode(['error' => 'Invalid CSRF token']);
            exit;
        }
        $rateLimitConfig = [
            'scrub' => ['key' => 'scrub', 'max' => 8, 'window' => 10],
            'restore' => ['key' => 'restore', 'max' => 8, 'window' => 10],
            'upload_ruleset' => ['key' => 'upload_ruleset', 'max' => 4, 'window' => 60]
        ];
        if (isset($rateLimitConfig[$action])) {
            $cfg = $rateLimitConfig[$action];
            $retryAfter = applyRateLimit((string)$cfg['key'], (int)$cfg['max'], (int)$cfg['window']);
            if ($retryAfter !== null) {
                http_response_code(429);
                header('Retry-After: ' . (string)$retryAfter);
                echo json_encode(['error' => 'Too many requests. Please retry later.']);
                exit;
            }
        }

        if ($action === 'scrub') {
        $text = $_POST['text'] ?? '';
        if (strlen($text) > 2_000_000) {
            $logger->warn('Scrub rejected: input too large', ['session_id' => $sessionId]);
            echo json_encode(['error' => 'Input too large']);
            exit;
        }

        $logger->info('Scrub requested', ['session_id' => $sessionId, 'length' => strlen($text)]);
        $result = $scrubber->scrubText($text);
        echo json_encode($result);
        exit;
    }

    if ($action === 'restore') {
        $text = $_POST['text'] ?? '';
        $logger->info('Restore requested', ['session_id' => $sessionId, 'length' => strlen($text)]);
        $restored = $scrubber->restoreText($text);
        $storage->updateLatestHistory($text, $restored);
        echo json_encode(['restored_text' => $restored]);
        exit;
    }

    if ($action === 'history') {
        $logger->info('History requested', ['session_id' => $sessionId]);
        echo json_encode($storage->getHistory());
        exit;
    }

    if ($action === 'rulesets') {
        echo json_encode($rulesRegistry->getRulesets());
        exit;
    }

    if ($action === 'session_status') {
        $enc = file_exists($dbPath . '.enc');
        echo json_encode(['encrypted' => $enc]);
        exit;
    }

    if ($action === 'resume_session') {
        $resumeId = strtolower(trim($_POST['session_id'] ?? ''));
        $passphrase = trim($_POST['passphrase'] ?? '');
        if (!preg_match('/^[a-f0-9]{32}$/', $resumeId)) {
            echo json_encode(['error' => 'Invalid session id format']);
            exit;
        }
        $resumeDb = $dataDir . "/session_{$resumeId}.sqlite";
        $resumeEnc = $resumeDb . '.enc';
        if (!file_exists($resumeDb) && !file_exists($resumeEnc)) {
            echo json_encode(['error' => 'Session not found.']);
            exit;
        }
        if (file_exists($resumeEnc) && $passphrase === '') {
            echo json_encode(['error' => 'Passphrase required for encrypted sessions.']);
            exit;
        }
        session_regenerate_id(true);
        $_SESSION['uuid'] = $resumeId;
        if ($passphrase !== '') {
            $_SESSION['session_key'] = $passphrase;
        } else {
            unset($_SESSION['session_key']);
        }
        $logger->info('Session resumed', ['session_id' => $resumeId]);
        echo json_encode(['status' => 'ok']);
        exit;
    }

    if ($action === 'exit_session') {
        $encrypted = false;
        $passphrase = $_SESSION['session_key'] ?? '';
        if ($passphrase !== '' && function_exists('openssl_encrypt') && function_exists('openssl_decrypt')) {
            try {
                $storage->setPassphrase($passphrase);
                $storage->finalize();
                $encrypted = file_exists($dbPath . '.enc');
            } catch (Throwable $e) {
                $logger->error('Session exit encryption failed', ['error' => $e->getMessage()]);
            }
        }
        $logger->info('Session exited', ['session_id' => $sessionId, 'encrypted' => $encrypted]);
        session_destroy();
        session_start();
        session_regenerate_id(true);
        $_SESSION['uuid'] = bin2hex(random_bytes(16));
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        echo json_encode(['status' => 'ok', 'encrypted' => $encrypted]);
        exit;
    }

    if ($action === 'encrypt_session') {
        $passphrase = trim($_POST['passphrase'] ?? '');
        if (strlen($passphrase) < 8) {
            echo json_encode(['error' => 'Passphrase must be at least 8 characters.']);
            exit;
        }
        if (!function_exists('openssl_encrypt') || !function_exists('openssl_decrypt')) {
            echo json_encode(['error' => 'OpenSSL extension is not available in this PHP build.']);
            exit;
        }
        $_SESSION['session_key'] = $passphrase;
        try {
            $storage->setPassphrase($passphrase);
            $storage->finalize();
            if (!file_exists($dbPath . '.enc')) {
                throw new RuntimeException('Encrypted session file was not created.');
            }
        } catch (Throwable $e) {
            $logger->error('Session encrypt failed', ['error' => $e->getMessage()]);
            echo json_encode(['error' => 'Failed to encrypt session data.']);
            exit;
        }
        $logger->info('Session encrypted', ['session_id' => $sessionId]);
        echo json_encode(['status' => 'ok']);
        exit;
    }

    if ($action === 'view_ruleset') {
        $rulesetId = $_POST['ruleset_id'] ?? '';
        $file = $rulesRegistry->getRulesetFile($rulesetId);
        if (!$file || !is_file($file)) {
            echo json_encode(['error' => 'Ruleset not found']);
            exit;
        }
        $raw = file_get_contents($file);
        $decoded = json_decode($raw, true);
        $pretty = $decoded ? json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : $raw;
        echo json_encode(['ruleset_id' => $rulesetId, 'content' => $pretty]);
        exit;
    }

    if ($action === 'toggle_ruleset') {
        $rulesetId = $_POST['ruleset_id'] ?? '';
        $enabled = ($_POST['enabled'] ?? '') === '1';
        if ($rulesetId !== '') {
            $storage->setRulesetState($rulesetId, $enabled);
            $logger->info('Ruleset toggled', ['ruleset_id' => $rulesetId, 'enabled' => $enabled]);
        }
        echo json_encode(['status' => 'ok']);
        exit;
    }

        if ($action === 'upload_ruleset') {
            if (!isset($_FILES['ruleset'])) {
            echo json_encode(['error' => 'Missing file']);
            exit;
        }
            $uploadError = (int)($_FILES['ruleset']['error'] ?? UPLOAD_ERR_NO_FILE);
            if ($uploadError !== UPLOAD_ERR_OK) {
                echo json_encode(['error' => 'Upload failed']);
                exit;
            }
            $name = (string)($_FILES['ruleset']['name'] ?? '');
            $nameLower = strtolower($name);
            if (!str_ends_with($nameLower, '.json') && !str_ends_with($nameLower, '.scrubrules.json')) {
                echo json_encode(['error' => 'Invalid file type']);
                exit;
            }
            $size = (int)($_FILES['ruleset']['size'] ?? 0);
            if ($size <= 0 || $size > 2_000_000) {
                echo json_encode(['error' => 'Ruleset file too large']);
                exit;
            }
            $tmp = $_FILES['ruleset']['tmp_name'] ?? '';
            if ($tmp === '' || !is_uploaded_file($tmp)) {
            echo json_encode(['error' => 'Upload failed']);
            exit;
        }
            $raw = file_get_contents($tmp);
            if ($raw === false) {
                echo json_encode(['error' => 'Failed to read uploaded file']);
                exit;
            }
            if (strlen($raw) > 2_000_000) {
                echo json_encode(['error' => 'Ruleset file too large']);
                exit;
            }
            $data = json_decode($raw, true);
            if (!is_array($data)) {
            echo json_encode(['error' => 'Invalid JSON']);
            exit;
        }

            if (isset($data['backup_version'], $data['rulesets']) && is_array($data['rulesets'])) {
                if (count($data['rulesets']) > 200) {
                    echo json_encode(['error' => 'Backup contains too many rulesets']);
                    exit;
                }
            $saved = 0;
            foreach ($data['rulesets'] as $entry) {
                if (!is_array($entry) || !isset($entry['filename'], $entry['content'])) {
                    continue;
                }
                $content = $entry['content'];
                if (!is_array($content) || !isset($content['ruleset_id'], $content['priority_base'], $content['rules'])) {
                    continue;
                }
                $rulesetId = (string)$content['ruleset_id'];
                if (!preg_match('/^[A-Z0-9_]+$/', $rulesetId)) {
                    continue;
                }
                $target = __DIR__ . '/rules/' . strtolower($rulesetId) . '.scrubrules.json';
                if (file_put_contents($target, json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) !== false) {
                    $storage->setRulesetState($rulesetId, true);
                    $saved++;
                }
            }
            echo json_encode(['status' => 'ok', 'saved' => $saved]);
            exit;
        }

            if (!isset($data['ruleset_id'], $data['priority_base'], $data['rules'])) {
            echo json_encode(['error' => 'Invalid ruleset format']);
            exit;
        }
        $rulesetId = (string)$data['ruleset_id'];
        if (!preg_match('/^[A-Z0-9_]+$/', $rulesetId)) {
            echo json_encode(['error' => 'Invalid ruleset_id']);
            exit;
        }
        $target = __DIR__ . '/rules/' . strtolower($rulesetId) . '.scrubrules.json';
        if (file_exists($target)) {
            echo json_encode(['error' => 'Ruleset already exists']);
            exit;
        }
        if (file_put_contents($target, $raw) === false) {
            echo json_encode(['error' => 'Failed to save ruleset']);
            exit;
        }
        $storage->setRulesetState($rulesetId, true);
        $logger->info('Ruleset uploaded', ['ruleset_id' => $rulesetId, 'file' => $target]);
        echo json_encode(['status' => 'ok']);
        exit;
    }

    if ($action === 'delete_ruleset') {
        $rulesetId = $_POST['ruleset_id'] ?? '';
        $file = $rulesRegistry->getRulesetFile($rulesetId);
        if (!$file || !is_file($file)) {
            echo json_encode(['error' => 'Ruleset not found']);
            exit;
        }
        $base = basename($file);
        if (in_array($base, ['pci.scrubrules.json', 'pii.scrubrules.json', 'phi.scrubrules.json', 'network.scrubrules.json', 'tokens.scrubrules.json', 'corp.scrubrules.json', 'finance.scrubrules.json', 'cloud.scrubrules.json'], true)) {
            echo json_encode(['error' => 'Cannot delete built-in ruleset']);
            exit;
        }
        if (!unlink($file)) {
            echo json_encode(['error' => 'Failed to delete ruleset']);
            exit;
        }
        $storage->deleteRulesetState($rulesetId);
        $logger->info('Ruleset deleted', ['ruleset_id' => $rulesetId]);
        echo json_encode(['status' => 'ok']);
        exit;
    }

        if ($action === 'clear') {
        $logger->info('Session cleared', ['session_id' => $sessionId]);
        session_destroy();
        if (file_exists($dbPath)) {
            unlink($dbPath);
        }
        if (file_exists($encPath)) {
            unlink($encPath);
        }
        echo json_encode(['status' => 'cleared']);
        exit;
    }

        echo json_encode(['error' => 'Invalid action']);
        exit;
    } catch (Throwable $e) {
        http_response_code(500);
        $logger->error('Unhandled POST exception', [
            'action' => $_POST['action'] ?? '',
            'error' => $e->getMessage()
        ]);
        echo json_encode(['error' => 'Internal server error']);
        exit;
    }
}
    $styleVersion = (string) (filemtime(__DIR__ . '/assets/style.css') ?: time());
    $appVersion = (string) (filemtime(__DIR__ . '/assets/app.js') ?: time());
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>" />
    <title>Local Scrubber</title>
    <link rel="stylesheet" href="assets/style.css?v=<?php echo urlencode($styleVersion); ?>" />
    <script src="assets/app.js?v=<?php echo urlencode($appVersion); ?>" defer></script>
</head>
<body>
<a href="#appMain" class="skip-link">Skip to main content</a>

<main id="appMain" class="container" aria-busy="false">
    <div id="srStatus" class="sr-only" role="status" aria-live="polite" aria-atomic="true"></div>
    <div id="loadingOverlay" class="loading-overlay hidden" aria-hidden="true">
        <div class="spinner" role="status" aria-live="polite" aria-label="Processing"></div>
        <div class="spinner-text">Processing...</div>
    </div>
    <div id="modalOverlay" class="modal-overlay hidden" aria-hidden="true">
        <div class="modal" role="dialog" aria-modal="true" aria-labelledby="modalTitle" aria-describedby="modalMessage">
            <h3 id="modalTitle" class="modal-title">Confirm</h3>
            <p id="modalMessage" class="modal-message"></p>
            <div class="modal-actions">
                <button id="modalCancel" type="button" class="secondary">Cancel</button>
                <button id="modalOk" type="button" class="primary">OK</button>
            </div>
        </div>
    </div>
    <div id="resumeOverlay" class="modal-overlay hidden" aria-hidden="true">
        <div class="modal" role="dialog" aria-modal="true" aria-labelledby="resumeTitle" aria-describedby="resumeHelp">
            <h3 id="resumeTitle" class="modal-title">Resume Session</h3>
            <div class="modal-form">
                <label for="resumeSessionId">Session ID</label>
                <input id="resumeSessionId" type="text" placeholder="32 hex chars" autocomplete="off" />
                <label for="resumePassphrase">Passphrase</label>
                <input id="resumePassphrase" type="password" placeholder="Optional for now" autocomplete="current-password" />
                <small id="resumeHelp" class="modal-hint">Enter a previous session ID. Passphrase is required if the session was encrypted.</small>
                <div id="resumeError" class="modal-error" role="alert" aria-live="assertive"></div>
                <div id="recentSessions" class="recent-sessions"></div>
            </div>
            <div class="modal-actions">
                <button id="resumeCancel" type="button" class="secondary">Cancel</button>
                <button id="resumeOk" type="button" class="primary">Open Session</button>
            </div>
        </div>
    </div>
    <div id="lockOverlay" class="modal-overlay hidden" aria-hidden="true">
        <div class="modal" role="dialog" aria-modal="true" aria-labelledby="lockTitle" aria-describedby="lockHelp">
            <h3 id="lockTitle" class="modal-title">Encrypt Session</h3>
            <div class="modal-form">
                <label for="lockPassphrase">Passphrase</label>
                <input id="lockPassphrase" type="password" placeholder="At least 8 characters" autocomplete="new-password" />
                <small id="lockHelp" class="modal-hint">Minimum length: 8 characters.</small>
            </div>
            <div class="modal-actions">
                <button id="lockCancel" type="button" class="secondary">Cancel</button>
                <button id="lockOk" type="button" class="primary">Encrypt</button>
            </div>
        </div>
    </div>

    <div class="header">
        <div class="header-row">
            <h1>Local Scrubber</h1>
            <a class="header-link" href="docs/readme.php" target="_blank" rel="noopener">About / Readme</a>
        </div>
        <p class="subtitle">Reversible Sensitive Data Anonymization</p>
    </div>

    <div class="session-bar">
        <div class="session-info">
            <span class="label">Session:</span>
            <code id="sessionId"><?php echo htmlspecialchars($sessionId, ENT_QUOTES, 'UTF-8'); ?></code>
            <button id="copySessionBtn" class="icon-btn" type="button" title="Copy session id" aria-label="Copy session id">⧉</button>
            <span id="sessionStatus" role="status" aria-live="polite" aria-atomic="true" class="session-status <?php echo $isEncrypted ? 'encrypted' : 'plain'; ?>">
                <?php echo $isEncrypted ? 'Encrypted' : 'Not Encrypted'; ?>
            </span>
        </div>

        <div class="session-actions">
            <button id="resumeBtn" type="button" class="secondary">Resume Session</button>
            <button id="encryptBtn" type="button" class="secondary">Encrypt Session</button>
            <button id="lockBtn" type="button" class="secondary">Exit Session</button>
            <button id="refreshHistoryBtn" type="button" class="secondary">Refresh History</button>
            <button id="clearBtn" type="button" class="danger">Clear Session</button>
        </div>
    </div>

    <div class="section scrub-section">
        <div class="section-header">
            <h2>Scrub Phase</h2>
            <button id="scrubBtn" type="button" class="primary">Scrub</button>
        </div>

        <div class="grid-2">
            <div class="pane">
                <label for="rawInput">Raw Input (Sensitive)</label>
                <div class="pane-actions">
                    <button type="button" class="secondary small" data-action="paste" data-target="rawInput">Paste</button>
                    <button type="button" class="secondary small" id="pasteScrubBtn">Paste + Scrub</button>
                    <button type="button" class="secondary small" data-action="copy" data-target="rawInput">Copy</button>
                </div>
                <textarea id="rawInput" placeholder="Paste logs, code, tickets, etc..."></textarea>
                <div id="rawInputMeta" class="textarea-meta">Chars: 0 | Lines: 0</div>
                <div id="feedbackRawInput" class="pane-feedback" aria-live="polite" aria-atomic="true"></div>
            </div>

            <div class="pane">
                <label for="scrubbedOutput">Scrubbed Output (Safe to Send)</label>
                <div class="pane-actions">
                    <button type="button" class="secondary small" data-action="copy" data-target="scrubbedOutput">Copy</button>
                </div>
                <textarea id="scrubbedOutput" readonly></textarea>
                <div id="scrubbedOutputMeta" class="textarea-meta">Chars: 0 | Lines: 0</div>
                <div id="feedbackScrubbedOutput" class="pane-feedback" aria-live="polite" aria-atomic="true"></div>
            </div>
        </div>

        <div class="stats-bar">
            <span id="scrubStats" role="status" aria-live="polite" aria-atomic="true">No operations yet.</span>
        </div>
    </div>

    <div class="section restore-section">
        <div class="section-header">
                <h2>Restore Phase</h2>
                <div class="section-actions">
                <button id="quickTestBtn" type="button" class="secondary">Quick Test</button>
                <button id="restoreBtn" type="button" class="primary">Restore</button>
            </div>
        </div>

        <div class="grid-2">
            <div class="pane">
                <label for="llmInput">LLM Response (Scrubbed)</label>
                <div class="pane-actions">
                    <button type="button" class="secondary small" data-action="paste" data-target="llmInput">Paste</button>
                    <button type="button" class="secondary small" id="pasteRestoreBtn">Paste + Restore</button>
                    <button type="button" class="secondary small" data-action="copy" data-target="llmInput">Copy</button>
                </div>
                <textarea id="llmInput" placeholder="Paste LLM response here..."></textarea>
                <div id="llmInputMeta" class="textarea-meta">Chars: 0 | Lines: 0</div>
                <div id="feedbackLlmInput" class="pane-feedback" aria-live="polite" aria-atomic="true"></div>
            </div>

            <div class="pane">
                <label for="restoredOutput">Restored Output (Sensitive)</label>
                <div class="pane-actions">
                    <button type="button" class="secondary small" data-action="copy" data-target="restoredOutput">Copy</button>
                </div>
                <textarea id="restoredOutput" readonly></textarea>
                <div id="restoredOutputMeta" class="textarea-meta">Chars: 0 | Lines: 0</div>
                <div id="feedbackRestoredOutput" class="pane-feedback" aria-live="polite" aria-atomic="true"></div>
            </div>
        </div>

        <div class="stats-bar">
            <span id="quickTestStatus" role="status" aria-live="polite" aria-atomic="true">Quick Test: idle.</span>
        </div>
    </div>

    <div class="section history-section">
        <div class="section-header">
            <h2>Session History</h2>
        </div>

        <div id="historyContainer" aria-live="polite">
            <p class="empty-history">No history yet.</p>
        </div>
    </div>

    <div class="section ruleset-section">
        <div class="section-header">
            <h2>Rulesets</h2>
        </div>
        <div class="ruleset-actions">
            <label class="file-upload-label" for="rulesetFile">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor" class="file-icon">
                    <path d="M.5 9.9a.5.5 0 0 1 .5.5v2.5a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-2.5a.5.5 0 0 1 1 0v2.5a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2v-2.5a.5.5 0 0 1 .5-.5z"/>
                    <path d="M7.646 1.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1-.708.708L8.5 2.707V11.5a.5.5 0 0 1-1 0V2.707L5.354 4.854a.5.5 0 1 1-.708-.708l3-3z"/>
                </svg>
                <span id="fileNameDisplay">Choose Ruleset File</span>
            </label>
            <input id="rulesetFile" type="file" accept=".json,.scrubrules.json" class="hidden-file-input" />
            <button id="uploadRulesetBtn" type="button" class="secondary">Upload Ruleset</button>
            <button id="backupRulesBtn" type="button" class="secondary">Download Backup Copy</button>
        </div>
        <div id="rulesetList" class="ruleset-list" aria-live="polite">
            <p class="empty-history">Loading rulesets...</p>
        </div>
    </div>

</main>

</body>
</html>
