# Laravel Clerk - Prahsys Payments Integration

[![Latest Version on Packagist](https://img.shields.io/packagist/v/prahsys/laravel-clerk.svg?style=flat-square)](https://packagist.org/packages/prahsys/laravel-clerk)
[![GitHub Tests Action Status](https://img.shields.io/github/workflow/status/Prahsys/laravel-clerk/run-tests?label=tests)](https://github.com/Prahsys/laravel-clerk/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/workflow/status/Prahsys/laravel-clerk/Fix%20PHP%20code%20style%20issues?label=code%20style)](https://github.com/Prahsys/laravel-clerk/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/prahsys/laravel-clerk.svg?style=flat-square)](https://packagist.org/packages/prahsys/laravel-clerk)

The Laravel-native way to integrate Prahsys payments. Built for developers who value simplicity, security, and comprehensive features.

**Trusted by Laravel developers for processing millions in transactions.**

## Why Laravel Clerk?

- **🚀 Laravel-First**: Built specifically for Laravel with Eloquent models, database migrations, and artisan commands
- **💳 Multiple Payment Methods**: Cards, digital wallets, and card-present transactions
- **🔒 Enterprise Security**: PCI compliant with comprehensive audit logging and webhook verification
- **⚡ Developer Experience**: Type-safe DTOs, comprehensive test coverage, and excellent documentation
- **🔄 Robust Webhooks**: Automatic retry logic, signature verification, and event handling
- **📊 Complete Tracking**: Every transaction, status change, and event is logged and auditable

## Installation

Install the package via Composer:

```bash
composer require prahsys/laravel-clerk
```

Publish and run the migrations:

```bash
php artisan vendor:publish --tag="clerk-migrations"
php artisan migrate
```

Publish the configuration file:

```bash
php artisan vendor:publish --tag="clerk-config"
```

## Configuration

Add your Prahsys API credentials to your `.env` file:

```env
PRAHSYS_API_KEY=your_api_key_here
PRAHSYS_ENTITY_ID=your_entity_id_here
PRAHSYS_ENVIRONMENT=sandbox # or 'production'
PRAHSYS_WEBHOOK_SECRET=your_webhook_secret_here
```

## Quick Start

### 1. Create a Payment Session

```php
use Prahsys\LaravelClerk\Services\PaymentSessionManager;

$paymentManager = app(PaymentSessionManager::class);

// Create a simple payment session
$session = $paymentManager->createPaymentSession(
    paymentId: 'payment-123',
    amount: 99.99,
    description: 'Order #1234',
    customerEmail: 'customer@example.com',
    customerName: 'John Doe'
);

// Redirect user to the payment URL
return redirect($session->checkout_url);
```

### 2. Create a Payment Portal Session

```php
// Create a hosted payment portal
$session = $paymentManager->createPortalSession(
    paymentId: 'payment-123',
    amount: 99.99,
    description: 'Premium Subscription',
    returnUrl: route('payment.success'),
    cancelUrl: route('payment.cancel'),
    merchantName: 'My Store',
    merchantLogo: 'https://mystore.com/logo.png'
);

return redirect($session->portal_url);
```

### 3. Handle Webhooks

Create a webhook controller to handle payment events:

```php
use Prahsys\LaravelClerk\Services\WebhookEventHandler;
use Illuminate\Http\Request;

class PaymentWebhookController extends Controller
{
    public function handle(Request $request, WebhookEventHandler $handler)
    {
        $event = $handler->handleWebhook(
            payload: $request->getContent(),
            signature: $request->header('X-Prahsys-Signature')
        );

        // The event is automatically stored and processed
        // You can add custom logic here based on event type
        
        match($event->event_type) {
            'payment.captured' => $this->handlePaymentSuccess($event),
            'payment.failed' => $this->handlePaymentFailure($event),
            default => null,
        };

        return response('OK');
    }
    
    private function handlePaymentSuccess($event)
    {
        // Send confirmation email, update order status, etc.
        $session = $event->paymentSession;
        
        // Access payment details
        logger("Payment successful: {$session->payment_id} for {$session->amount}");
    }
}
```

## Core Features

### Payment Session Management

```php
use Prahsys\LaravelClerk\Models\PaymentSession;

// Query payment sessions
$pendingSessions = PaymentSession::where('status', 'pending')->get();
$completedToday = PaymentSession::whereDate('completed_at', today())->get();

// Check session status
if ($session->isCompleted()) {
    // Payment was successful
}

if ($session->isExpired()) {
    // Session has expired, create a new one
}

// Access related data
foreach ($session->transactions as $transaction) {
    echo "Transaction: {$transaction->transaction_id} - {$transaction->status}";
}
```

### Transaction Processing

```php
use Prahsys\LaravelClerk\Models\PaymentTransaction;

// Query transactions
$successfulTransactions = PaymentTransaction::where('status', 'captured')->get();
$failedTransactions = PaymentTransaction::where('status', 'failed')
    ->whereDate('created_at', today())
    ->get();

// Check transaction types
if ($transaction->isPayment()) {
    // This is a payment transaction
}

if ($transaction->isRefund()) {
    // This is a refund transaction
}

// Access gateway response data
$gatewayResponse = $transaction->gateway_response;
$responseCode = $gatewayResponse['response_code'];
```

### Audit Logging

Every action is automatically logged for compliance and debugging:

```php
use Prahsys\LaravelClerk\Models\AuditLog;

// View all audit logs for a payment session
$logs = $session->auditLogs()->recent()->get();

// Query specific events
$paymentEvents = AuditLog::forEventType('payment_processed')->get();
$recentActivity = AuditLog::recent(50)->get();

foreach ($logs as $log) {
    echo "Event: {$log->event_type} at {$log->created_at}";
    echo "Changes: " . json_encode($log->new_values);
}
```

### Card Present Transactions

```php
// Create a card present payment session
$session = $paymentManager->createPaymentSession(
    paymentId: 'terminal-payment-123',
    amount: 49.99,
    description: 'In-store purchase',
    customerEmail: 'customer@example.com',
    paymentMethod: 'card_present',
    metadata: [
        'terminal_id' => 'TERM_001',
        'location' => 'Store Front Desk'
    ]
);
```

## Advanced Usage

### Custom Payment Flows

```php
use Prahsys\LaravelClerk\Http\Requests\CreatePortalSessionRequest;
use Prahsys\LaravelClerk\Services\PaymentService;

class CustomPaymentController extends Controller 
{
    public function createCustomPayment(PaymentService $paymentService)
    {
        // Direct API integration for custom flows
        $response = $paymentService->createPortalSession([
            'amount' => 150.00,
            'currency' => 'USD',
            'paymentId' => 'custom-' . uniqid(),
            'operation' => 'PAY',
            'customer' => [
                'email' => 'customer@example.com',
                'name' => 'Jane Smith'
            ],
            'returnUrl' => route('payment.success'),
            'cancelUrl' => route('payment.cancel'),
            'merchant' => [
                'name' => 'Premium Services',
                'logo' => asset('images/logo.png')
            ]
        ]);

        if ($response->successful()) {
            $data = $response->data();
            return redirect($data->redirectUrl);
        }

        return back()->withErrors('Payment session creation failed');
    }
}
```

### Error Handling

```php
try {
    $session = $paymentManager->createPaymentSession(/* ... */);
} catch (\Prahsys\LaravelClerk\Exceptions\PrahsysException $e) {
    logger()->error('Payment session creation failed', [
        'error' => $e->getMessage(),
        'code' => $e->getErrorCode(),
        'details' => $e->getDetails()
    ]);
    
    return response()->json(['error' => 'Payment unavailable'], 503);
}
```

### Testing

Laravel Clerk provides comprehensive factories for testing:

```php
use Prahsys\LaravelClerk\Models\PaymentSession;

public function test_successful_payment_flow()
{
    // Create test data
    $session = PaymentSession::factory()
        ->completed()
        ->create(['amount' => 99.99]);
        
    $transaction = PaymentTransaction::factory()
        ->successful()
        ->create(['payment_session_id' => $session->id]);
    
    $this->assertTrue($session->isCompleted());
    $this->assertTrue($transaction->isSuccessful());
}

public function test_webhook_processing()
{
    // Test webhook events
    $webhookEvent = WebhookEvent::factory()
        ->paymentCaptured()
        ->create();
        
    $this->assertEquals('payment.captured', $webhookEvent->event_type);
    $this->assertTrue($webhookEvent->isProcessed());
}
```

## Configuration Options

The configuration file provides extensive customization options:

```php
// config/clerk.php
return [
    'api' => [
        'base_url' => env('PRAHSYS_BASE_URL', 'https://api.prahsys.com'),
        'timeout' => 30,
        'max_retries' => 3,
        'retry_delay' => 1000, // milliseconds
    ],
    
    'webhooks' => [
        'secret' => env('PRAHSYS_WEBHOOK_SECRET'),
        'max_retry_attempts' => 5,
        'verify_signatures' => true,
    ],
    
    'audit' => [
        'enabled' => true,
        'log_user_data' => true,
        'retention_days' => 365,
    ],
];
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Prahsys Team](https://github.com/Prahsys)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.