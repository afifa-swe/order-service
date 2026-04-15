<?php

namespace App\Services;

use App\DTO\OrderData;
use App\Enums\DeliveryType;
use App\Enums\PaymentType;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

class OrderService
{
    public function createOrder(array $data): Order
    {
        $dto = OrderData::fromArray($data);

        return DB::transaction(function () use ($dto) {
            $total = $this->calculateItemsTotal($dto->items);
            $deliveryCost = (float) ($dto->deliveryData['cost'] ?? 0);

            $order = Order::create([
                'user_id' => $dto->userId,
                'phone' => $dto->phone,
                'email' => $dto->email,
                'comment' => $dto->comment,
                'total' => $total + $deliveryCost,
                'status' => 'new',
            ]);

            $order->items()->createMany($dto->items);

            $this->saveDelivery($order, $dto);
            $this->savePayment($order, $dto);

            return $order->load(['items', 'delivery', 'payment']);
        });
    }

    protected function calculateItemsTotal(array $items): float
    {
        return array_reduce($items, function (float $sum, array $item) {
            return $sum + ((float) $item['price'] * (int) $item['quantity']);
        }, 0.0);
    }

    protected function saveDelivery(Order $order, OrderData $dto): Delivery
    {
        $payload = [
            'type' => $dto->deliveryType->value,
            'cost' => $dto->deliveryData['cost'] ?? 0,
        ];

        if ($dto->deliveryType === DeliveryType::Address) {
            $payload['city'] = $dto->deliveryData['city'];
            $payload['street'] = $dto->deliveryData['street'];
            $payload['house'] = $dto->deliveryData['house'];
            $payload['apartment'] = $dto->deliveryData['apartment'] ?? null;
        }

        if ($dto->deliveryType === DeliveryType::Pickup) {
            $payload['pickup_point_id'] = $dto->deliveryData['pickup_point_id'];
        }

        return $order->delivery()->create($payload);
    }

    protected function savePayment(Order $order, OrderData $dto): Payment
    {
        $payload = [
            'type' => $dto->paymentType->value,
            'status' => 'pending',
            'amount' => $order->total,
        ];

        if ($dto->paymentType === PaymentType::Card) {
            $payload['card_last4'] = $dto->paymentData['card_last4'];
        }

        if ($dto->paymentType === PaymentType::Credit) {
            $months = (int) $dto->paymentData['credit_months'];
            $payload['credit_provider'] = $dto->paymentData['credit_provider'];
            $payload['credit_months'] = $months;
            $payload['monthly_payment'] = round((float) $order->total / $months, 2);
        }

        return $order->payment()->create($payload);
    }
}
