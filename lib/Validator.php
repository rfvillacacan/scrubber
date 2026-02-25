<?php
declare(strict_types=1);

class Validator {

    public static function validate(string $type, string $value): bool {
        return match ($type) {
            'ipv4_strict' => filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false,
            'ipv6_strict' => filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false,
            'cidr_strict' => self::cidrStrict($value),
            'luhn' => self::luhnCheck($value),
            'iban' => self::ibanCheck($value),
            'routing_aba' => self::routingAbaCheck($value),
            'jwt_structure' => self::jwtStructure($value),
            'entropy_check' => self::entropyCheck($value),
            default => true
        };
    }

    private static function luhnCheck(string $number): bool {
        $number = preg_replace('/\D/', '', $number);
        if ($number === '') {
            return false;
        }

        $sum = 0;
        $alt = false;

        for ($i = strlen($number) - 1; $i >= 0; $i--) {
            $n = (int)$number[$i];
            if ($alt) {
                $n *= 2;
                if ($n > 9) {
                    $n -= 9;
                }
            }
            $sum += $n;
            $alt = !$alt;
        }

        return $sum % 10 === 0;
    }

    private static function jwtStructure(string $value): bool {
        if (!str_contains($value, '.')) {
            return false;
        }

        $parts = explode('.', $value);
        if (count($parts) !== 3) {
            return false;
        }

        foreach ($parts as $part) {
            if ($part === '' || !preg_match('/^[A-Za-z0-9_-]+$/', $part)) {
                return false;
            }
        }

        return true;
    }

    private static function entropyCheck(string $value): bool {
        $len = strlen($value);
        if ($len < 16) {
            return false;
        }

        $freq = [];
        for ($i = 0; $i < $len; $i++) {
            $char = $value[$i];
            $freq[$char] = ($freq[$char] ?? 0) + 1;
        }

        $entropy = 0.0;
        foreach ($freq as $count) {
            $p = $count / $len;
            $entropy -= $p * log($p, 2);
        }

        return $entropy >= 3.5;
    }

    private static function cidrStrict(string $value): bool {
        $parts = explode('/', $value);
        if (count($parts) !== 2) {
            return false;
        }

        [$ip, $prefix] = $parts;
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            return false;
        }

        if ($prefix === '' || !ctype_digit($prefix)) {
            return false;
        }

        $num = (int)$prefix;
        return $num >= 0 && $num <= 32;
    }

    private static function ibanCheck(string $value): bool {
        $iban = strtoupper(str_replace(' ', '', $value));
        if (strlen($iban) < 15 || strlen($iban) > 34) {
            return false;
        }

        if (!preg_match('/^[A-Z0-9]+$/', $iban)) {
            return false;
        }

        $rearranged = substr($iban, 4) . substr($iban, 0, 4);
        $expanded = '';
        $len = strlen($rearranged);
        for ($i = 0; $i < $len; $i++) {
            $ch = $rearranged[$i];
            if (ctype_alpha($ch)) {
                $expanded .= (string)(ord($ch) - 55);
            } else {
                $expanded .= $ch;
            }
        }

        $mod = 0;
        $expandedLen = strlen($expanded);
        for ($i = 0; $i < $expandedLen; $i++) {
            $digit = (int)$expanded[$i];
            $mod = ($mod * 10 + $digit) % 97;
        }

        return $mod === 1;
    }

    private static function routingAbaCheck(string $value): bool {
        if (!preg_match('/^\\d{9}$/', $value)) {
            return false;
        }
        $digits = array_map('intval', str_split($value));
        $checksum = (3 * ($digits[0] + $digits[3] + $digits[6]))
            + (7 * ($digits[1] + $digits[4] + $digits[7]))
            + ($digits[2] + $digits[5] + $digits[8]);

        return $checksum % 10 === 0;
    }
}
