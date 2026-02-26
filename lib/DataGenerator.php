<?php
declare(strict_types=1);

class DataGenerator {

    // Static cache for consistent domain mapping
    private static array $domainMap = [];
    private static array $fakeDomains = [
        'example.com', 'test.local', 'demo.org', 'sample.net',
        'mockdomain.io', 'fakedata.test', 'placeholder.co', 'testsite.org'
    ];

    public static function generateEmail(?string $originalEmail = null): string {
        $usernames = ['user', 'admin', 'notify', 'service', 'account'];

        // If original email provided, extract its domain and get consistent fake domain
        if ($originalEmail !== null && str_contains($originalEmail, '@')) {
            $fakeDomain = self::getFakeDomain($originalEmail);
        } else {
            // No original email, pick random fake domain
            $fakeDomain = self::$fakeDomains[array_rand(self::$fakeDomains)];
        }

        return $usernames[array_rand($usernames)] . '_' .
               substr(md5((string)rand()), 0, 8) . '@' .
               $fakeDomain;
    }

    /**
     * Get a consistent fake domain for an original domain
     * Same original domain always maps to same fake domain
     * Extracts base domain (second-level + TLD) for consistent mapping
     */
    public static function getFakeDomain(string $originalDomain): string {
        // Extract domain from email if full email provided
        if (str_contains($originalDomain, '@')) {
            $originalDomain = substr(strrchr($originalDomain, '@'), 1);
        }

        // Extract base domain (second-level domain + TLD)
        // e.g., app.corp.internal -> corp.internal
        // e.g., api.service.example.com -> example.com
        $parts = explode('.', $originalDomain);
        if (count($parts) >= 2) {
            // Get last 2 parts for base domain
            $baseDomain = $parts[count($parts) - 2] . '.' . $parts[count($parts) - 1];

            // Special handling for common multi-part TLDs (co.uk, com.au, etc.)
            if (count($parts) >= 3 && in_array($parts[count($parts) - 2], ['co', 'com', 'org', 'net', 'gov', 'edu', 'ac'])) {
                $baseDomain = $parts[count($parts) - 3] . '.' . $baseDomain;
            }
        } else {
            $baseDomain = $originalDomain;
        }

        // Return cached mapping if available
        if (isset(self::$domainMap[$baseDomain])) {
            return self::$domainMap[$baseDomain];
        }

        // Create consistent mapping based on hash
        $hash = substr(md5($baseDomain), 0, 8);
        $index = hexdec(substr($hash, 0, 2)) % count(self::$fakeDomains);
        $fakeDomain = self::$fakeDomains[$index];

        // Cache the mapping
        self::$domainMap[$baseDomain] = $fakeDomain;
        return $fakeDomain;
    }

    /**
     * Generate a fake URL with consistent domain mapping
     * Preserves protocol, port, path structure
     */
    public static function generateUrl(string $originalUrl): string {
        // Extract host from URL
        if (!preg_match('~^([a-z][a-z0-9+.-]*):\\/\\/([^\\/:]+)~i', $originalUrl, $matches)) {
            // Not a URL with protocol - might be hostname:port format
            if (preg_match('~^([^\\/:]+):(\\d+)$~', $originalUrl, $hostMatches)) {
                // hostname:port format (e.g., postgres-db.company.com:5432)
                [$full, $host, $port] = $hostMatches;
                $fakeDomain = self::getFakeDomain($host);
                return $fakeDomain . ':' . $port;
            }
            return self::generateSmartString($originalUrl);
        }

        $host = $matches[2];

        // Get consistent fake domain for this host
        $fakeDomain = self::getFakeDomain($host);

        // Replace host in original URL with fake domain
        $fakeUrl = preg_replace(
            '~^([a-z][a-z0-9+.-]*):\\/\\/[^\\/:]+~i',
            '$1://' . $fakeDomain,
            $originalUrl
        );

        return $fakeUrl;
    }

    public static function generatePassword(int $length = 12): string {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789@#$%!';
        $password = '';
        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[mt_rand(0, strlen($chars) - 1)];
        }
        return $password;
    }

    public static function generatePhoneNumber(): string {
        $formats = [
            '+1-555-%03d-%04d',
            '+44-20-%04d-%04d',
            '+1 (%03d) %03d-%04d'
        ];
        $format = $formats[array_rand($formats)];
        return sprintf($format, rand(100, 999), rand(1000, 9999), rand(100, 9999));
    }

    public static function generateIPv4(): string {
        return sprintf('%d.%d.%d.%d', rand(1, 255), rand(0, 255), rand(0, 255), rand(1, 254));
    }

    public static function generateCIDR(): string {
        return self::generateIPv4() . '/' . rand(16, 32);
    }

    public static function generateUUID(): string {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            rand(0, 65535), rand(0, 65535), rand(0, 65535), rand(0, 65535),
            rand(0, 65535), rand(0, 65535), rand(0, 65535), rand(0, 65535));
    }

    public static function generateCreditCard(): string {
        $prefixes = ['4', '5', '3', '6'];
        $card = $prefixes[array_rand($prefixes)];
        for ($i = 0; $i < 15; $i++) {
            $card .= rand(0, 9);
        }
        return chunk_split($card, 4, ' ');
    }

    public static function generateCVV(): string {
        return sprintf('%03d', rand(100, 999));
    }

    public static function generateBearerToken(): string {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789._-';
        $token = '';
        for ($i = 0; $i < 40; $i++) {
            $token .= $chars[mt_rand(0, strlen($chars) - 1)];
        }
        return 'Bearer ' . $token;
    }

    public static function generateJWT(): string {
        $header = base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload = base64_encode(json_encode(['sub' => self::generateEmail(), 'exp' => time() + 3600]));
        $signature = substr(md5((string)rand()), 0, 43);
        return $header . '.' . $payload . '.' . $signature;
    }

    public static function generateDatabaseName(): string {
        $names = ['myapp_db', 'production_db', 'staging_db', 'test_db', 'dev_db'];
        return $names[array_rand($names)];
    }

    public static function generateUsername(): string {
        $names = ['dbuser', 'admin', 'app_user', 'service_user'];
        return $names[array_rand($names)] . '_' . rand(100, 999);
    }

    public static function generateHostname(): string {
        $names = ['web-server', 'api-gateway', 'db-master', 'cache-node', 'worker'];
        return $names[array_rand($names)] . rand(1, 10) . '.internal';
    }

    public static function generateAmount(): string {
        $amounts = [99.99, 149.99, 249.99, 499.99, 999.99];
        return $amounts[array_rand($amounts)] . ' USD';
    }

    public static function generateVersion(): string {
        return sprintf('%d.%d.%d', rand(1, 5), rand(0, 10), rand(0, 20));
    }

    public static function generateRegion(): string {
        $regions = ['us-east-1', 'us-west-2', 'eu-west-1', 'ap-southeast-1', 'ap-northeast-1'];
        return $regions[array_rand($regions)];
    }

    public static function generatePort(): string {
        $ports = [3306, 5432, 6379, 9200, 5672, 27017, 8080, 443, 25, 587];
        return (string)$ports[array_rand($ports)];
    }

    public static function generatePersonName(): string {
        $firstNames = ['John', 'Jane', 'Bob', 'Alice', 'Charlie', 'Diana', 'Edward', 'Fiona'];
        $lastNames = ['Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis'];
        return $firstNames[array_rand($firstNames)] . ' ' .
               substr($lastNames[array_rand($lastNames)], 0, 1) . '. ' .
               $lastNames[array_rand($lastNames)];
    }

    public static function generateAccountId(): string {
        return 'ACC-' . sprintf('%06d', rand(100000, 999999));
    }

    public static function generateCustomerId(): string {
        return 'CUST-' . substr(md5((string)mt_rand()), 0, 6);
    }

    public static function generateString(int|string $lengthOrOriginal = 10): string {
        // If original value is passed (string), use smart generation
        if (is_string($lengthOrOriginal)) {
            return self::generateSmartString($lengthOrOriginal);
        }

        // Legacy behavior: generate random string of specified length
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $result = '';
        for ($i = 0; $i < $lengthOrOriginal; $i++) {
            $result .= $chars[mt_rand(0, strlen($chars) - 1)];
        }
        return $result;
    }

    private static function analyzeValueFormat(string $value): array {
        $format = [
            'length' => strlen($value),
            'has_upper' => false,
            'has_lower' => false,
            'has_digits' => false,
            'special_chars' => '',
            'special_positions' => [],
            'entropy' => 0.0,
            'is_high_entropy' => false,
            'is_secret_like' => false
        ];

        for ($i = 0; $i < strlen($value); $i++) {
            $char = $value[$i];
            if (ctype_upper($char)) {
                $format['has_upper'] = true;
            } elseif (ctype_lower($char)) {
                $format['has_lower'] = true;
            } elseif (ctype_digit($char)) {
                $format['has_digits'] = true;
            } else {
                $format['special_chars'] .= $char;
                $format['special_positions'][] = ['char' => $char, 'pos' => $i];
            }
        }

        // Calculate Shannon entropy
        $format['entropy'] = self::calculateEntropy($value);

        // Detect if value looks like a secret/token/key
        $format['is_high_entropy'] = $format['entropy'] > 4.5;
        $format['is_secret_like'] = self::isSecretLike($value, $format);

        return $format;
    }

    private static function calculateEntropy(string $value): float {
        $freq = [];
        $len = strlen($value);
        for ($i = 0; $i < $len; $i++) {
            $char = $value[$i];
            $freq[$char] = ($freq[$char] ?? 0) + 1;
        }

        $entropy = 0.0;
        foreach ($freq as $count) {
            $p = $count / $len;
            $entropy -= $p * log($p, 2);
        }
        return $entropy;
    }

    private static function isSecretLike(string $value, array $format): bool {
        // Long strings with high entropy are likely secrets
        if ($format['length'] > 20 && $format['is_high_entropy']) {
            return true;
        }

        // Mixed case + numbers + special chars = likely secret
        if ($format['has_upper'] && $format['has_lower'] &&
            $format['has_digits'] && strlen($format['special_chars']) > 0) {
            return true;
        }

        // Common secret prefixes
        $secretPrefixes = ['AKIA', 'sk_', 'pk_', 'Bearer', 'Basic', 'eyJ', 'ghp_', 'gho_', 'ghu_'];
        foreach ($secretPrefixes as $prefix) {
            if (str_starts_with($value, $prefix)) {
                return true;
            }
        }

        return false;
    }

    public static function generateSmartString(string $originalValue): string {
        $format = self::analyzeValueFormat($originalValue);
        $result = '';

        for ($i = 0; $i < $format['length']; $i++) {
            $char = $originalValue[$i];

            if (ctype_digit($char)) {
                $result .= (string)rand(0, 9);
            } elseif (ctype_upper($char)) {
                $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
                $result .= $chars[mt_rand(0, strlen($chars) - 1)];
            } elseif (ctype_lower($char)) {
                $chars = 'abcdefghijklmnopqrstuvwxyz';
                $result .= $chars[mt_rand(0, strlen($chars) - 1)];
            } else {
                $result .= $char;
            }
        }

        return $result;
    }

    public static function generateIban(string $originalIban = ''): string {
        $length = $originalIban !== '' ? strlen(preg_replace('/\s+/', '', $originalIban)) : rand(15, 34);
        $countryCodes = ['GB', 'DE', 'FR', 'IT', 'ES', 'NL', 'BE', 'AT', 'PT', 'IE'];
        $cc = $countryCodes[array_rand($countryCodes)];
        $checkDigits = sprintf('%02d', rand(0, 99));
        $remainingLength = $length - 4;
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $bankInfo = '';
        for ($i = 0; $i < $remainingLength; $i++) {
            $bankInfo .= $chars[mt_rand(0, strlen($chars) - 1)];
        }
        return $cc . $checkDigits . $bankInfo;
    }

    public static function generateS3Bucket(string $originalS3): string {
        if (!str_starts_with(strtolower($originalS3), 's3://')) {
            return self::generateSmartString($originalS3);
        }

        $bucketPart = substr($originalS3, 5);
        $fakeBucket = self::generateSmartString($bucketPart);
        return 's3://' . $fakeBucket;
    }

    public static function generateDockerRegistry(string $originalDocker): string {
        // Updated pattern to match ANY domain, not just internal ones
        if (!preg_match('/^([a-z0-9.-]+\\.[a-z]{2,})(?::(\\d{2,5}))\\/([A-Za-z0-9._-]+)(?::([A-Za-z0-9._-]+))?$/i', $originalDocker, $matches)) {
            return self::generateSmartString($originalDocker);
        }

        $domain = $matches[1];
        $port = $matches[2] ?? '';
        $service = $matches[3];
        $tag = $matches[4] ?? '';

        // Use consistent domain mapping for all domains
        $fakeDomain = self::getFakeDomain($domain);

        // Analyze service name to determine if it's sensitive
        $serviceFormat = self::analyzeValueFormat($service);

        // Common service names that are NOT sensitive (technical infrastructure)
        $commonServices = [
            'redis', 'postgres', 'mysql', 'mongodb', 'nginx', 'apache',
            'vault', 'consul', 'grafana', 'prometheus', 'alertmanager',
            'elasticsearch', 'kibana', 'logstash', 'rabbitmq', 'kafka',
            'auth', 'api', 'web', 'app', 'frontend', 'backend',
            'cache', 'db', 'queue', 'worker', 'proxy', 'gateway'
        ];

        // Preserve common technical service names
        if (in_array(strtolower($service), $commonServices, true)) {
            $fakeService = $service;
        } elseif ($serviceFormat['is_secret_like'] || self::containsBusinessTerms($service)) {
            // Scrub business-sensitive or high-entropy service names
            $fakeService = self::generateSmartString($service);
        } else {
            // Low entropy generic name, preserve
            $fakeService = $service;
        }

        // Preserve version/tag unchanged - it's technical context, not sensitive
        $fakeTag = $tag !== '' ? ':' . $tag : '';

        return $fakeDomain . ($port !== '' ? ":{$port}/" : '/') . $fakeService . $fakeTag;
    }

    private static function containsBusinessTerms(string $value): bool {
        $businessTerms = [
            'payment', 'billing', 'transaction', 'invoice', 'receipt',
            'customer', 'financial', 'credit', 'debit', 'fraud',
            'insurance', 'claim', 'policy', 'premium', 'account',
            'proprietary', 'secret', 'confidential', 'internal', 'private'
        ];

        $lowerValue = strtolower($value);
        foreach ($businessTerms as $term) {
            if (str_contains($lowerValue, $term)) {
                return true;
            }
        }
        return false;
    }
}
