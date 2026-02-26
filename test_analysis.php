<?php
// Analysis script for scrubbing functionality
require_once 'lib/RulesRegistry.php';
require_once 'lib/ScrubberEngine.php';
require_once 'lib/Storage.php';
require_once 'lib/Validator.php';
require_once 'lib/Logger.php';

// Read test file
$testFile = 'test-sample/sensitive-info-sample.txt';
if (!file_exists($testFile)) {
    die("Test file not found: $testFile\n");
}

$testInput = file_get_contents($testFile);
echo "=== Scrubber Analysis Test ===\n\n";

// Create a temporary session for testing
$testSessionId = 'test_' . bin2hex(random_bytes(8));
$dataDir = 'data';
if (!is_dir($dataDir)) {
    mkdir($dataDir, 0700, true);
}

$dbPath = $dataDir . "/session_{$testSessionId}.sqlite";
$logger = new Logger($dataDir . '/logs', true);
$storage = new Storage($dbPath, $testSessionId, $logger);
$rulesRegistry = new RulesRegistry('rules', $logger, []);
$scrubber = new ScrubberEngine($rulesRegistry, $storage, $logger);

echo "Sample input length: " . strlen($testInput) . " characters\n\n";

try {
    // Test scrubbing
    echo "Scrubbing text...\n";
    $result = $scrubber->scrubText($testInput);
    
    echo "Scrubbing Results:\n";
    echo "-----------------\n";
    echo "Total replacements: " . $result['count'] . "\n";
    echo "Processing stats: " . json_encode($result['stats'], JSON_PRETTY_PRINT) . "\n\n";
    
    // Analyze scrubbed text
    $scrubbedText = $result['scrubbed_text'];
    echo "Scrubbed text length: " . strlen($scrubbedText) . " characters\n\n";
    
    // Check for any remaining sensitive patterns
    $remainingSensitive = [];
    $patternsToCheck = [
        '/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/' => 'email',
        '/\d{3}-\d{2}-\d{4}/' => 'ssn',
        '/\b\d{4}[-\s]?\d{4}[-\s]?\d{4}[-\s]?\d{4}\b/' => 'credit_card',
        '/\b(?:phone|mobile)\s*[:=]?\s*\+?\d[\d\s().-]{6,}\b/i' => 'phone',
    ];
    
    foreach ($patternsToCheck as $pattern => $type) {
        if (preg_match_all($pattern, $scrubbedText, $matches)) {
            $remainingSensitive[$type] = count($matches[0]);
        }
    }
    
    if (!empty($remainingSensitive)) {
        echo "WARNING: Found remaining sensitive patterns:\n";
        foreach ($remainingSensitive as $type => $count) {
            echo "  - $type: $count occurrences\n";
        }
        echo "\n";
    } else {
        echo "✅ No remaining sensitive patterns detected!\n\n";
    }
    
    // Test restore
    echo "Testing restoration...\n";
    $restoredText = $scrubber->restoreText($scrubbedText);
    echo "Restore complete. Length: " . strlen($restoredText) . " characters\n\n";
    
    // Verify restore
    if ($testInput === $restoredText) {
        echo "✅ SUCCESS: Original text perfectly restored!\n";
    } else {
        echo "❌ FAILURE: Text does not match original!\n";
        echo "Length difference: " . (strlen($testInput) - strlen($restoredText)) . " characters\n\n";
    }
    
    // Show sample mappings
    echo "Sample database mappings:\n";
    echo "------------------------\n";
    $mappings = $storage->getMappings();
    $shown = 0;
    foreach ($mappings as $mapping) {
        echo "- " . $mapping['placeholder'] . " → " . $mapping['original_value'] . "\n";
        $shown++;
        if ($shown >= 5) break;
    }
    if (count($mappings) > 5) {
        echo "... and " . (count($mappings) - 5) . " more mappings\n";
    }
    echo "\n";
    
    // Performance metrics
    $history = $storage->getHistory();
    if (!empty($history)) {
        $latest = $history[0];
        echo "Performance metrics:\n";
        echo "-------------------\n";
        echo "Processing time: " . (microtime(true) - $start) . " seconds\n";
        echo "Database entries: " . count($mappings) . "\n";
        echo "History records: " . count($history) . "\n";
    }
    
    // Clean up
    $storage->finalize();
    if (file_exists($dbPath)) {
        unlink($dbPath);
    }
    
} catch (Throwable $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " Line: " . $e->getLine() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
    
    // Clean up on error
    if (file_exists($dbPath)) {
        unlink($dbPath);
    }
}
echo "\n=== Analysis Complete ===\n";
?>
