<?php

declare(strict_types=1);

namespace Nocart\Cart\Application\Command;

use Nocart\Cart\Domain\Aggregate\Cart;
use Nocart\Cart\Domain\Repository\CartRepositoryInterface;
use Nocart\SharedKernel\Domain\ValueObject\CartId;
use Nocart\SharedKernel\Domain\ValueObject\ItemId;
use Nocart\SharedKernel\Domain\ValueObject\ItemType;
use Nocart\SharedKernel\Domain\ValueObject\Money;
use Nocart\SharedKernel\Domain\ValueObject\Quantity;
use Nocart\SharedKernel\Domain\ValueObject\UserId;
use Nocart\SharedKernel\Infrastructure\Messaging\EventPublisherInterface;
use Nocart\SharedKernel\Infrastructure\Messaging\KafkaTopics;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class AddItemToCartHandler
{
    public function __construct(
        private CartRepositoryInterface $cartRepository,
        private EventPublisherInterface $eventPublisher,
    ) {
    }

    public function __invoke(AddItemToCartCommand $command): string
    {
        $userId = UserId::fromString($command->userId);
        $cart = $this->cartRepository->findByUserId($userId);

        if ($cart === null) {
            $cart = Cart::create(
                id: CartId::generate(),
                userId: $userId,
            );
        }

        $itemId = ItemId::generate();

        $parentItemId = null;
        if ($command->parentItemId !== null && $command->parentItemId !== '') {
            $parentItemId = ItemId::fromString($command->parentItemId);
        }

        $cart->addItem(
            itemId: $itemId,
            offerId: $command->offerId,
            type: ItemType::from($command->type),
            name: $command->name,
            unitPrice: Money::fromFloat($command->price),
            quantity: Quantity::of($command->quantity),
            parentItemId: $parentItemId,
            correlationId: $command->correlationId,
        );

        $this->cartRepository->save($cart);

        foreach ($cart->pullDomainEvents() as $event) {
            $this->eventPublisher->publish(KafkaTopics::CART_EVENTS, $event);
        }

        return $itemId->toString();
    }
}

