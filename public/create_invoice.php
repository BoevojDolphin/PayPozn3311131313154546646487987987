<?php
/**
 * create_invoice.php
 * Приём webhook от Tilda, создание лида в Bitrix24 и запуск генерации PDF.
 */
declare(strict_types=1); 
 
// В начале каждого public/*.php
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');
error_reporting(E_ALL);

// Подключаем автозагрузчик Composer из родительской папки
require __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use Monolog\Logger;
use Monolog\Handler\RotatingFileHandler;

// Загрузка конфигурации
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Логи
$logger = new Logger('create_invoice');
$logger->pushHandler(new RotatingFileHandler(__DIR__ . '/logs/create_invoice.log', 7));

// Читаем JSON payload
$input = file_get_contents('php://input');
$data  = json_decode($input, true);

// Логи входящих данных
$logger->info('Webhook payload', $data);

// Обязательные поля
$required = ['company_inn','company_name','client_email','client_name','client_phone','order_id','amount'];
foreach ($required as $field) {
    if (empty($data[$field])) {
        $logger->warning("Missing field: $field");
        http_response_code(400);
        echo json_encode(['status'=>'error','message'=>"Missing $field"]);
        exit;
    }
}

// Подготовка данных для лида
$leadFields = [
    'TITLE'        => "Заказ №{$data['order_id']}",
    'NAME'         => $data['client_name'],
    'EMAIL'        => [['VALUE'=>$data['client_email'],'VALUE_TYPE'=>'WORK']],
    'PHONE'        => [['VALUE'=>$data['client_phone'],'VALUE_TYPE'=>'WORK']],
    'OPPORTUNITY'  => (float)$data['amount'],
    'CURRENCY_ID'  => 'RUB',
    'COMMENTS'     => $data['company_name'] . ' (' . $data['company_inn'] . ')',
];

// Bitrix webhook URL
$webhookUrl = rtrim($_ENV['BITRIX_WEBHOOK_URL'], '/') . '/crm.lead.add.json';

// Отправка в Bitrix24
$ch = curl_init($webhookUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => http_build_query(['fields'=>$leadFields]),
    CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
    CURLOPT_CAINFO         => $_ENV['SSL_CACERT_PATH'],
]);
$response = curl_exec($ch);
$code     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error    = curl_error($ch);
curl_close($ch);

$logger->info('Bitrix response', ['code'=>$code,'body'=>$response,'error'=>$error]);
if ($error || $code >= 300) {
    http_response_code(502);
    echo json_encode(['status'=>'error','message'=>'Bitrix API error']);
    exit;
}
$result = json_decode($response, true);
if (empty($result['result'])) {
    http_response_code(502);
    echo json_encode(['status'=>'error','message'=>'Empty Bitrix result']);
    exit;
}

// Генерация токена и сохранение payload
$token = bin2hex(random_bytes(16));
file_put_contents(__DIR__ . "/invoices/tmp/{$token}.json", json_encode($data, JSON_UNESCAPED_UNICODE));

// Запуск генерации PDF
exec("php " . escapeshellarg(__DIR__ . "/private_invoice.php") . " {$token} > /dev/null 2>&1 &");

// URL ожидания
$protocol = (!empty($_SERVER['HTTPS'])?'https':'http');
$waitUrl  = "{$protocol}://{$_SERVER['HTTP_HOST']}/wait.php?t={$token}&order_id={$data['order_id']}";

// Ответ Tilda
header('Content-Type: application/json');
echo json_encode(['status'=>'success','wait_url'=>$waitUrl]);
exit;
