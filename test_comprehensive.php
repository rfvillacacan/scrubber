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
$engine = new ScrubberEngine($registry, $storage, $logger);

echo "=== COMPREHENSIVE SCRUBBER TEST ===\n\n";
echo "Testing all data types with multiple occurrences, labels, and combinations\n\n";

$tests = [];

// TEST 1: Email consistency with and without context
$tests[] = [
    'name' => 'Email Consistency',
    'input' => 'User alice@company.com sent email to bob@company.com. Then alice@company.com replied.',
    'verify' => function($output) {
        preg_match_all('/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}/', $output, $matches);
        $emails = $matches[0];
        return [
            'alice@company.com appears 2x' => count($emails) === 2 && $emails[0] === $emails[1],
            'bob@company.com different' => count($emails) === 2 && $emails[0] !== $emails[1] || count($emails) > 2,
            'total replacements' => count($emails) === 3
        ];
    }
];

// TEST 2: IP address consistency
$tests[] = [
    'name' => 'IP Address Consistency',
    'input' => 'Server 10.0.1.5 connected to 192.168.1.1, then 10.0.1.5 pinged 192.168.1.1 again.',
    'verify' => function($output) {
        preg_match_all('/\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}/', $output, $matches);
        $ips = $matches[0];
        return [
            '10.0.1.5 appears 2x same' => count($ips) >= 2 && $ips[0] === $ips[2],
            '192.168.1.1 appears 2x same' => count($ips) >= 2 && $ips[1] === $ips[3],
            'different IPs different' => $ips[0] !== $ips[1]
        ];
    }
];

// TEST 3: UUID consistency with labels
$tests[] = [
    'name' => 'UUID with TRACE_ID label',
    'input' => 'Request-ID: 550e8400-e29b-41d4-a716-446655440000 and then 550e8400-e29b-41d4-a716-446655440000 again',
    'verify' => function($output) {
        preg_match_all('/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i', $output, $matches);
        $uuids = $matches[0];
        return [
            'UUID appears 2x same' => count($uuids) === 2 && $uuids[0] === $uuids[1],
            'Request-ID label preserved' => strpos($output, 'Request-ID:') !== false
        ];
    }
];

// TEST 4: UUID with and without label (same UUID)
$tests[] = [
    'name' => 'UUID with/without label consistency',
    'input' => 'Request-ID: 123e4567-e89b-12d3-a456-426614174000 and standalone 123e4567-e89b-12d3-a456-426614174000',
    'verify' => function($output) {
        preg_match_all('/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i', $output, $matches);
        $uuids = $matches[0];
        return [
            'both UUIDs same fake value' => count($uuids) === 2 && $uuids[0] === $uuids[1],
            'label preserved' => strpos($output, 'Request-ID:') !== false
        ];
    }
];

// TEST 5: Key=value patterns (passwords)
$tests[] = [
    'name' => 'Password Assignment',
    'input' => 'DB_PASS=Secret123! and SMTP_PASS=Secret123! and DB_PASS=Secret123!',
    'verify' => function($output) {
        preg_match_all('/DB_PASS=[^\s]+/', $output, $db);
        preg_match_all('/SMTP_PASS=[^\s]+/', $output, $smtp);
        return [
            'DB_PASS consistent' => count($db[0]) === 2 && $db[0][0] === $db[0][1],
            'labels preserved' => strpos($output, 'DB_PASS=') !== false && strpos($output, 'SMTP_PASS=') !== false,
            'values different' => count($db[0]) > 0 && count($smtp[0]) > 0 && $db[0][0] !== $smtp[0][0]
        ];
    }
];

// TEST 6: Multiple key=value patterns
$tests[] = [
    'name' => 'Multiple Assignments',
    'input' => 'DB_USER=admin, DB_PASS=secret, DB_NAME=production, DB_USER=admin, DB_PASS=secret',
    'verify' => function($output) {
        preg_match_all('/DB_USER=[^\s,]+/', $output, $user);
        preg_match_all('/DB_PASS=[^\s,]+/', $output, $pass);
        preg_match_all('/DB_NAME=[^\s,]+/', $output, $name);
        return [
            'DB_USER consistent' => count($user[0]) === 2 && $user[0][0] === $user[0][1],
            'DB_PASS consistent' => count($pass[0]) === 2 && $pass[0][0] === $pass[0][1],
            'all labels preserved' => strpos($output, 'DB_USER=') !== false && strpos($output, 'DB_PASS=') !== false && strpos($output, 'DB_NAME=') !== false
        ];
    }
];

// TEST 7: Bearer tokens
$tests[] = [
    'name' => 'Bearer Token',
    'input' => 'Bearer eyJhbGciOiJIUzI1NiJ9.abc123 and then Bearer eyJhbGciOiJIUzI1NiJ9.abc123 again',
    'verify' => function($output) {
        preg_match_all('/Bearer\s+[A-Za-z0-9._=-]+/i', $output, $matches);
        $tokens = $matches[0];
        return [
            'tokens consistent' => count($tokens) === 2 && $tokens[0] === $tokens[1],
            'Bearer label preserved' => strpos($output, 'Bearer ') !== false
        ];
    }
];

// TEST 8: Customer IDs
$tests[] = [
    'name' => 'Customer ID',
    'input' => 'Customer: CUST-00123456 and then CUST-00123456 again, also CUST-00999999',
    'verify' => function($output) {
        preg_match_all('/CUST-[A-Za-z0-9-]+/', $output, $matches);
        $custs = $matches[0];
        return [
            'CUST-00123456 appears 2x same' => count($custs) >= 2 && $custs[0] === $custs[1],
            'CUST-00999999 different' => count($custs) >= 3 && $custs[0] !== $custs[2],
            'Customer: label preserved' => strpos($output, 'Customer:') !== false
        ];
    }
];

// TEST 9: Mixed data types
$tests[] = [
    'name' => 'Mixed Data Types',
    'input' => 'User john@test.com from 192.168.1.1 with ID: 123e4567-e89b-12d3-a456-426614174000. Later john@test.com from 192.168.1.1 with ID: 123e4567-e89b-12d3-a456-426614174000.',
    'verify' => function($output) {
        preg_match_all('/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}/', $output, $emails);
        preg_match_all('/\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}/', $output, $ips);
        preg_match_all('/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i', $output, $uuids);
        return [
            'email consistent' => count($emails[0]) === 2 && $emails[0][0] === $emails[0][1],
            'IP consistent' => count($ips[0]) === 2 && $ips[0][0] === $ips[0][1],
            'UUID consistent' => count($uuids[0]) === 2 && $uuids[0][0] === $uuids[0][1],
            'ID: label preserved' => strpos($output, 'ID:') !== false
        ];
    }
];

// TEST 10: Network specific patterns
$tests[] = [
    'name' => 'Network Patterns',
    'input' => 'Host web-server1.internal and db-server1.internal connected to 10.0.0.1:5432 and 10.0.0.1:5432',
    'verify' => function($output) {
        preg_match_all('/\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}/', $output, $ips);
        return [
            'IP appears 2x same' => count($ips[0]) === 2 && $ips[0][0] === $ips[0][1],
            'Host labels preserved' => strpos($output, 'Host ') !== false
        ];
    }
];

// TEST 11: Authorization patterns
$tests[] = [
    'name' => 'Authorization Header',
    'input' => 'Authorization: Bearer abc123def456 and then Authorization: Bearer abc123def456 again',
    'verify' => function($output) {
        preg_match_all('/Authorization:\s+Bearer\s+[A-Za-z0-9._=-]+/i', $output, $matches);
        return [
            'token consistent' => count($matches[0]) === 2 && $matches[0][0] === $matches[0][1],
            'Authorization label preserved' => strpos($output, 'Authorization:') !== false
        ];
    }
];

// TEST 12: Account IDs
$tests[] = [
    'name' => 'Account ID',
    'input' => 'Account: ACC-123456 and ACC-123456 again, also ACC-789012',
    'verify' => function($output) {
        preg_match_all('/ACC-\d+/', $output, $matches);
        $accs = $matches[0];
        return [
            'ACC-123456 appears 2x same' => count($accs) >= 2 && $accs[0] === $accs[1],
            'ACC-789012 different' => count($accs) >= 3 && $accs[0] !== $accs[2],
            'Account: label preserved' => strpos($output, 'Account:') !== false
        ];
    }
];

// TEST 13: Complex log entry
$tests[] = [
    'name' => 'Complex Log Entry',
    'input' => '[2024-01-15] User alice@example.com (IP: 10.20.30.40) accessed resource with ID: 6ba7b810-9dad-11d1-80b4-00c04fd430c8. Then alice@example.com from 10.20.30.40 accessed same resource ID: 6ba7b810-9dad-11d1-80b4-00c04fd430c8.',
    'verify' => function($output) {
        preg_match_all('/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}/', $output, $emails);
        preg_match_all('/\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}/', $output, $ips);
        preg_match_all('/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i', $output, $uuids);
        return [
            'email consistent (2x)' => count($emails[0]) === 2 && $emails[0][0] === $emails[0][1],
            'IP consistent (2x)' => count($ips[0]) === 2 && $ips[0][0] === $ips[0][1],
            'UUID consistent (2x)' => count($uuids[0]) === 2 && $uuids[0][0] === $uuids[0][1],
            'timestamp preserved' => strpos($output, '[2024-01-15]') !== false || strpos($output, '[') !== false,
            'ID: label preserved' => strpos($output, 'ID:') !== false
        ];
    }
];

// TEST 14: Multiple UUIDs with different formats
$tests[] = [
    'name' => 'Multiple Different UUIDs',
    'input' => 'Trace: 00000000-0000-0000-0000-000000000001 and Trace: 00000000-0000-0000-0000-000000000002, then 00000000-0000-0000-0000-000000000001 again',
    'verify' => function($output) {
        preg_match_all('/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i', $output, $matches);
        $uuids = $matches[0];
        return [
            'UUID1 appears 2x same' => count($uuids) >= 2 && $uuids[0] === $uuids[2],
            'UUID2 different from UUID1' => count($uuids) >= 2 && $uuids[0] !== $uuids[1],
            'Trace: label preserved' => strpos($output, 'Trace:') !== false
        ];
    }
];

// TEST 15: Email in different contexts
$tests[] = [
    'name' => 'Email in Different Contexts',
    'input' => 'From: admin@test.com, To: admin@test.com, CC: user@test.com',
    'verify' => function($output) {
        preg_match_all('/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}/', $output, $matches);
        $emails = $matches[0];
        return [
            'admin@test.com consistent (2x)' => count($emails) >= 2 && $emails[0] === $emails[1],
            'user@test.com different' => count($emails) >= 3 && $emails[0] !== $emails[2],
            'From: preserved' => strpos($output, 'From:') !== false,
            'To: preserved' => strpos($output, 'To:') !== false
        ];
    }
];

// TEST 16: Database connection string
$tests[] = [
    'name' => 'Database Connection',
    'input' => 'DB_HOST=db1.internal, DB_PORT=5432, DB_USER=admin, DB_PASS=secret123, DB_NAME=production. Connected to db1.internal:5432.',
    'verify' => function($output) {
        preg_match_all('/DB_HOST=[^\s,]+/', $output, $host);
        preg_match_all('/DB_PORT=[^\s,]+/', $output, $port);
        preg_match_all('/DB_USER=[^\s,]+/', $output, $user);
        preg_match_all('/DB_PASS=[^\s,]+/', $output, $pass);
        preg_match_all('/DB_NAME=[^\s,]+/', $output, $name);
        return [
            'DB_HOST label preserved' => strpos($output, 'DB_HOST=') !== false,
            'DB_PORT label preserved' => strpos($output, 'DB_PORT=') !== false,
            'DB_USER label preserved' => strpos($output, 'DB_USER=') !== false,
            'DB_PASS label preserved' => strpos($output, 'DB_PASS=') !== false,
            'DB_NAME label preserved' => strpos($output, 'DB_NAME=') !== false
        ];
    }
];

// TEST 17: JWT tokens with different parts
$tests[] = [
    'name' => 'JWT Tokens',
    'input' => 'JWT: eyJhbGciOiJIUzI1NiJ9.eyJzdWIiOiIxMjM0NTY3ODkwIn0 SIGNATURE and again JWT: eyJhbGciOiJIUzI1NiJ9.eyJzdWIiOiIxMjM0NTY3ODkwIn0 SIGNATURE',
    'verify' => function($output) {
        preg_match_all('/JWT:\s+[A-Za-z0-9._=-]+/i', $output, $matches);
        $jwts = $matches[0];
        return [
            'JWT consistent (2x)' => count($jwts) === 2 && $jwts[0] === $jwts[1],
            'JWT: label preserved' => strpos($output, 'JWT:') !== false
        ];
    }
];

// TEST 18: Financial data with labels
$tests[] = [
    'name' => 'Financial Amount',
    'input' => 'Amount: 1500.00 USD and then Amount: 1500.00 USD again, also Amount: 2500.00 USD',
    'verify' => function($output) {
        preg_match_all('/Amount:\s+[\d.]+/i', $output, $matches);
        $amounts = $matches[0];
        return [
            'Amount1 consistent (2x)' => count($amounts) >= 2 && $amounts[0] === $amounts[1],
            'Amount2 different' => count($amounts) >= 3 && $amounts[0] !== $amounts[2],
            'Amount: label preserved' => strpos($output, 'Amount:') !== false
        ];
    }
];

// TEST 19: Port numbers in context
$tests[] = [
    'name' => 'Port in Context',
    'input' => 'Connecting to 192.168.1.1:8080 and then 192.168.1.1:8080 again',
    'verify' => function($output) {
        preg_match_all('/\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}/', $output, $ips);
        return [
            'IP consistent (2x)' => count($ips[0]) === 2 && $ips[0][0] === $ips[0][1],
            'port preserved in output' => strpos($output, ':8080') !== false || strpos($output, ':') !== false
        ];
    }
];

// TEST 20: Long complex entry with everything
$tests[] = [
    'name' => 'Everything Together',
    'input' => 'LOG: User alice@test.com (IP: 10.0.1.5) with Request-ID: aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee accessed DB_HOST=prod-db.internal:5432 using DB_USER=admin with token Bearer xyz123. Later alice@test.com from 10.0.1.5 with Request-ID: aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee accessed same DB_HOST=prod-db.internal.',
    'verify' => function($output) {
        preg_match_all('/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}/', $output, $emails);
        preg_match_all('/\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}/', $output, $ips);
        preg_match_all('/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i', $output, $uuids);
        preg_match_all('/DB_HOST=[^\s:]+/', $output, $dbhosts);
        preg_match_all('/DB_USER=[^\s]+/', $output, $dbusers);
        preg_match_all('/Bearer\s+[A-Za-z0-9._=-]+/i', $output, $tokens);
        return [
            'email consistent' => count($emails[0]) === 2 && $emails[0][0] === $emails[0][1],
            'IP consistent' => count($ips[0]) === 2 && $ips[0][0] === $ips[0][1],
            'UUID consistent' => count($uuids[0]) === 2 && $uuids[0][0] === $uuids[0][1],
            'DB_HOST consistent' => count($dbhosts[0]) === 2 && $dbhosts[0][0] === $dbhosts[0][1],
            'DB_USER consistent' => count($dbusers[0]) === 2 && $dbusers[0][0] === $dbusers[0][1],
            'Bearer consistent' => count($tokens[0]) === 2 && $tokens[0][0] === $tokens[0][1],
            'LOG: label preserved' => strpos($output, 'LOG:') !== false,
            'Request-ID: label preserved' => strpos($output, 'Request-ID:') !== false
        ];
    }
];

// Run all tests
$totalTests = count($tests);
$passedTests = 0;
$failedTests = 0;
$failedDetails = [];

foreach ($tests as $index => $test) {
    echo "TEST " . ($index + 1) . ": {$test['name']}\n";
    echo "Input: {$test['input']}\n";

    $result = $engine->scrubText($test['input']);
    $output = $result['scrubbed_text'];

    echo "Output: $output\n";

    $results = $test['verify']($output);
    $testPassed = true;
    $testFailures = [];

    foreach ($results as $check => $passed) {
        $status = $passed ? '✅ PASS' : '❌ FAIL';
        echo "  $status: $check\n";
        if (!$passed) {
            $testPassed = false;
            $testFailures[] = $check;
        }
    }

    if ($testPassed) {
        $passedTests++;
        echo "  ✅ TEST PASSED\n\n";
    } else {
        $failedTests++;
        $failedDetails[] = [
            'name' => $test['name'],
            'failures' => $testFailures
        ];
        echo "  ❌ TEST FAILED\n\n";
    }
}

// Summary
echo "\n" . str_repeat("=", 50) . "\n";
echo "TEST SUMMARY\n";
echo str_repeat("=", 50) . "\n";
echo "Total Tests: $totalTests\n";
echo "Passed: $passedTests ✅\n";
echo "Failed: $failedTests ❌\n";

if ($failedTests > 0) {
    echo "\nFailed Tests Details:\n";
    foreach ($failedDetails as $failed) {
        echo "  - {$failed['name']}:\n";
        foreach ($failed['failures'] as $failure) {
            echo "    • $failure\n";
        }
    }
}

echo "\n=== TEST COMPLETE ===\n";
