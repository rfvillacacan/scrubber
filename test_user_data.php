<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/DataGenerator.php';
require_once __DIR__ . '/lib/ScrubberEngine.php';
require_once __DIR__ . '/lib/RulesRegistry.php';
require_once __DIR__ . '/lib/Storage.php';
require_once __DIR__ . '/lib/Logger.php';
require_once __DIR__ . '/lib/Validator.php';
require_once __DIR__ . '/lib/Crypto.php';

echo "=== Test User Data ===\n\n";

// Initialize components
$storage = new Storage(':memory:', 'test_session', null);
$logger = new Logger(__DIR__ . '/logs');
$registry = new RulesRegistry(__DIR__ . '/rules', $logger);
$engine = new ScrubberEngine($registry, $storage, $logger);

// Test data from the user
$testData = <<<'EOD'
INCOMING REQUEST BEFORE FAILURE
--------------------------------------------------------------------------------

User Email: john.doe@corp.internal
Client IP: 192.168.10.45
Request-ID: 91a7bc22-44ff-4c21-a321-77abcc998811

Authorization Header:
Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiJqb2huLmRvZUBjb3JwLmludGVybmFsIiwiaWF0IjoxNjE2MjM5MDIyfQ.invalidsignature123
EOD;

echo "Original Text:\n";
echo "---------------\n";
echo $testData . "\n\n";

echo "Scrubbing...\n\n";

$result = $engine->scrubText($testData);

echo "Scrubbed Text:\n";
echo "---------------\n";
echo $result['scrubbed_text'] . "\n\n";

echo "Statistics:\n";
echo "-----------\n";
echo "Replacements made: " . $result['count'] . "\n";
foreach ($result['stats'] as $ruleId => $count) {
    echo "  $ruleId: $count\n";
}

echo "\n=== Test Complete ===\n";
