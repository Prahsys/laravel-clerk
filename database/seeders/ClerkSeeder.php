<?php

declare(strict_types=1);

namespace Prahsys\LaravelClerk\Database\Seeders;

use Illuminate\Database\Seeder;
use Prahsys\LaravelClerk\Models\PaymentSession;
use Prahsys\LaravelClerk\Models\PaymentTransaction;
use Prahsys\LaravelClerk\Models\WebhookEvent;
use Prahsys\LaravelClerk\Models\AuditLog;

class ClerkSeeder extends Seeder
{
    public function run(): void
    {
        // Create completed portal payment sessions with transactions
        PaymentSession::factory()
            ->count(5)
            ->portal()
            ->completed()
            ->create()
            ->each(function (PaymentSession $session) {
                // Create successful transactions
                PaymentTransaction::factory()
                    ->count(2)
                    ->successful()
                    ->create(['payment_session_id' => $session->id]);
                
                // Create webhook events
                WebhookEvent::factory()
                    ->count(3)
                    ->processed()
                    ->create(['payment_session_id' => $session->id]);
                
                // Create audit logs
                AuditLog::factory()
                    ->count(2)
                    ->paymentProcessed()
                    ->create([
                        'auditable_type' => PaymentSession::class,
                        'auditable_id' => $session->id,
                    ]);
            });

        // Create pending payment sessions
        PaymentSession::factory()
            ->count(3)
            ->create()
            ->each(function (PaymentSession $session) {
                // Create pending transactions
                PaymentTransaction::factory()
                    ->pending()
                    ->create(['payment_session_id' => $session->id]);
                
                // Create audit logs
                AuditLog::factory()
                    ->created()
                    ->create([
                        'auditable_type' => PaymentSession::class,
                        'auditable_id' => $session->id,
                    ]);
            });

        // Create failed payment sessions
        PaymentSession::factory()
            ->count(2)
            ->state(['status' => 'failed'])
            ->create()
            ->each(function (PaymentSession $session) {
                // Create failed transactions
                PaymentTransaction::factory()
                    ->failed()
                    ->create(['payment_session_id' => $session->id]);
                
                // Create failed webhook events
                WebhookEvent::factory()
                    ->failed()
                    ->create(['payment_session_id' => $session->id]);
                
                // Create audit logs
                AuditLog::factory()
                    ->paymentProcessed()
                    ->create([
                        'auditable_type' => PaymentSession::class,
                        'auditable_id' => $session->id,
                        'metadata' => [
                            'status' => 'failed',
                            'gateway_response' => [
                                'transaction_id' => 'TXN' . fake()->numerify('##########'),
                                'response_code' => '05',
                                'response_message' => 'Do not honor',
                            ],
                            'timestamp' => now()->toISOString(),
                        ],
                    ]);
            });

        // Create expired payment sessions
        PaymentSession::factory()
            ->count(2)
            ->expired()
            ->create()
            ->each(function (PaymentSession $session) {
                AuditLog::factory()
                    ->create([
                        'auditable_type' => PaymentSession::class,
                        'auditable_id' => $session->id,
                        'event_type' => 'session_expired',
                        'metadata' => [
                            'expired_at' => $session->expires_at->toISOString(),
                        ],
                    ]);
            });

        // Create card present payment sessions
        PaymentSession::factory()
            ->count(3)
            ->cardPresent()
            ->completed()
            ->create()
            ->each(function (PaymentSession $session) {
                PaymentTransaction::factory()
                    ->cardPresent()
                    ->successful()
                    ->create(['payment_session_id' => $session->id]);
            });

        $this->command->info('Clerk test data seeded successfully!');
        $this->command->info('Created:');
        $this->command->info('- ' . PaymentSession::count() . ' payment sessions');
        $this->command->info('- ' . PaymentTransaction::count() . ' payment transactions');
        $this->command->info('- ' . WebhookEvent::count() . ' webhook events');
        $this->command->info('- ' . AuditLog::count() . ' audit log entries');
    }
}