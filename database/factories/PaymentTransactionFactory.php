<?php

declare(strict_types=1);

namespace Prahsys\LaravelClerk\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Prahsys\LaravelClerk\Models\PaymentSession;
use Prahsys\LaravelClerk\Models\PaymentTransaction;

class PaymentTransactionFactory extends Factory
{
    protected $model = PaymentTransaction::class;

    public function definition(): array
    {
        return [
            'payment_session_id' => PaymentSession::factory(),
            'transaction_id' => 'TXN_' . $this->faker->unique()->numerify('############'),
            'type' => 'payment',
            'status' => $this->faker->randomElement(['pending', 'captured', 'authorized', 'failed']),
            'amount' => $this->faker->randomFloat(2, 1, 1000),
            'currency' => 'USD',
            'reference' => 'REF-' . $this->faker->numerify('#####'),
            'gateway_response' => [
                'response_code' => '00',
                'response_text' => 'Approved',
                'transaction_id' => 'GW_' . $this->faker->numerify('##########'),
            ],
            'card_data' => [
                'last4' => $this->faker->numerify('####'),
                'brand' => $this->faker->randomElement(['visa', 'mastercard', 'amex']),
                'exp_month' => $this->faker->numberBetween(1, 12),
                'exp_year' => $this->faker->numberBetween(2025, 2030),
            ],
            'customer_data' => [
                'name' => $this->faker->name(),
                'email' => $this->faker->safeEmail(),
            ],
            'processed_at' => $this->faker->dateTimeBetween('-1 week', 'now'),
        ];
    }

    public function capture(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'type' => 'capture',
                'status' => 'captured',
                'captured_at' => $this->faker->dateTimeBetween('-1 week', 'now'),
            ];
        });
    }

    public function refund(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'type' => 'refund',
                'status' => 'completed',
                'refunded_at' => $this->faker->dateTimeBetween('-1 week', 'now'),
            ];
        });
    }

    public function void(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'type' => 'void',
                'status' => 'completed',
                'voided_at' => $this->faker->dateTimeBetween('-1 week', 'now'),
            ];
        });
    }

    public function failed(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'failed',
                'gateway_response' => [
                    'response_code' => '05',
                    'response_text' => 'Do not honor',
                    'error_code' => 'CARD_DECLINED',
                ],
                'processed_at' => $this->faker->dateTimeBetween('-1 week', 'now'),
            ];
        });
    }

    public function successful(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => $this->faker->randomElement(['captured', 'authorized']),
                'gateway_response' => [
                    'response_code' => '00',
                    'response_text' => 'Approved',
                    'transaction_id' => 'GW_' . $this->faker->numerify('##########'),
                ],
                'processed_at' => $this->faker->dateTimeBetween('-1 week', 'now'),
                'captured_at' => $this->faker->dateTimeBetween('-1 week', 'now'),
            ];
        });
    }

    public function pending(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'pending',
                'processed_at' => null,
                'captured_at' => null,
            ];
        });
    }

    public function cardPresent(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'gateway_response' => array_merge($attributes['gateway_response'] ?? [], [
                    'terminal_id' => 'TERM_' . $this->faker->numerify('###'),
                    'entry_method' => 'chip_pin',
                    'receipt_number' => 'RCP' . $this->faker->numerify('######'),
                ]),
                'card_data' => array_merge($attributes['card_data'] ?? [], [
                    'entry_method' => 'chip_pin',
                ]),
            ];
        });
    }
}