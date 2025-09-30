<?php

namespace App\DTO;

class OrderData
{
    public function __construct(
        public readonly string $orderId,
        public readonly string $name,
        public readonly string $email,
        public readonly string $phone,
        public readonly float $amount,
        public readonly string $productName,
        public readonly string $organization = '',
        public readonly string $inn = ''
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            orderId: (string)($data['order_id'] ?? ''),
            name: trim((string)($data['name'] ?? '')),
            email: filter_var($data['email'] ?? '', FILTER_VALIDATE_EMAIL) ?: '',
            phone: preg_replace('/[^\d+\-\(\)\s]/', '', $data['phone'] ?? ''),
            amount: (float)($data['amount'] ?? 0),
            productName: trim((string)($data['product_name'] ?? '')),
            organization: trim((string)($data['organization'] ?? '')),
            inn: preg_replace('/[^\d\-]/', '', $data['inn'] ?? '')
        );
    }

    public function validate(): array
    {
        $errors = [];

        if (empty($this->orderId)) $errors[] = 'order_id обязателен';
        if (empty($this->name)) $errors[] = 'name обязательно';
        if ($this->amount <= 0) $errors[] = 'amount должен быть больше 0';
        if (!empty($this->email) && !filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'email имеет неверный формат';
        }

        return $errors;
    }

    public function toArray(): array
    {
        return [
            'order_id' => $this->orderId,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'amount' => $this->amount,
            'product_name' => $this->productName,
            'organization' => $this->organization,
            'inn' => $this->inn
        ];
    }
}
