<?php

declare(strict_types=1);

namespace Nocart\SharedKernel\Domain\Event\Cart;

use Nocart\SharedKernel\Domain\Event\AbstractDomainEvent;

final readonly class CartItemQuantityChanged extends AbstractDomainEvent
{
    public function __construct(
        public string $cartId,
        public string $itemId,
        public int $oldQuantity,
        public int $newQuantity,
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
            'old_quantity' => $this->oldQuantity,
            'new_quantity' => $this->newQuantity,
        ];
    }
}

