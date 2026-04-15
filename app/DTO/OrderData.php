<?php

namespace App\DTO;

use App\Enums\DeliveryType;
use App\Enums\PaymentType;

class OrderData
{
    public function __construct(
        public readonly int $userId,
        public readonly string $phone,
        public readonly string $email,
        public readonly ?string $comment,
        public readonly array $items,
        public readonly DeliveryType $deliveryType,
        public readonly array $deliveryData,
        public readonly PaymentType $paymentType,
        public readonly array $paymentData,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            userId: (int) $data['user_id'],
            phone: $data['phone'],
            email: $data['email'],
            comment: $data['comment'] ?? null,
            items: $data['items'],
            deliveryType: DeliveryType::from($data['delivery']['type']),
            deliveryData: $data['delivery'],
            paymentType: PaymentType::from($data['payment']['type']),
            paymentData: $data['payment'],
        );
    }
}
