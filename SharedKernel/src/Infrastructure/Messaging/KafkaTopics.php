<?php

declare(strict_types=1);

namespace Nocart\SharedKernel\Infrastructure\Messaging;

final readonly class KafkaTopics
{
    public const CART_EVENTS = 'cart-events';
    public const SHIPPING_EVENTS = 'shipping-events';
    public const PAYMENT_EVENTS = 'payment-events';
    public const CHECKOUT_EVENTS = 'checkout-events';
    public const PROMOTION_EVENTS = 'promotion-events';
    public const SERVICES_EVENTS = 'services-events';
}

