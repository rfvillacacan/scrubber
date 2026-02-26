<?php
declare(strict_types=1);

require_once __DIR__ . '/DataGenerator.php';

class ScrubberEngine {

    private RulesRegistry $registry;
    private Storage $storage;
    private ?Logger $logger;

    // Static caches for consistency
    private static array $globalCache = [];
    private static array $localCache = [];

    public function __construct(RulesRegistry $registry, Storage $storage, ?Logger $logger = null) {
        $this->registry = $registry;
        $this->storage = $storage;
        $this->logger = $logger;
    }

    public function scrubText(string $input): array {
        $rules = $this->registry->getRules();
        $accepted = [];
        $occupied = [];
        $stats = [];
        $startTime = microtime(true);

        $placeholderRanges = $this->findPlaceholderRanges($input);
        foreach ($placeholderRanges as $range) {
            $occupied[] = $range;
        }

        foreach ($rules as $rule) {
            if (!preg_match_all($rule['regex'], $input, $matches, PREG_OFFSET_CAPTURE)) {
                continue;
            }

            // Check if pattern has capturing groups (group 1 exists)
            $hasCapturingGroup = isset($matches[1]) && count($matches[1]) > 0;

            foreach ($matches[0] as $index => $match) {
                $fullMatch = $match[0];
                $offset = $match[1];

                if ($fullMatch === '') {
                    continue;
                }

                // If there's a capturing group, use it for the value to replace
                if ($hasCapturingGroup) {
                    $capturedMatch = $matches[1][$index];
                    $matchValue = $capturedMatch[0];
                    $start = $capturedMatch[1];
                    $length = strlen($matchValue);
                } else {
                    $matchValue = $fullMatch;
                    $start = $offset;
                    $length = strlen($matchValue);
                }

                $end = $start + $length;

                if ($this->isOverlapping($start, $end, $occupied)) {
                    continue;
                }

                if ($rule['validator'] && !Validator::validate((string)$rule['validator'], $matchValue)) {
                    continue;
                }

                // Use ORIGINAL value (case-sensitive) for hash to avoid collisions
                $hash = substr(hash('sha256', $matchValue), 0, 10);
                $replacement = $this->generateReplacement($rule, $matchValue);

                $this->storage->saveMapping(
                    $rule['ruleset_id'],
                    $rule['rule_id'],
                    $matchValue,
                    $replacement,
                    $hash
                );

                $accepted[] = [
                    'start' => $start,
                    'length' => $length,
                    'replacement' => $replacement
                ];

                $occupied[] = [$start, $end];
                $stats[$rule['rule_id']] = ($stats[$rule['rule_id']] ?? 0) + 1;
            }
        }

        usort($accepted, fn($a, $b) => $b['start'] <=> $a['start']);

        $output = $input;
        foreach ($accepted as $match) {
            $output = substr_replace(
                $output,
                $match['replacement'],
                $match['start'],
                $match['length']
            );
        }

        $this->storage->saveHistory($input, $output, null, null);
        $durationMs = (int)round((microtime(true) - $startTime) * 1000);
        $this->logger?->info('Scrub completed', [
            'replacements' => count($accepted),
            'duration_ms' => $durationMs
        ]);

        return [
            'scrubbed_text' => $output,
            'count' => count($accepted),
            'stats' => $stats
        ];
    }

    public function restoreText(string $input): string {
        $startTime = microtime(true);
        $mappings = $this->storage->getMappings();

        // Sort by replacement length (longest first) to avoid partial replacements
        usort($mappings, fn($a, $b) => strlen($b['placeholder']) <=> strlen($a['placeholder']));

        foreach ($mappings as $map) {
            $input = str_replace($map['placeholder'], $map['original_value'], $input);
        }

        $durationMs = (int)round((microtime(true) - $startTime) * 1000);
        $this->logger?->info('Restore completed', [
            'replacements' => count($mappings),
            'duration_ms' => $durationMs
        ]);

        return $input;
    }

    private function generateReplacement(array $rule, string $originalValue): string {
        $ruleId = $rule['rule_id'];

        // Use ORIGINAL value (case-sensitive) for hash to avoid collisions
        // Normalization is only for matching logic, not for unique identification
        $hash = substr(hash('sha256', $originalValue), 0, 10);

        // Determine cache type from rule config (default: local)
        $cacheType = $rule['cache_type'] ?? 'local';

        // Determine cache key
        if ($cacheType === 'global') {
            $dataType = $rule['data_type'] ?? $ruleId;
            $cacheKey = $dataType . '_' . $hash;
            $cache = &self::$globalCache;
        } else {
            $cacheKey = $rule['ruleset_id'] . '_' . $ruleId . '_' . $hash;
            $cache = &self::$localCache;
        }

        // Check cache
        if (isset($cache[$cacheKey])) {
            return $cache[$cacheKey];
        }

        // Generate fake value
        $fakeValue = $this->callGenerator($rule, $originalValue);

        // Length adjustment (skip if rule says so)
        if (!($rule['skip_length_adjust'] ?? false)) {
            $minLen = strlen($originalValue) * 0.5;
            $maxLen = strlen($originalValue) * 1.5;

            if (strlen($fakeValue) > $maxLen || strlen($fakeValue) < $minLen) {
                $targetLength = strlen($originalValue);
                if (strlen($fakeValue) > $targetLength) {
                    $fakeValue = substr($fakeValue, 0, $targetLength);
                } elseif (strlen($fakeValue) < $targetLength) {
                    $padLength = $targetLength - strlen($fakeValue);
                    $fakeValue = $fakeValue . substr(str_repeat('0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ', (int)ceil($padLength / 36)), 0, $padLength);
                }
            }
        }

        // Store in cache
        $cache[$cacheKey] = $fakeValue;

        return $fakeValue;
    }

    private function callGenerator(array $rule, string $originalValue): string {
        $generator = $rule['generator'] ?? null;

        if (!$generator) {
            return DataGenerator::generateSmartString($originalValue);
        }

        // Parse generator: "method" or "class::method"
        if (str_contains($generator, '::')) {
            [$class, $method] = explode('::', $generator, 2);
            if (!class_exists($class) || !method_exists($class, $method)) {
                return DataGenerator::generateSmartString($originalValue);
            }
            return $class::$method();
        }

        // DataGenerator methods
        $method = 'generate' . ucfirst($generator);
        if (method_exists(DataGenerator::class, $method)) {
            // IBAN, string, s3Bucket, and dockerRegistry generators need original value for format matching
            if (in_array($generator, ['iban', 'string', 's3Bucket', 'dockerRegistry'], true)) {
                return DataGenerator::$method($originalValue);
            }
            return DataGenerator::$method();
        }

        return DataGenerator::generateSmartString($originalValue);
    }

    private function isOverlapping(int $start, int $end, array $occupied): bool {
        foreach ($occupied as $range) {
            [$s, $e] = $range;
            if ($start < $e && $end > $s) {
                return true;
            }
        }

        return false;
    }

    private function findPlaceholderRanges(string $input): array {
        $ranges = [];
        if (preg_match_all('/(?:\[\[\[SCRUB_[A-Z0-9_]+_[A-Z0-9_]+_[a-f0-9]{10}(?:@dummy\.local)?\]\]\]|SCRUB_[A-Z0-9_]+_[A-Z0-9_]+_[a-f0-9]{10}(?:@dummy\.local)?)/', $input, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as $match) {
                $value = $match[0];
                $offset = $match[1];
                $ranges[] = [$offset, $offset + strlen($value)];
            }
        }
        return $ranges;
    }

    private function normalizeValue(array $rule, string $value): string {
        $normalize = $rule['normalize'] ?? null;
        if ($normalize === 'lower') {
            return strtolower($value);
        }
        if ($normalize === 'upper') {
            return strtoupper($value);
        }
        return $value;
    }
}
