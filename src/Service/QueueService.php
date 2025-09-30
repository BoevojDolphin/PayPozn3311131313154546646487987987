<?php

namespace App\Service;

use App\Bootstrap;

class QueueService
{
    private const QUEUE_NAME = 'invoice_generation';

    public static function push(string $orderId, string $token): void
    {
        $redis = Bootstrap::getRedis();
        $payload = json_encode(['order_id' => $orderId, 'token' => $token]);
        
        $redis->lpush(self::QUEUE_NAME, $payload);
        
        Bootstrap::getLogger()->info('Задача добавлена в очередь', [
            'order_id' => $orderId
        ]);
    }

    public static function pop(): ?array
    {
        $redis = Bootstrap::getRedis();
        $payload = $redis->brpop([self::QUEUE_NAME], 30);
        
        if (!$payload) {
            return null;
        }

        return json_decode($payload[1], true);
    }

    public static function size(): int
    {
        $redis = Bootstrap::getRedis();
        return $redis->llen(self::QUEUE_NAME);
    }
}
