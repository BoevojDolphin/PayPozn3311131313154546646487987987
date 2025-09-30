#!/usr/bin/env php
<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Bootstrap;
use App\DTO\OrderData;
use App\Service\InvoiceGenerator;
use App\Service\QueueService;
use App\Storage\TempStorage;

Bootstrap::init();
$logger = Bootstrap::getLogger();

$logger->info('Запуск worker для генерации счетов');

while (true) {
    try {
        $job = QueueService::pop();
        
        if (!$job) {
            continue;
        }

        $logger->info('Обработка задачи', $job);

        // Получение данных заказа
        $data = TempStorage::read($job['token']);
        if (!$data) {
            $logger->warning('Данные заказа не найдены', $job);
            continue;
        }

        // Генерация счёта
        $order = OrderData::fromArray($data);
        $generator = new InvoiceGenerator();
        $fileName = $generator->generate($order);

        $logger->info('Счёт сгенерирован', [
            'order_id' => $order->orderId,
            'file' => $fileName
        ]);

    } catch (\Exception $e) {
        $logger->error('Ошибка в worker', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
    }
}
