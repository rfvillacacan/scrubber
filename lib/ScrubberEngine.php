<?php
declare(strict_types=1);

class ScrubberEngine {

    private RulesRegistry $registry;
    private Storage $storage;
    private ?Logger $logger;

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

            foreach ($matches[0] as $match) {
                $matchValue = $match[0];
                $offset = $match[1];

                if ($matchValue === '') {
                    continue;
                }

                $length = strlen($matchValue);
                $start = $offset;
                $end = $offset + $length;

                if ($this->isOverlapping($start, $end, $occupied)) {
                    continue;
                }

                if ($rule['validator'] && !Validator::validate((string)$rule['validator'], $matchValue)) {
                    continue;
                }

                $hash = substr(hash('sha256', $matchValue), 0, 10);
                $placeholder = $this->buildPlaceholder(
                    $rule['ruleset_id'],
                    $rule['rule_id'],
                    $hash
                );

                $this->storage->saveMapping(
                    $rule['ruleset_id'],
                    $rule['rule_id'],
                    $matchValue,
                    $placeholder,
                    $hash
                );

                $accepted[] = [
                    'start' => $start,
                    'length' => $length,
                    'placeholder' => $placeholder
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
                $match['placeholder'],
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

        usort($mappings, fn($a, $b) => strlen($b['placeholder']) <=> strlen($a['placeholder']));

        foreach ($mappings as $map) {
            $variants = $this->placeholderVariants($map['placeholder']);
            foreach ($variants as $variant) {
                $input = str_replace($variant, $map['original_value'], $input);
            }
        }

        $durationMs = (int)round((microtime(true) - $startTime) * 1000);
        $this->logger?->info('Restore completed', [
            'replacements' => count($mappings),
            'duration_ms' => $durationMs
        ]);

        return $input;
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

    private function buildPlaceholder(string $rulesetId, string $ruleId, string $hash): string {
        return "[[[SCRUB_{$rulesetId}_{$ruleId}_{$hash}@dummy.local]]]";
    }

    private function stripPlaceholderWrapper(string $placeholder): string {
        return trim($placeholder, '[]');
    }

    private function isBarePlaceholder(string $value): bool {
        return (bool)preg_match('/^SCRUB_[A-Z0-9_]+_[A-Z0-9_]+_[a-f0-9]{10}@dummy\.local$/', $value);
    }

    private function placeholderVariants(string $placeholder): array {
        $variants = [];

        $variants[] = $placeholder;

        $bare = $this->stripPlaceholderWrapper($placeholder);
        $variants[] = $bare;

        if (preg_match('/^SCRUB_([A-Z0-9_]+)_([A-Z0-9_]+)_([a-f0-9]{10})(@dummy\.local)?$/', $bare, $m)) {
            $core = "SCRUB_{$m[1]}_{$m[2]}_{$m[3]}";
            $with = $core . "@dummy.local";

            $variants[] = "[[[{$with}]]]";
            $variants[] = $with;
            $variants[] = "[[[{$core}]]]";
            $variants[] = $core;
        }

        return array_values(array_unique($variants));
    }
}
