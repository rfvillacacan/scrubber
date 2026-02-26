<?php
declare(strict_types=1);

class RulesRegistry {

    private array $rules = [];
    private array $rulesets = [];
    private array $rulesetErrors = [];
    private ?Logger $logger;
    private array $enabledMap;

    public function __construct(string $rulesDir, ?Logger $logger = null, array $enabledMap = []) {
        $this->logger = $logger;
        $this->enabledMap = $enabledMap;
        $files = array_values(array_unique(array_merge(
            glob($rulesDir . '/*.scrubrules.json') ?: [],
            glob($rulesDir . '/*.json') ?: []
        )));

        foreach ($files as $file) {
            $this->loadRuleset($file);
        }

        usort($this->rules, fn($a, $b) => $b['final_priority'] <=> $a['final_priority']);
    }

    private function loadRuleset(string $file): void {
        $raw = file_get_contents($file);
        if ($raw === false) {
            $this->logger?->warn('Ruleset load failed', ['file' => $file]);
            $this->rulesetErrors[basename($file)][] = 'Failed to read ruleset file.';
            return;
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            $this->logger?->warn('Ruleset JSON invalid', ['file' => $file]);
            $this->rulesetErrors[basename($file)][] = 'Invalid JSON.';
            return;
        }

        if (!isset($data['ruleset_id'], $data['priority_base'], $data['rules']) || !is_array($data['rules'])) {
            $this->logger?->warn('Ruleset missing required fields', ['file' => $file]);
            $this->rulesetErrors[basename($file)][] = 'Missing required fields.';
            return;
        }

        $rulesetId = (string)$data['ruleset_id'];
        $priorityBase = (int)$data['priority_base'];
        $enabled = $this->enabledMap[$rulesetId] ?? true;

        $this->rulesets[$rulesetId] = [
            'ruleset_id' => $rulesetId,
            'version' => $data['version'] ?? '0.0.0',
            'description' => $data['description'] ?? '',
            'priority_base' => $priorityBase,
            'file' => $file,
            'enabled' => $enabled,
            'rule_count' => is_array($data['rules']) ? count($data['rules']) : 0
        ];

        foreach ($data['rules'] as $rule) {
            if (!is_array($rule) || empty($rule['enabled'])) {
                continue;
            }

            if (!isset($rule['id'], $rule['pattern'], $rule['priority'])) {
                continue;
            }

            $flags = $rule['flags'] ?? '';
            $pattern = '/' . $rule['pattern'] . '/' . $flags;

            if (@preg_match($pattern, '') === false) {
                $this->logger?->warn('Rule pattern invalid', ['file' => $file, 'rule_id' => $rule['id'] ?? null]);
                $this->rulesetErrors[$rulesetId][] = 'Invalid regex for rule: ' . ($rule['id'] ?? 'UNKNOWN');
                continue;
            }

            $this->rules[] = [
                'ruleset_id' => $rulesetId,
                'rule_id' => (string)$rule['id'],
                'regex' => $pattern,
                'validator' => $rule['validation'] ?? null,
                'final_priority' => $priorityBase + (int)$rule['priority'],
                'enabled' => $enabled,
                // NEW: Configuration fields loaded from JSON
                'generator' => $rule['generator'] ?? null,
                'cache_type' => $rule['cache_type'] ?? 'local',
                'data_type' => $rule['data_type'] ?? null,
                'skip_length_adjust' => $rule['skip_length_adjust'] ?? false,
                'normalize' => $rule['normalize'] ?? null,
            ];
        }
    }

    public function getRules(): array {
        return array_values(array_filter($this->rules, fn($rule) => $rule['enabled']));
    }

    public function getRulesets(): array {
        $out = [];
        foreach ($this->rulesets as $rulesetId => $meta) {
            $errors = $this->rulesetErrors[$rulesetId] ?? [];
            $out[] = array_merge($meta, [
                'errors' => $errors,
                'valid' => count($errors) === 0
            ]);
        }
        foreach ($this->rulesetErrors as $key => $errors) {
            if (isset($this->rulesets[$key])) {
                continue;
            }
            $out[] = [
                'ruleset_id' => $key,
                'version' => '0.0.0',
                'description' => 'Invalid ruleset file',
                'priority_base' => 0,
                'file' => $key,
                'enabled' => false,
                'rule_count' => 0,
                'errors' => $errors,
                'valid' => false
            ];
        }
        return $out;
    }

    public function getRulesetFile(string $rulesetId): ?string {
        return $this->rulesets[$rulesetId]['file'] ?? null;
    }
}
