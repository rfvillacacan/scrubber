<?php
declare(strict_types=1);

class Logger {

    private string $filePath;
    private bool $enabled;

    public function __construct(string $logDir, bool $enabled = true) {
        $this->enabled = $enabled;
        if (!is_dir($logDir)) {
            mkdir($logDir, 0700, true);
        }
        $this->filePath = rtrim($logDir, '/').'/scrubber.log';
    }

    public function info(string $message, array $context = []): void {
        $this->write('INFO', $message, $context);
    }

    public function warn(string $message, array $context = []): void {
        $this->write('WARN', $message, $context);
    }

    public function error(string $message, array $context = []): void {
        $this->write('ERROR', $message, $context);
    }

    private function write(string $level, string $message, array $context): void {
        if (!$this->enabled) {
            return;
        }
        $safeContext = $this->sanitizeContext($context);
        $timestamp = gmdate('Y-m-d\TH:i:s\Z');
        $entry = [
            'ts' => $timestamp,
            'level' => $level,
            'message' => $message,
            'context' => $safeContext
        ];
        file_put_contents($this->filePath, json_encode($entry, JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    private function sanitizeContext(array $context): array {
        $sanitized = [];
        foreach ($context as $key => $value) {
            $k = (string)$key;
            if ($k === 'session_id' && is_string($value) && $value !== '') {
                $sanitized[$k] = 'sid_' . substr(hash('sha256', $value), 0, 12);
                continue;
            }

            if (($k === 'db_path' || $k === 'file' || $k === 'path') && is_string($value) && $value !== '') {
                $sanitized[$k] = basename($value);
                continue;
            }

            if (is_array($value)) {
                $sanitized[$k] = $this->sanitizeContext($value);
                continue;
            }

            $sanitized[$k] = $value;
        }
        return $sanitized;
    }
}
