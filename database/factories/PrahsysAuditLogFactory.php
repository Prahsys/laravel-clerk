<?php

declare(strict_types=1);

namespace Prahsys\LaravelClerk\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Prahsys\LaravelClerk\Models\PrahsysAuditLog;
use Prahsys\LaravelClerk\Models\PaymentSession;

class PrahsysAuditLogFactory extends Factory
{
    protected $model = PrahsysAuditLog::class;

    public function definition(): array
    {
        return [
            'auditable_type' => PaymentSession::class,
            'auditable_id' => PaymentSession::factory(),
            'event_type' => $this->faker->randomElement(['created', 'updated', 'deleted', 'processed']),
            'user_id' => null,
            'user_type' => null,
            'ip_address' => $this->faker->ipv4(),
            'user_agent' => $this->faker->userAgent(),
            'old_values' => [],
            'new_values' => [
                'status' => $this->faker->randomElement(['created', 'pending', 'captured']),
                'amount' => $this->faker->randomFloat(2, 1, 500),
            ],
            'metadata' => [
                'source' => 'system',
                'timestamp' => now()->toISOString(),
            ],
        ];
    }

    public function created(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'event_type' => 'created',
                'old_values' => [],
                'new_values' => [
                    'session_id' => 'SESSION' . $this->faker->numerify('##########'),
                    'status' => 'created',
                    'amount' => $this->faker->randomFloat(2, 1, 500),
                ],
            ];
        });
    }

    public function updated(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'event_type' => 'updated',
                'old_values' => [
                    'status' => 'pending',
                ],
                'new_values' => [
                    'status' => 'captured',
                    'completed_at' => now()->toDateTimeString(),
                ],
            ];
        });
    }

    public function deleted(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'event_type' => 'deleted',
                'old_values' => [
                    'session_id' => 'SESSION' . $this->faker->numerify('##########'),
                    'status' => 'created',
                ],
                'new_values' => [],
            ];
        });
    }

    public function paymentProcessed(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'event_type' => 'payment_processed',
                'metadata' => [
                    'status' => $this->faker->randomElement(['captured', 'failed']),
                    'gateway_response' => [
                        'transaction_id' => 'TXN' . $this->faker->numerify('##########'),
                        'response_code' => $this->faker->randomElement(['00', '01', '05']),
                    ],
                    'timestamp' => now()->toISOString(),
                ],
            ];
        });
    }

    public function webhookProcessed(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'event_type' => 'webhook_processed',
                'metadata' => [
                    'webhook_event_type' => $this->faker->randomElement(['payment.succeeded', 'payment.failed']),
                    'payload_summary' => [
                        'event_id' => 'evt_' . $this->faker->lexify('????????????????'),
                        'event_type' => 'payment.succeeded',
                        'object_id' => 'pay_' . $this->faker->lexify('????????????????'),
                        'amount' => $this->faker->randomFloat(2, 1, 500),
                        'currency' => 'USD',
                        'status' => 'succeeded',
                    ],
                    'timestamp' => now()->toISOString(),
                ],
            ];
        });
    }

    public function withUser(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'user_id' => $this->faker->randomNumber(5),
                'user_type' => 'App\\Models\\User',
            ];
        });
    }
}