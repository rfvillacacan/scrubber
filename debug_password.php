<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/DataGenerator.php';
require_once __DIR__ . '/lib/ScrubberEngine.php';
require_once __DIR__ . '/lib/RulesRegistry.php';
require_once __DIR__ . '/lib/Storage.php';
require_once __DIR__ . '/lib/Logger.php';
require_once __DIR__ . '/lib/Validator.php';
require_once __DIR__ . '/lib/Crypto.php';

$storage = new Storage(':memory:', 'test_session', null);
$logger = new Logger(__DIR__ . '/logs');
$registry = new RulesRegistry(__DIR__ . '/rules', $logger);

// Check all PASSWORD-related rules
$allRules = $registry->getRules();
echo "=== All PASSWORD-related rules ===\n";
foreach ($allRules as $rule) {
    if (stripos($rule['rule_id'], 'PASS') !== false || stripos($rule['rule_id'], 'SECRET') !== false) {
        echo "Rule ID: {$rule['rule_id']}\n";
        echo "Ruleset ID: {$rule['ruleset_id']}\n";
        echo "Enabled: " . ($rule['enabled'] ? 'yes' : 'no') . "\n";
        echo "Priority: {$rule['final_priority']}\n";
        echo "Pattern: {$rule['regex']}\n";
        echo "\n";
    }
}

// Test with simple input
echo "=== TEST: Simple password input ===\n";
$engine = new ScrubberEngine($registry, $storage, $logger);
$input = 'DB_PASS=secret and DB_PASS=secret again';
echo "Input: $input\n";
$result = $engine->scrubText($input);
echo "Output: {$result['scrubbed_text']}\n\n";

// Get stats
echo "Stats:\n";
foreach ($result['stats'] as $ruleId => $count) {
    echo "  $ruleId: $count\n";
}
