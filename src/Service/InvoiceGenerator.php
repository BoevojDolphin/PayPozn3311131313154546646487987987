<?php

namespace App\Service;

use App\Bootstrap;
use App\DTO\OrderData;
use FPDF;

class InvoiceGenerator
{
    public function generate(OrderData $order): string
    {
        $logger = Bootstrap::getLogger();
        $logger->info('Начало генерации счёта', ['order_id' => $order->orderId]);

        $pdf = new FPDF('P', 'mm', 'A4');
        $pdf->AddPage();
        $pdf->SetFont('Arial', '', 12);

        // Заголовок
        $pdf->SetFont('Arial', 'B', 16);
        $pdf->Cell(0, 10, "Счёт № {$order->orderId}", 0, 1, 'C');
        $pdf->Ln(10);

        // Информация о клиенте
        $pdf->SetFont('Arial', '', 12);
        $pdf->Cell(50, 7, 'Клиент:', 0, 0);
        $pdf->Cell(0, 7, $order->name, 0, 1);

        if ($order->email) {
            $pdf->Cell(50, 7, 'Email:', 0, 0);
            $pdf->Cell(0, 7, $order->email, 0, 1);
        }

        if ($order->phone) {
            $pdf->Cell(50, 7, 'Телефон:', 0, 0);
            $pdf->Cell(0, 7, $order->phone, 0, 1);
        }

        if ($order->organization) {
            $pdf->Cell(50, 7, 'Организация:', 0, 0);
            $pdf->Cell(0, 7, $order->organization, 0, 1);
        }

        $pdf->Ln(10);

        // Таблица
        $pdf->SetFillColor(240, 240, 240);
        $pdf->Cell(10, 7, '#', 1, 0, 'C', true);
        $pdf->Cell(130, 7, 'Наименование', 1, 0, 'C', true);
        $pdf->Cell(30, 7, 'Сумма, RUB', 1, 1, 'C', true);

        $pdf->Cell(10, 7, '1', 1, 0, 'C');
        $pdf->Cell(130, 7, $order->productName, 1, 0);
        $pdf->Cell(30, 7, number_format($order->amount, 2), 1, 1, 'R');

        // Итого
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(140, 7, 'Итого:', 1, 0, 'R', true);
        $pdf->Cell(30, 7, number_format($order->amount, 2), 1, 1, 'R', true);

        // Сохранение
        $fileName = "invoice_{$order->orderId}.pdf";
        $filePath = $_ENV['INVOICES_DIR'] . "/{$fileName}";
        $pdf->Output('F', $filePath);

        $logger->info('Счёт создан', [
            'order_id' => $order->orderId,
            'file' => $fileName
        ]);

        return $fileName;
    }
}
