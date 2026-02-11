<?php

declare(strict_types=1);

namespace Nocart\Cart\Domain\Aggregate;

use Nocart\Cart\Domain\Exception\CartItemNotFoundException;
use Nocart\Cart\Domain\ValueObject\CartItem;
use Nocart\SharedKernel\Domain\Aggregate\AggregateRoot;
use Nocart\SharedKernel\Domain\Event\Cart\CartCleared;
use Nocart\SharedKernel\Domain\Event\Cart\CartItemAdded;
use Nocart\SharedKernel\Domain\Event\Cart\CartItemQuantityChanged;
use Nocart\SharedKernel\Domain\Event\Cart\CartItemRemoved;
use Nocart\SharedKernel\Domain\ValueObject\CartId;
use Nocart\SharedKernel\Domain\ValueObject\ItemId;
use Nocart\SharedKernel\Domain\ValueObject\ItemType;
use Nocart\SharedKernel\Domain\ValueObject\Money;
use Nocart\SharedKernel\Domain\ValueObject\Quantity;
use Nocart\SharedKernel\Domain\ValueObject\UserId;

final class Cart extends AggregateRoot
{
    /** @var array<string, CartItem> */
    private array $items = [];

    private function __construct(
        private readonly CartId $id,
        private readonly UserId $userId,
        private readonly \DateTimeImmutable $createdAt,
        private \DateTimeImmutable $updatedAt,
    ) {
    }

    public static function create(CartId $id, UserId $userId): self
    {
        $now = new \DateTimeImmutable();

        return new self(
            id: $id,
            userId: $userId,
            createdAt: $now,
            updatedAt: $now,
        );
    }

    public function addItem(
        ItemId $itemId,
        int $offerId,
        ItemType $type,
        string $name,
        Money $unitPrice,
        Quantity $quantity,
        ?ItemId $parentItemId = null,
        ?string $correlationId = null,
    ): void {
        $item = new CartItem(
            id: $itemId,
            offerId: $offerId,
            type: $type,
            name: $name,
            unitPrice: $unitPrice,
            quantity: $quantity,
            parentItemId: $parentItemId,
        );

        $this->items[$itemId->toString()] = $item;
        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(new CartItemAdded(
            cartId: $this->id->toString(),
            itemId: $itemId->toString(),
            itemType: $type->value,
            offerId: $offerId,
            quantity: $quantity->getValue(),
            priceAmount: $unitPrice->getAmount(),
            priceCurrency: $unitPrice->getCurrency(),
            parentItemId: $parentItemId?->toString(),
            correlationId: $correlationId,
        ));
    }

    public function removeItem(ItemId $itemId, ?string $correlationId = null): void
    {
        if (!isset($this->items[$itemId->toString()])) {
            throw CartItemNotFoundException::withId($itemId);
        }

        $item = $this->items[$itemId->toString()];
        unset($this->items[$itemId->toString()]);

        $this->removeChildItems($itemId, $correlationId);

        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(new CartItemRemoved(
            cartId: $this->id->toString(),
            itemId: $itemId->toString(),
            itemType: $item->type->value,
            correlationId: $correlationId,
        ));
    }

    public function changeQuantity(ItemId $itemId, Quantity $newQuantity, ?string $correlationId = null): void
    {
        if (!isset($this->items[$itemId->toString()])) {
            throw CartItemNotFoundException::withId($itemId);
        }

        $item = $this->items[$itemId->toString()];
        $oldQuantity = $item->quantity;

        if ($newQuantity->isZero()) {
            $this->removeItem($itemId, $correlationId);
            return;
        }

        $this->items[$itemId->toString()] = $item->withQuantity($newQuantity);

        $this->updateChildItemsQuantity($itemId, $oldQuantity, $newQuantity);

        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(new CartItemQuantityChanged(
            cartId: $this->id->toString(),
            itemId: $itemId->toString(),
            oldQuantity: $oldQuantity->getValue(),
            newQuantity: $newQuantity->getValue(),
            correlationId: $correlationId,
        ));
    }

    public function clear(?string $correlationId = null): void
    {
        $this->items = [];
        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(new CartCleared(
            cartId: $this->id->toString(),
            correlationId: $correlationId,
        ));
    }

    public function getTotal(): Money
    {
        $total = Money::zero();

        foreach ($this->items as $item) {
            $total = $total->add($item->getTotalPrice());
        }

        return $total;
    }

    public function getItemsCount(): int
    {
        return count($this->items);
    }

    public function getTotalQuantity(): int
    {
        $total = 0;

        foreach ($this->items as $item) {
            $total += $item->quantity->getValue();
        }

        return $total;
    }

    public function isEmpty(): bool
    {
        return count($this->items) === 0;
    }

    public function getItem(ItemId $itemId): ?CartItem
    {
        return $this->items[$itemId->toString()] ?? null;
    }

    public function hasItem(ItemId $itemId): bool
    {
        return isset($this->items[$itemId->toString()]);
    }

    /** @return array<CartItem> */
    public function getItems(): array
    {
        return array_values($this->items);
    }

    /** @return array<CartItem> */
    public function getChildItems(ItemId $parentId): array
    {
        return array_filter(
            $this->items,
            fn(CartItem $item) => $item->isChildOf($parentId)
        );
    }

    public function getId(): CartId
    {
        return $this->id;
    }

    public function getUserId(): UserId
    {
        return $this->userId;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    private function removeChildItems(ItemId $parentId, ?string $correlationId): void
    {
        foreach ($this->items as $key => $item) {
            if ($item->isChildOf($parentId)) {
                unset($this->items[$key]);

                $this->recordEvent(new CartItemRemoved(
                    cartId: $this->id->toString(),
                    itemId: $item->id->toString(),
                    itemType: $item->type->value,
                    correlationId: $correlationId,
                ));
            }
        }
    }

    private function updateChildItemsQuantity(ItemId $parentId, Quantity $oldQuantity, Quantity $newQuantity): void
    {
        $ratio = $newQuantity->getValue() / $oldQuantity->getValue();

        foreach ($this->items as $key => $item) {
            if ($item->isChildOf($parentId)) {
                $newChildQuantity = Quantity::of((int) ($item->quantity->getValue() * $ratio));
                $this->items[$key] = $item->withQuantity($newChildQuantity);
            }
        }
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id->toString(),
            'user_id' => $this->userId->toString(),
            'items' => array_map(fn(CartItem $item) => $item->toArray(), $this->items),
            'total' => $this->getTotal()->toArray(),
            'items_count' => $this->getItemsCount(),
            'total_quantity' => $this->getTotalQuantity(),
            'created_at' => $this->createdAt->format(\DateTimeInterface::ATOM),
            'updated_at' => $this->updatedAt->format(\DateTimeInterface::ATOM),
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $cart = new self(
            id: CartId::fromString($data['id']),
            userId: UserId::fromString($data['user_id']),
            createdAt: new \DateTimeImmutable($data['created_at']),
            updatedAt: new \DateTimeImmutable($data['updated_at']),
        );

        foreach ($data['items'] as $itemData) {
            $cart->items[$itemData['id']] = CartItem::fromArray($itemData);
        }

        return $cart;
    }
}

