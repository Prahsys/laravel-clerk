<?php

declare(strict_types=1);

namespace Prahsys\LaravelClerk\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Prahsys\LaravelClerk\Models\PaymentSession;
use Prahsys\LaravelClerk\Models\WebhookEvent;

class WebhookEventFactory extends Factory
{
    protected $model = WebhookEvent::class;

    public function definition(): array
    {
        return [
            'payment_session_id' => PaymentSession::factory(),
            'event_id' => 'evt_' . $this->faker->unique()->lexify('????????????????????'),
            'event_type' => $this->faker->randomElement([
                'payment.created',
                'payment.captured',
                'payment.failed',
                'session.expired',
                'refund.created',
            ]),
            'status' => $this->faker->randomElement(['pending', 'processed', 'failed']),
            'payload' => [
                'id' => 'evt_' . $this->faker->lexify('????????????????????'),
                'object' => 'event',
                'created' => now()->timestamp,
                'data' => [
                    'object' => [
                        'id' => 'pay_' . $this->faker->lexify('????????????????????'),
                        'amount' => $this->faker->randomFloat(2, 1, 1000),
                        'currency' => 'USD',
                        'status' => 'succeeded',
                    ],
                ],
            ],
            'signature' => 'prahsys_' . $this->faker->lexify('????????????????????????????????'),
            'retry_count' => 0,
        ];
    }

    public function processed(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'processed',
                'processed_at' => $this->faker->dateTimeBetween('-1 week', 'now'),
            ];
        });
    }

    public function failed(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'failed',
                'failed_at' => $this->faker->dateTimeBetween('-1 week', 'now'),
                'error_message' => $this->faker->sentence(),
                'retry_count' => $this->faker->numberBetween(1, 3),
            ];
        });
    }

    public function paymentCaptured(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'event_type' => 'payment.captured',
                'payload' => [
                    'id' => 'evt_' . $this->faker->lexify('????????????????????'),
                    'object' => 'event',
                    'type' => 'payment.captured',
                    'created' => now()->timestamp,
                    'data' => [
                        'object' => [
                            'id' => 'pay_' . $this->faker->lexify('????????????????????'),
                            'amount' => $attributes['payload']['data']['object']['amount'] ?? $this->faker->randomFloat(2, 1, 1000),
                            'currency' => 'USD',
                            'status' => 'captured',
                            'captured_at' => now()->timestamp,
                        ],
                    ],
                ],
            ];
        });
    }

    public function paymentFailed(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'event_type' => 'payment.failed',
                'payload' => [
                    'id' => 'evt_' . $this->faker->lexify('????????????????????'),
                    'object' => 'event',
                    'type' => 'payment.failed',
                    'created' => now()->timestamp,
                    'data' => [
                        'object' => [
                            'id' => 'pay_' . $this->faker->lexify('????????????????????'),
                            'amount' => $attributes['payload']['data']['object']['amount'] ?? $this->faker->randomFloat(2, 1, 1000),
                            'currency' => 'USD',
                            'status' => 'failed',
                            'failure_reason' => 'card_declined',
                        ],
                    ],
                ],
            ];
        });
    }
}