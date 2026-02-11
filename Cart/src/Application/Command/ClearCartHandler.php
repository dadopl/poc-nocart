<?php

declare(strict_types=1);

namespace Nocart\Cart\Application\Command;

use Nocart\Cart\Domain\Repository\CartRepositoryInterface;
use Nocart\SharedKernel\Domain\ValueObject\UserId;
use Nocart\SharedKernel\Infrastructure\Messaging\EventPublisherInterface;
use Nocart\SharedKernel\Infrastructure\Messaging\KafkaTopics;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ClearCartHandler
{
    public function __construct(
        private CartRepositoryInterface $cartRepository,
        private EventPublisherInterface $eventPublisher,
    ) {
    }

    public function __invoke(ClearCartCommand $command): void
    {
        $userId = UserId::fromString($command->userId);
        $cart = $this->cartRepository->findByUserId($userId);

        if ($cart === null) {
            return;
        }

        $cart->clear($command->correlationId);

        $this->cartRepository->save($cart);

        foreach ($cart->pullDomainEvents() as $event) {
            $this->eventPublisher->publish(KafkaTopics::CART_EVENTS, $event);
        }
    }
}

