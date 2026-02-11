<?php

declare(strict_types=1);

namespace Nocart\SharedKernel\Domain\Event\Cart;

use Nocart\SharedKernel\Domain\Event\AbstractDomainEvent;

final readonly class CartItemRemoved extends AbstractDomainEvent
{
    public function __construct(
        public string $cartId,
        public string $itemId,
        public string $itemType,
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
            'item_id' => $this->itemId,
            'item_type' => $this->itemType,
        ];
    }
}

