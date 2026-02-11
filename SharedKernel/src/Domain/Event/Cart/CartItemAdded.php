<?php

declare(strict_types=1);

namespace Nocart\SharedKernel\Domain\Event\Cart;

use Nocart\SharedKernel\Domain\Event\AbstractDomainEvent;

final readonly class CartItemAdded extends AbstractDomainEvent
{
    public function __construct(
        public string $cartId,
        public string $itemId,
        public string $itemType,
        public int $offerId,
        public int $quantity,
        public int $priceAmount,
        public string $priceCurrency,
        public ?string $parentItemId = null,
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
            'offer_id' => $this->offerId,
            'quantity' => $this->quantity,
            'price_amount' => $this->priceAmount,
            'price_currency' => $this->priceCurrency,
            'parent_item_id' => $this->parentItemId,
        ];
    }
}

