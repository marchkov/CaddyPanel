<?php

namespace CaddyPanel\Core;

class IpAccess
{
    public static function isAllowed(string $ipAddress, string $allowlist): bool
    {
        $rules = array_filter(array_map('trim', preg_split('/[\s,]+/', $allowlist) ?: []));

        if ($rules === []) {
            return true;
        }

        foreach ($rules as $rule) {
            if (self::matches($ipAddress, $rule)) {
                return true;
            }
        }

        return false;
    }

    public static function isLocal(string $ipAddress): bool
    {
        return in_array($ipAddress, ['127.0.0.1', '::1'], true);
    }

    public static function validateAllowlist(string $allowlist): bool
    {
        $rules = array_filter(array_map('trim', preg_split('/[\s,]+/', $allowlist) ?: []));

        foreach ($rules as $rule) {
            if (!self::isValidRule($rule)) {
                return false;
            }
        }

        return true;
    }

    private static function matches(string $ipAddress, string $rule): bool
    {
        if (!str_contains($rule, '/')) {
            return hash_equals($rule, $ipAddress);
        }

        [$network, $bits] = explode('/', $rule, 2);

        if (!ctype_digit($bits)) {
            return false;
        }

        $ipBinary = inet_pton($ipAddress);
        $networkBinary = inet_pton($network);

        if ($ipBinary === false || $networkBinary === false || strlen($ipBinary) !== strlen($networkBinary)) {
            return false;
        }

        $prefixBits = (int) $bits;
        $maxBits = strlen($ipBinary) * 8;

        if ($prefixBits < 0 || $prefixBits > $maxBits) {
            return false;
        }

        $fullBytes = intdiv($prefixBits, 8);
        $remainingBits = $prefixBits % 8;

        if ($fullBytes > 0 && substr($ipBinary, 0, $fullBytes) !== substr($networkBinary, 0, $fullBytes)) {
            return false;
        }

        if ($remainingBits === 0) {
            return true;
        }

        $mask = 0xFF << (8 - $remainingBits) & 0xFF;

        return (ord($ipBinary[$fullBytes]) & $mask) === (ord($networkBinary[$fullBytes]) & $mask);
    }

    private static function isValidRule(string $rule): bool
    {
        if (!str_contains($rule, '/')) {
            return filter_var($rule, FILTER_VALIDATE_IP) !== false;
        }

        [$network, $bits] = explode('/', $rule, 2);

        if (filter_var($network, FILTER_VALIDATE_IP) === false || !ctype_digit($bits)) {
            return false;
        }

        $maxBits = str_contains($network, ':') ? 128 : 32;
        $prefixBits = (int) $bits;

        return $prefixBits >= 0 && $prefixBits <= $maxBits;
    }
}
