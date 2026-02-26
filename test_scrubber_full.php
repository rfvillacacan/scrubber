<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/DataGenerator.php';
require_once __DIR__ . '/lib/ScrubberEngine.php';
require_once __DIR__ . '/lib/RulesRegistry.php';
require_once __DIR__ . '/lib/Storage.php';
require_once __DIR__ . '/lib/Logger.php';
require_once __DIR__ . '/lib/Validator.php';
require_once __DIR__ . '/lib/Crypto.php';

echo "=== Full Scrubber Test ===\n\n";

// Initialize components
$storage = new Storage(':memory:', 'test_session', null);
$logger = new Logger(__DIR__ . '/logs');
$registry = new RulesRegistry(__DIR__ . '/rules', $logger);
$engine = new ScrubberEngine($registry, $storage, $logger);

// Test data from the incident
$testData = <<<'EOD'
[2024-01-15 14:32:15] ERROR: Connection timeout to database smtp.example.com
User: john.doe@example.com attempted to connect
SMTP_PASS=EmailP@ssw0rd!
DB_USER=db_admin
DB_NAME=production_db
Server: 192.168.1.100
Port: 5432
Customer-ID: CUST-884422
sourceAccount: 123456789012
Amount: $1500.00
GET /api/v1/users HTTP/1.1
User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36
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

echo "\nMappings:\n";
echo "---------\n";
$mappings = $storage->getMappings();
foreach ($mappings as $map) {
    echo $map['original_value'] . " -> " . $map['placeholder'] . "\n";
}

echo "\n=== Test Complete ===\n";
