<?php

declare(strict_types=1);

namespace Prahsys\LaravelClerk\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Prahsys\LaravelClerk\Models\PaymentSession;

class PaymentSessionFactory extends Factory
{
    protected $model = PaymentSession::class;

    public function definition(): array
    {
        return [
            'session_id' => 'SESSION' . $this->faker->unique()->numerify('##########'),
            'payment_id' => 'PAYMENT-' . $this->faker->unique()->numerify('###'),
            'merchant_id' => 'MERCHANT_' . $this->faker->numerify('###'),
            'status' => $this->faker->randomElement(['created', 'pending', 'captured', 'authorized', 'failed']),
            'amount' => $this->faker->randomFloat(2, 1, 1000),
            'currency' => 'USD',
            'description' => $this->faker->sentence(),
            'customer_email' => $this->faker->safeEmail(),
            'customer_name' => $this->faker->name(),
            'payment_method' => $this->faker->randomElement(['card', 'paypal', 'apple_pay']),
            'card_last4' => $this->faker->numerify('####'),
            'card_brand' => $this->faker->randomElement(['visa', 'mastercard', 'amex', 'discover']),
            'metadata' => [
                'source' => 'test',
                'ip_address' => $this->faker->ipv4(),
                'user_agent' => $this->faker->userAgent(),
            ],
            'expires_at' => $this->faker->dateTimeBetween('now', '+1 hour'),
            'completed_at' => null,
        ];
    }

    public function portal(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'portal_configuration' => [
                    'operation' => 'PAY',
                    'returnUrl' => 'https://example.com/success',
                    'cancelUrl' => 'https://example.com/cancel',
                    'merchant' => [
                        'name' => 'Test Store',
                        'logo' => 'https://example.com/logo.png',
                    ],
                ],
                'success_indicator' => $this->faker->lexify('????????????????'),
            ];
        });
    }

    public function completed(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'captured',
                'completed_at' => $this->faker->dateTimeBetween('-1 week', 'now'),
            ];
        });
    }

    public function expired(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'expires_at' => $this->faker->dateTimeBetween('-1 hour', '-1 minute'),
            ];
        });
    }

    public function cardPresent(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'payment_method' => 'card_present',
                'metadata' => array_merge($attributes['metadata'] ?? [], [
                    'terminal_id' => 'TERMINAL_' . $this->faker->numerify('###'),
                    'receipt_number' => 'RCP' . $this->faker->numerify('######'),
                ]),
            ];
        });
    }
}