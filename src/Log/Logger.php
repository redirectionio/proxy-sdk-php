<?php

namespace RedirectionIO\Client\Log;

/**
 * Logger 
 * 
 * Minimal working logger
 */
class Logger {

    public function log($level, $message = '', $context = [])
    {
        // todo
    }

    public function debug($message, $context)
    {
        self::log('debug', $message, $context);
    }

    public function warning($message, $context)
    {
        self::log('warning', $message, $context);
    }

    public function error($message, $context)
    {
        self::log('error', $message, $context);
    }
}
