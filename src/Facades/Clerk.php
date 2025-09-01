<?php

namespace Prahsys\Clerk\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Prahsys\Clerk\Data\PaymentResponseData createPayment(array $data)
 * @method static \Prahsys\Clerk\Data\PaymentResponseData getPayment(string $id)
 * @method static \Prahsys\Clerk\Data\SessionResponseData createSession(array $data)
 * @method static \Prahsys\Clerk\Data\PaymentResponseData refundPayment(string $id, ?int $amount = null)
 * @method static \Prahsys\Clerk\Data\PaymentResponseData capturePayment(string $id, ?int $amount = null)
 *
 * @see \Prahsys\Clerk\Services\PaymentService
 */
class Clerk extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'clerk';
    }
}