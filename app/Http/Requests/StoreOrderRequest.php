<?php

namespace App\Http\Requests;

use App\Enums\DeliveryType;
use App\Enums\PaymentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return array_merge(
            $this->orderRules(),
            $this->deliveryRules(),
            $this->paymentRules(),
        );
    }

    protected function orderRules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'phone' => ['required', 'string', 'max:20'],
            'email' => ['required', 'email', 'max:255'],
            'comment' => ['nullable', 'string', 'max:1000'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.price' => ['required', 'numeric', 'min:0'],
        ];
    }

    protected function deliveryRules(): array
    {
        $isAddress = $this->input('delivery.type') === DeliveryType::Address->value;
        $isPickup = $this->input('delivery.type') === DeliveryType::Pickup->value;

        return [
            'delivery' => ['required', 'array'],
            'delivery.type' => ['required', Rule::enum(DeliveryType::class)],
            'delivery.cost' => ['nullable', 'numeric', 'min:0'],

            'delivery.pickup_point_id' => [
                Rule::requiredIf($isPickup),
                'nullable', 'integer',
            ],

            'delivery.city' => [Rule::requiredIf($isAddress), 'nullable', 'string', 'max:100'],
            'delivery.street' => [Rule::requiredIf($isAddress), 'nullable', 'string', 'max:150'],
            'delivery.house' => [Rule::requiredIf($isAddress), 'nullable', 'string', 'max:20'],
            'delivery.apartment' => ['nullable', 'string', 'max:20'],
        ];
    }

    protected function paymentRules(): array
    {
        $isCard = $this->input('payment.type') === PaymentType::Card->value;
        $isCredit = $this->input('payment.type') === PaymentType::Credit->value;

        return [
            'payment' => ['required', 'array'],
            'payment.type' => ['required', Rule::enum(PaymentType::class)],

            'payment.card_last4' => [Rule::requiredIf($isCard), 'nullable', 'digits:4'],

            'payment.credit_provider' => [Rule::requiredIf($isCredit), 'nullable', 'string', 'max:100'],
            'payment.credit_months' => [Rule::requiredIf($isCredit), 'nullable', 'integer', 'min:3', 'max:36'],
        ];
    }
}
