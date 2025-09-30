<?php
// В начале каждого public/*.php
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');
error_reporting(E_ALL);

require __DIR__ . '/../vendor/autoload.php';

use App\Bootstrap;
use App\Storage\TempStorage;

Bootstrap::init();
header('Content-Type: application/json; charset=utf-8');

$token = isset($_GET['t']) ? preg_replace('/[^a-f0-9]/', '', $_GET['t']) : '';
if (!$token) {
    echo json_encode(['status' => 'error', 'message' => 'missing token'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Локальный режим: нет очереди, ожидаем наличия PDF-файла по имени из temp
if (($_ENV['APP_ENV'] ?? '') === 'local') {
    $data = TempStorage::read($token);
    if (!$data) {
        echo json_encode(['status' => 'error', 'message' => 'not found'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $orderId = $data['order_id'] ?? '';
    $pattern = sprintf('%s/invoice_%s.pdf', rtrim($_ENV['INVOICES_DIR'], '/\\'), $orderId);
    if (is_file($pattern)) {
        $rel = '/invoices/' . basename($pattern);
        echo json_encode(['status' => 'ready', 'url' => $rel], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode(['status' => 'processing'], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// Продакшн: можно проверять статус через Redis/TempStorage (по ключу состояния)
$data = TempStorage::read($token);
if (!$data) {
    echo json_encode(['status' => 'error', 'message' => 'not found'], JSON_UNESCAPED_UNICODE);
    exit;
}

$orderId = $data['order_id'] ?? '';
$path = rtrim($_ENV['INVOICES_DIR'], '/\\') . '/invoice_' . $orderId . '.pdf';
if (is_file($path)) {
    echo json_encode(['status' => 'ready', 'url' => '/invoices/' . basename($path)], JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode(['status' => 'processing'], JSON_UNESCAPED_UNICODE);
}