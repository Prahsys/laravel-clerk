# Laravel Clerk - Prahsys Payments Integration

[![Latest Version on Packagist](https://img.shields.io/packagist/v/prahsys/laravel-clerk.svg?style=flat-square)](https://packagist.org/packages/prahsys/laravel-clerk)
[![GitHub Tests Action Status](https://img.shields.io/github/workflow/status/Prahsys/laravel-clerk/run-tests?label=tests)](https://github.com/Prahsys/laravel-clerk/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/workflow/status/Prahsys/laravel-clerk/Fix%20PHP%20code%20style%20issues?label=code%20style)](https://github.com/Prahsys/laravel-clerk/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/prahsys/laravel-clerk.svg?style=flat-square)](https://packagist.org/packages/prahsys/laravel-clerk)

The Laravel-native way to integrate Prahsys payments that just works.

## Installation

You can install the package via composer:

```bash
composer require prahsys/laravel-clerk
```

You can publish and run the migrations with:

```bash
php artisan vendor:publish --tag="clerk-migrations"
php artisan migrate
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="clerk-config"
```

## Usage

```php
use Prahsys\Clerk\Facades\Clerk;

$payment = Clerk::createPayment([
    'amount' => 1000, // $10.00 in cents
    'currency' => 'USD',
    'customer_email' => 'customer@example.com',
]);
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