<?php

namespace CaddyPanel\Support;

class Validator
{
    public static function domain(string $domain): bool
    {
        $domain = strtolower(trim($domain));

        if ($domain === '' || strlen($domain) > 253) {
            return false;
        }

        return preg_match('/^(?=.{1,253}$)([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/', $domain) === 1;
    }

    public static function siteType(string $type): bool
    {
        return in_array($type, ['static', 'php'], true);
    }

    public static function siteStatus(string $status): bool
    {
        return in_array($status, ['draft', 'active', 'disabled', 'deleted', 'error'], true);
    }
}
