<?php
// В начале каждого public/*.php
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');
error_reporting(E_ALL);

// Подключаем автозагрузчик Composer из родительской папки
require __DIR__ . '/../vendor/autoload.php';

use App\Bootstrap;
use App\Client\BitrixClient;
use App\DTO\OrderData;
use App\Storage\TempStorage;
use App\Service\QueueService;
use App\Service\InvoiceGenerator;

Bootstrap::init();
$logger = Bootstrap::getLogger();
header('Content-Type: application/json; charset=utf-8');

try {
    // Получение данных
    $input = file_get_contents('php://input');
    $data = $_POST ?: json_decode($input, true);
    
    $logger->info('Получен webhook от Tilda', ['data' => $data]);

    // Создание DTO и валидация
    $order = OrderData::fromArray($data ?? []);
    $errors = $order->validate();
    
    if ($errors) {
        $logger->warning('Ошибки валидации', ['errors' => $errors]);
        http_response_code(400);
        echo json_encode(['status' => 'error', 'errors' => $errors], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Локальный режим: без Bitrix/Redis, синхронная генерация PDF
    if (($_ENV['APP_ENV'] ?? '') === 'local') {
        $generator = new InvoiceGenerator();
        $fileName = $generator->generate($order);
        $url = "/invoices/{$fileName}";
        $logger->info('Локальный режим: счёт сгенерирован', ['file' => $fileName]);
        echo json_encode(['status' => 'success', 'order_id' => $order->orderId, 'url' => $url], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Создание лида в Bitrix24
    $bitrixClient = new BitrixClient();
    $bitrixClient->createLead($order);

    // Сохранение во временное хранилище
    $token = TempStorage::generateToken($order->orderId);
    TempStorage::write($token, $order->toArray());

    // Добавление в очередь генерации счёта
    QueueService::push($order->orderId, $token);

    // URL ожидания
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    $waitUrl = "{$protocol}://{$_SERVER['HTTP_HOST']}/wait.php?t={$token}&order_id={$order->orderId}";

    $response = [
        'status' => 'success',
        'order_id' => $order->orderId,
        'wait_url' => $waitUrl
    ];

    $logger->info('Webhook обработан успешно', $response);
    echo json_encode($response, JSON_UNESCAPED_UNICODE);

} catch (\Exception $e) {
    $logger->error('Ошибка обработки webhook', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);

    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Internal error'], JSON_UNESCAPED_UNICODE);
}
