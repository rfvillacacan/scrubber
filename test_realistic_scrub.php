<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/DataGenerator.php';
require_once __DIR__ . '/lib/ScrubberEngine.php';
require_once __DIR__ . '/lib/RulesRegistry.php';
require_once __DIR__ . '/lib/Storage.php';
require_once __DIR__ . '/lib/Logger.php';
require_once __DIR__ . '/lib/Validator.php';
require_once __DIR__ . '/lib/Crypto.php';

echo "=== Realistic Scrubber Test ===\n\n";

// Test DataGenerator methods directly
echo "1. Testing DataGenerator methods:\n";
echo "-----------------------------------\n";
echo "Email: " . DataGenerator::generateEmail() . "\n";
echo "Password: " . DataGenerator::generatePassword() . "\n";
echo "Phone: " . DataGenerator::generatePhoneNumber() . "\n";
echo "IPv4: " . DataGenerator::generateIPv4() . "\n";
echo "CIDR: " . DataGenerator::generateCIDR() . "\n";
echo "UUID: " . DataGenerator::generateUUID() . "\n";
echo "Credit Card: " . DataGenerator::generateCreditCard() . "\n";
echo "CVV: " . DataGenerator::generateCVV() . "\n";
echo "Bearer Token: " . DataGenerator::generateBearerToken() . "\n";
echo "JWT: " . DataGenerator::generateJWT() . "\n";
echo "Database Name: " . DataGenerator::generateDatabaseName() . "\n";
echo "Username: " . DataGenerator::generateUsername() . "\n";
echo "Hostname: " . DataGenerator::generateHostname() . "\n";
echo "Amount: " . DataGenerator::generateAmount() . "\n";
echo "Version: " . DataGenerator::generateVersion() . "\n";
echo "Region: " . DataGenerator::generateRegion() . "\n";
echo "Port: " . DataGenerator::generatePort() . "\n";
echo "Person Name: " . DataGenerator::generatePersonName() . "\n";
echo "Account ID: " . DataGenerator::generateAccountId() . "\n";
echo "Customer ID: " . DataGenerator::generateCustomerId() . "\n";
echo "\n";

// Test realistic scrubbing scenario
echo "2. Testing Realistic Scrubbing:\n";
echo "-----------------------------------\n";

$testData = "SMTP_PASS=EmailP@ssw0rd!";
echo "Original: $testData\n";
echo "Realistic: SMTP_PASS=" . DataGenerator::generatePassword() . "\n";
echo "\n";

$testEmail = "User: john.doe@example.com";
echo "Original: $testEmail\n";
echo "Realistic: User: " . DataGenerator::generateEmail() . "\n";
echo "\n";

$testIP = "Server IP: 192.168.1.100";
echo "Original: $testIP\n";
echo "Realistic: Server IP: " . DataGenerator::generateIPv4() . "\n";
echo "\n";

$testCard = "Card Number: 4111 1111 1111 1111";
echo "Original: $testCard\n";
echo "Realistic: Card Number: " . DataGenerator::generateCreditCard() . "\n";
echo "\n";

echo "=== Test Complete ===\n";
