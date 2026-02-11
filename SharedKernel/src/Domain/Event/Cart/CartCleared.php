<?php

declare(strict_types=1);

namespace Nocart\SharedKernel\Domain\Event\Cart;

use Nocart\SharedKernel\Domain\Event\AbstractDomainEvent;

final readonly class CartCleared extends AbstractDomainEvent
{
    public function __construct(
        public string $cartId,
        ?string $correlationId = null,
    ) {
        parent::__construct(correlationId: $correlationId);
    }

    public function getAggregateId(): string
    {
        return $this->cartId;
    }

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'cart_id' => $this->cartId,
        ];
    }
}

