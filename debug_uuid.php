<?php
declare(strict_types=1);

$input = 'Trace: 00000000-0000-0000-0000-000000000001';

echo "Input: $input\n\n";

// Test TRACE_ID pattern
$tracePattern = '/\b(?:REQUEST-ID|REQUEST_ID|TRACE-ID|TRACE_ID)\s*[:=]\s*([A-F0-9-]{16,})\b/i';
if (preg_match($tracePattern, $input, $matches)) {
    echo "TRACE_ID matches!\n";
    echo "Full match: {$matches[0]}\n";
    if (isset($matches[1])) {
        echo "Captured group: {$matches[1]}\n";
    }
} else {
    echo "TRACE_ID does not match\n";
}

echo "\n";

// Test UUID pattern
$uuidPattern = '/\b[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\b/i';
if (preg_match($uuidPattern, $input, $matches)) {
    echo "UUID matches!\n";
    echo "Full match: {$matches[0]}\n";
} else {
    echo "UUID does not match (third segment must start with 1-5, but this starts with 0000)\n";
}

echo "\n";

// Check if TRACE_ID pattern is capturing too much
$testInput = 'Trace: 00000000-0000-0000-0000-000000000001';
if (preg_match($tracePattern, $testInput, $matches)) {
    echo "TRACE_ID matches: {$matches[0]}\n";
    if (isset($matches[1])) {
        echo "Captured: {$matches[1]} (length: " . strlen($matches[1]) . ")\n";
        echo "This captures from '00000000-0000-0000-0000-000000000001'\n";
        echo "Length captured: " . strlen('00000000-0000-0000-0000-000000000001') . "\n";
    }
}
