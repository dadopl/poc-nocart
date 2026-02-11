<?php

declare(strict_types=1);

namespace Nocart\Cart\Tests\Unit\Domain\Aggregate;

use Nocart\Cart\Domain\Aggregate\Cart;
use Nocart\Cart\Domain\Exception\CartItemNotFoundException;
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
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Cart::class)]
final class CartTest extends TestCase
{
    #[Test]
    public function itCreatesEmptyCart(): void
    {
        // Arrange
        $cartId = CartId::generate();
        $userId = UserId::generate();

        // Act
        $cart = Cart::create($cartId, $userId);

        // Assert
        $this->assertTrue($cart->isEmpty());
        $this->assertSame(0, $cart->getItemsCount());
        $this->assertTrue($cart->getTotal()->isZero());
    }

    #[Test]
    public function itAddsItemToCart(): void
    {
        // Arrange
        $cart = $this->createCart();
        $itemId = ItemId::generate();

        // Act
        $cart->addItem(
            itemId: $itemId,
            offerId: 123,
            type: ItemType::PRODUCT,
            name: 'Laptop Dell XPS',
            unitPrice: Money::fromFloat(5999.00),
            quantity: Quantity::one(),
        );

        // Assert
        $this->assertFalse($cart->isEmpty());
        $this->assertSame(1, $cart->getItemsCount());
        $this->assertTrue($cart->hasItem($itemId));
        $this->assertEquals(599900, $cart->getTotal()->getAmount());

        $events = $cart->pullDomainEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(CartItemAdded::class, $events[0]);
    }

    #[Test]
    public function itAddsMultipleItemsToCart(): void
    {
        // Arrange
        $cart = $this->createCart();

        // Act
        $cart->addItem(
            itemId: ItemId::generate(),
            offerId: 123,
            type: ItemType::PRODUCT,
            name: 'Laptop',
            unitPrice: Money::fromFloat(5999.00),
            quantity: Quantity::one(),
        );

        $cart->addItem(
            itemId: ItemId::generate(),
            offerId: 456,
            type: ItemType::PRODUCT,
            name: 'Torba',
            unitPrice: Money::fromFloat(149.00),
            quantity: Quantity::one(),
        );

        // Assert
        $this->assertSame(2, $cart->getItemsCount());
        $this->assertEquals(614800, $cart->getTotal()->getAmount());
    }

    #[Test]
    public function itAddsChildItemToParent(): void
    {
        // Arrange
        $cart = $this->createCart();
        $laptopId = ItemId::generate();
        $warrantyId = ItemId::generate();

        // Act
        $cart->addItem(
            itemId: $laptopId,
            offerId: 123,
            type: ItemType::PRODUCT,
            name: 'Laptop',
            unitPrice: Money::fromFloat(5999.00),
            quantity: Quantity::one(),
        );

        $cart->addItem(
            itemId: $warrantyId,
            offerId: 456,
            type: ItemType::WARRANTY,
            name: 'Gwarancja 36m',
            unitPrice: Money::fromFloat(299.00),
            quantity: Quantity::one(),
            parentItemId: $laptopId,
        );

        // Assert
        $warranty = $cart->getItem($warrantyId);
        $this->assertNotNull($warranty);
        $this->assertTrue($warranty->isChildOf($laptopId));

        $childItems = $cart->getChildItems($laptopId);
        $this->assertCount(1, $childItems);
    }

    #[Test]
    public function itRemovesItemFromCart(): void
    {
        // Arrange
        $cart = $this->createCart();
        $itemId = ItemId::generate();

        $cart->addItem(
            itemId: $itemId,
            offerId: 123,
            type: ItemType::PRODUCT,
            name: 'Laptop',
            unitPrice: Money::fromFloat(5999.00),
            quantity: Quantity::one(),
        );
        $cart->pullDomainEvents();

        // Act
        $cart->removeItem($itemId);

        // Assert
        $this->assertTrue($cart->isEmpty());
        $this->assertFalse($cart->hasItem($itemId));

        $events = $cart->pullDomainEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(CartItemRemoved::class, $events[0]);
    }

    #[Test]
    public function itRemovesChildItemsWhenParentRemoved(): void
    {
        // Arrange
        $cart = $this->createCart();
        $laptopId = ItemId::generate();
        $warrantyId = ItemId::generate();

        $cart->addItem(
            itemId: $laptopId,
            offerId: 123,
            type: ItemType::PRODUCT,
            name: 'Laptop',
            unitPrice: Money::fromFloat(5999.00),
            quantity: Quantity::one(),
        );

        $cart->addItem(
            itemId: $warrantyId,
            offerId: 456,
            type: ItemType::WARRANTY,
            name: 'Gwarancja',
            unitPrice: Money::fromFloat(299.00),
            quantity: Quantity::one(),
            parentItemId: $laptopId,
        );
        $cart->pullDomainEvents();

        // Act
        $cart->removeItem($laptopId);

        // Assert
        $this->assertTrue($cart->isEmpty());
        $this->assertFalse($cart->hasItem($warrantyId));

        $events = $cart->pullDomainEvents();
        $this->assertCount(2, $events);
    }

    #[Test]
    public function itThrowsExceptionWhenRemovingNonExistentItem(): void
    {
        // Arrange
        $cart = $this->createCart();
        $itemId = ItemId::generate();

        // Assert
        $this->expectException(CartItemNotFoundException::class);

        // Act
        $cart->removeItem($itemId);
    }

    #[Test]
    public function itChangesItemQuantity(): void
    {
        // Arrange
        $cart = $this->createCart();
        $itemId = ItemId::generate();

        $cart->addItem(
            itemId: $itemId,
            offerId: 123,
            type: ItemType::PRODUCT,
            name: 'Laptop',
            unitPrice: Money::fromFloat(5999.00),
            quantity: Quantity::one(),
        );
        $cart->pullDomainEvents();

        // Act
        $cart->changeQuantity($itemId, Quantity::of(2));

        // Assert
        $item = $cart->getItem($itemId);
        $this->assertSame(2, $item->quantity->getValue());
        $this->assertEquals(1199800, $cart->getTotal()->getAmount());

        $events = $cart->pullDomainEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(CartItemQuantityChanged::class, $events[0]);
    }

    #[Test]
    public function itRemovesItemWhenQuantitySetToZero(): void
    {
        // Arrange
        $cart = $this->createCart();
        $itemId = ItemId::generate();

        $cart->addItem(
            itemId: $itemId,
            offerId: 123,
            type: ItemType::PRODUCT,
            name: 'Laptop',
            unitPrice: Money::fromFloat(5999.00),
            quantity: Quantity::one(),
        );
        $cart->pullDomainEvents();

        // Act
        $cart->changeQuantity($itemId, Quantity::zero());

        // Assert
        $this->assertTrue($cart->isEmpty());

        $events = $cart->pullDomainEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(CartItemRemoved::class, $events[0]);
    }

    #[Test]
    public function itClearsCart(): void
    {
        // Arrange
        $cart = $this->createCart();

        $cart->addItem(
            itemId: ItemId::generate(),
            offerId: 123,
            type: ItemType::PRODUCT,
            name: 'Laptop',
            unitPrice: Money::fromFloat(5999.00),
            quantity: Quantity::one(),
        );

        $cart->addItem(
            itemId: ItemId::generate(),
            offerId: 456,
            type: ItemType::PRODUCT,
            name: 'Torba',
            unitPrice: Money::fromFloat(149.00),
            quantity: Quantity::one(),
        );
        $cart->pullDomainEvents();

        // Act
        $cart->clear();

        // Assert
        $this->assertTrue($cart->isEmpty());
        $this->assertTrue($cart->getTotal()->isZero());

        $events = $cart->pullDomainEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(CartCleared::class, $events[0]);
    }

    #[Test]
    public function itSerializesAndDeserializesCart(): void
    {
        // Arrange
        $cart = $this->createCart();
        $laptopId = ItemId::generate();

        $cart->addItem(
            itemId: $laptopId,
            offerId: 123,
            type: ItemType::PRODUCT,
            name: 'Laptop',
            unitPrice: Money::fromFloat(5999.00),
            quantity: Quantity::of(2),
        );

        $cart->addItem(
            itemId: ItemId::generate(),
            offerId: 456,
            type: ItemType::WARRANTY,
            name: 'Gwarancja',
            unitPrice: Money::fromFloat(299.00),
            quantity: Quantity::of(2),
            parentItemId: $laptopId,
        );

        // Act
        $data = $cart->toArray();
        $restoredCart = Cart::fromArray($data);

        // Assert
        $this->assertEquals($cart->getId()->toString(), $restoredCart->getId()->toString());
        $this->assertEquals($cart->getUserId()->toString(), $restoredCart->getUserId()->toString());
        $this->assertEquals($cart->getItemsCount(), $restoredCart->getItemsCount());
        $this->assertEquals($cart->getTotal()->getAmount(), $restoredCart->getTotal()->getAmount());
    }

    private function createCart(): Cart
    {
        return Cart::create(
            id: CartId::generate(),
            userId: UserId::generate(),
        );
    }
}

