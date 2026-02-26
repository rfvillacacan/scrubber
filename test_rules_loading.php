<?php
declare(strict_types=1);

require_once 'lib/RulesRegistry.php';
require_once 'lib/Logger.php';

// Create a simple logger
class TestLogger {
    public function warn($message, $context = []) {
        echo "WARN: $message\n";
        if (!empty($context)) {
            echo "CONTEXT: " . print_r($context, true) . "\n";
        }
    }
}

$logger = new TestLogger();
$rulesDir = 'rules';

echo "Testing rules loading...\n";
echo "Rules directory: $rulesDir\n\n";

$registry = new RulesRegistry($rulesDir, $logger);

$rulesets = $registry->getRulesets();
echo "Loaded " . count($rulesets) . " rulesets:\n";

foreach ($rulesets as $ruleset) {
    $status = $ruleset['valid'] ? '✓' : '✗';
    echo "  $status {$ruleset['ruleset_id']} (v{$ruleset['version']}) - {$ruleset['rule_count']} rules\n";
    if (!$ruleset['valid'] && !empty($ruleset['errors'])) {
        echo "    ERRORS: " . implode(', ', $ruleset['errors']) . "\n";
    }
}

echo "\nTotal rules loaded: " . count($registry->getRules()) . "\n";

echo "\nRules by priority:\n";
$rules = $registry->getRules();
foreach ($rules as $rule) {
    echo "  {$rule['ruleset_id']}:{$rule['rule_id']} (Priority: {$rule['final_priority']})\n";
}

echo "\nTesting pattern validation...\n";
$testPatterns = [
    'AKIA1234567890ABCDEF',  // AWS access key
    's3://my-bucket',        // S3 bucket
    '0x1234567890abcdef1234567890abcdef12345678',  // Ethereum address
    'jdbc:mysql://localhost:3306/mydb?user=root&password=secret',  // JDBC
    'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIiwibmFtZSI6IkpvaG4gRG9lIiwiaWF0IjoxNTE2MjM5MDIyfQ.SflKxwRJSMeKKF2QT4fwpMeJf36POk6yJV_adQssw5c',  // JWT
    '-----BEGIN RSA PRIVATE KEY-----',  // SSH private key
    'i-01234567890abcdef',  // EC2 instance ID
    'sk_live_123456789012345678901234',  // Stripe API key
];

foreach ($testPatterns as $pattern) {
    $matched = false;
    foreach ($rules as $rule) {
        if (@preg_match($rule['regex'], $pattern)) {
            $matched = true;
            echo "  ✓ Matched: {$rule['ruleset_id']}:{$rule['rule_id']} - $pattern\n";
            break;
        }
    }
    if (!$matched) {
        echo "  ✗ No match for: $pattern\n";
    }
}