<?php

namespace Atlas\Support;

class Logger
{
    public static function info(string $message, array $context = []): void
    {
        self::log('INFO', $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::log('ERROR', $message, $context);
    }

    private static function log(string $level, string $message, array $context = []): void
    {
        $formatted = sprintf('[%s] [%s] %s %s', date('Y-m-d H:i:s'), $level, $message, json_encode($context));
        error_log($formatted); // Adaptador por defecto del sistema
    }
}