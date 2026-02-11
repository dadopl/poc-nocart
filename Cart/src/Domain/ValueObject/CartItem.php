<?php

declare(strict_types=1);

namespace Nocart\Cart\Domain\ValueObject;

use Nocart\SharedKernel\Domain\ValueObject\ItemId;
use Nocart\SharedKernel\Domain\ValueObject\ItemType;
use Nocart\SharedKernel\Domain\ValueObject\Money;
use Nocart\SharedKernel\Domain\ValueObject\Quantity;

final readonly class CartItem
{
    public function __construct(
        public ItemId $id,
        public int $offerId,
        public ItemType $type,
        public string $name,
        public Money $unitPrice,
        public Quantity $quantity,
        public ?ItemId $parentItemId = null,
    ) {
    }

    public function getTotalPrice(): Money
    {
        return $this->unitPrice->multiply($this->quantity->getValue());
    }

    public function withQuantity(Quantity $quantity): self
    {
        return new self(
            id: $this->id,
            offerId: $this->offerId,
            type: $this->type,
            name: $this->name,
            unitPrice: $this->unitPrice,
            quantity: $quantity,
            parentItemId: $this->parentItemId,
        );
    }

    public function isChildOf(ItemId $parentId): bool
    {
        return $this->parentItemId !== null && $this->parentItemId->equals($parentId);
    }

    public function hasParent(): bool
    {
        return $this->parentItemId !== null;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id->toString(),
            'offer_id' => $this->offerId,
            'type' => $this->type->value,
            'name' => $this->name,
            'unit_price' => $this->unitPrice->toArray(),
            'quantity' => $this->quantity->getValue(),
            'parent_item_id' => $this->parentItemId?->toString(),
            'total_price' => $this->getTotalPrice()->toArray(),
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: ItemId::fromString($data['id']),
            offerId: $data['offer_id'],
            type: ItemType::from($data['type']),
            name: $data['name'],
            unitPrice: Money::fromArray($data['unit_price']),
            quantity: Quantity::of($data['quantity']),
            parentItemId: isset($data['parent_item_id']) ? ItemId::fromString($data['parent_item_id']) : null,
        );
    }
}

