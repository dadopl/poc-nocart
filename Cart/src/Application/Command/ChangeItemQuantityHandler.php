<?php

declare(strict_types=1);

namespace Nocart\Cart\Application\Command;

use Nocart\Cart\Domain\Exception\CartNotFoundException;
use Nocart\Cart\Domain\Repository\CartRepositoryInterface;
use Nocart\SharedKernel\Domain\ValueObject\ItemId;
use Nocart\SharedKernel\Domain\ValueObject\Quantity;
use Nocart\SharedKernel\Domain\ValueObject\UserId;
use Nocart\SharedKernel\Infrastructure\Messaging\EventPublisherInterface;
use Nocart\SharedKernel\Infrastructure\Messaging\KafkaTopics;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ChangeItemQuantityHandler
{
    public function __construct(
        private CartRepositoryInterface $cartRepository,
        private EventPublisherInterface $eventPublisher,
    ) {
    }

    public function __invoke(ChangeItemQuantityCommand $command): void
    {
        $userId = UserId::fromString($command->userId);
        $cart = $this->cartRepository->findByUserId($userId);

        if ($cart === null) {
            throw CartNotFoundException::forUser($userId);
        }

        $cart->changeQuantity(
            itemId: ItemId::fromString($command->itemId),
            newQuantity: Quantity::of($command->quantity),
            correlationId: $command->correlationId,
        );

        $this->cartRepository->save($cart);

        foreach ($cart->pullDomainEvents() as $event) {
            $this->eventPublisher->publish(KafkaTopics::CART_EVENTS, $event);
        }
    }
}

