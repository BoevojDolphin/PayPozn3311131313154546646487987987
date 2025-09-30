<?php

namespace App;

use Dotenv\Dotenv;
use Monolog\Logger;
use Monolog\Handler\RotatingFileHandler;
use Predis\Client as RedisClient;

class Bootstrap
{
    private static ?Logger $logger = null;
    private static ?RedisClient $redis = null;

    public static function init(): void
    {
        // Загрузка переменных окружения
        $dotenv = Dotenv::createImmutable(__DIR__ . '/../');
        $dotenv->load();

        // Настройка PHP
        ini_set('display_errors', $_ENV['APP_DEBUG'] === 'true' ? '1' : '0');
        ini_set('log_errors', '1');
        ini_set('error_log', $_ENV['LOG_PATH'] . '/php_errors.log');

        // Создание директорий
        self::ensureDirectories();
    }

    public static function getLogger(): Logger
    {
        if (!self::$logger) {
            self::$logger = new Logger('app');
            self::$logger->pushHandler(
                new RotatingFileHandler(
                    $_ENV['LOG_PATH'] . '/app.log',
                    7,
                    Logger::toMonologLevel($_ENV['LOG_LEVEL'])
                )
            );
        }
        return self::$logger;
    }

    public static function getRedis(): RedisClient
    {
        if (!self::$redis) {
            self::$redis = new RedisClient([
                'host' => $_ENV['REDIS_HOST'],
                'port' => $_ENV['REDIS_PORT'],
                'database' => $_ENV['REDIS_DATABASE']
            ]);
        }
        return self::$redis;
    }

    private static function ensureDirectories(): void
    {
        $dirs = [
            $_ENV['INVOICES_DIR'],
            $_ENV['TEMP_DIR'],
            $_ENV['LOG_PATH']
        ];

        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0750, true);
            }
        }
    }
}
