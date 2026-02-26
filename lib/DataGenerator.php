<?php
declare(strict_types=1);

class DataGenerator {

    public static function generateEmail(): string {
        $domains = ['example.com', 'test.local', 'demo.org', 'sample.net'];
        $usernames = ['user', 'admin', 'notify', 'service', 'account'];
        return $usernames[array_rand($usernames)] . '_' .
               substr(md5((string)rand()), 0, 8) . '@' .
               $domains[array_rand($domains)];
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

    public static function generateString(int $length = 10): string {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $result = '';
        for ($i = 0; $i < $length; $i++) {
            $result .= $chars[mt_rand(0, strlen($chars) - 1)];
        }
        return $result;
    }
}
