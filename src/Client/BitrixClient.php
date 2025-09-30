<?php

namespace App\Client;

use App\Bootstrap;
use App\DTO\OrderData;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class BitrixClient
{
    private Client $client;

    public function __construct()
    {
        $this->client = new Client([
            'base_uri' => $_ENV['BITRIX_WEBHOOK_URL'],
            'verify' => $_ENV['SSL_CACERT_PATH'],
            'timeout' => 30,
            'headers' => [
                'User-Agent' => 'TildaWebhook/2.0'
            ]
        ]);
    }

    public function createLead(OrderData $order): array
    {
        $logger = Bootstrap::getLogger();
        
        $fields = [
            'TITLE' => "Заказ №{$order->orderId}",
            'NAME' => $order->name,
            'EMAIL' => [['VALUE' => $order->email, 'VALUE_TYPE' => 'WORK']],
            'PHONE' => [['VALUE' => $order->phone, 'VALUE_TYPE' => 'WORK']],
            'OPPORTUNITY' => $order->amount,
            'CURRENCY_ID' => 'RUB',
            'COMMENTS' => $order->productName,
        ];

        if ($order->organization) {
            $fields['COMPANY_TITLE'] = $order->organization;
        }

        try {
            $logger->info('Отправка лида в Bitrix24', ['order_id' => $order->orderId]);

            $response = $this->client->post($_ENV['BITRIX_WEBHOOK_URL'], [
                'form_params' => ['fields' => $fields]
            ]);

            $body = $response->getBody()->getContents();
            $result = json_decode($body, true);

            $logger->info('Ответ Bitrix24', [
                'status' => $response->getStatusCode(),
                'result' => $result
            ]);

            if (empty($result['result'])) {
                throw new \Exception('Пустой результат от Bitrix24');
            }

            return $result;

        } catch (GuzzleException $e) {
            $logger->error('Ошибка при отправке в Bitrix24', [
                'error' => $e->getMessage(),
                'order_id' => $order->orderId
            ]);
            throw $e;
        }
    }
}
