<?php

namespace App\Storage;

use App\Bootstrap;

class TempStorage
{
    private const TTL = 600; // 10 минут

    public static function generateToken(string $orderId): string
    {
        $secret = $_ENV['APP_ENV'] . date('Ymd');
        return hash_hmac('sha256', $orderId . '|' . time() . '|' . mt_rand(), $secret);
    }

    public static function write(string $token, array $data, int $ttl = self::TTL): void
    {
        $redis = Bootstrap::getRedis();
        $redis->setex("temp:$token", $ttl, json_encode($data, JSON_UNESCAPED_UNICODE));
    }

    public static function read(string $token): ?array
    {
        $redis = Bootstrap::getRedis();
        $data = $redis->get("temp:$token");
        
        return $data ? json_decode($data, true) : null;
    }

    public static function delete(string $token): void
    {
        $redis = Bootstrap::getRedis();
        $redis->del("temp:$token");
    }
}
