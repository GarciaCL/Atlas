<?php

namespace Atlas\Configuration;

class Config
{
    /** @var array<string, mixed> */
    private static array $settings = [];

    /**
     * @param array<string, mixed> $settings
     */
    public static function load(array $settings): void
    {
        self::$settings = array_merge(self::$settings, $settings);
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::$settings[$key] ?? $default;
    }
}