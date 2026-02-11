<?php

declare(strict_types=1);

namespace Nocart\Shipping\Application\Command;

use Nocart\Shipping\Domain\Aggregate\ShippingSession;
use Nocart\Shipping\Domain\Repository\ShippingMethodRepositoryInterface;
use Nocart\Shipping\Domain\Repository\ShippingSessionRepositoryInterface;
use Nocart\SharedKernel\Infrastructure\Messaging\EventPublisherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SelectShippingMethodHandler
{
    public function __construct(
        private ShippingMethodRepositoryInterface $methodRepository,
        private ShippingSessionRepositoryInterface $sessionRepository,
        private EventPublisherInterface $eventPublisher
    ) {
    }

    public function __invoke(SelectShippingMethodCommand $command): void
    {
        $method = $this->methodRepository->getById($command->methodId);
        if (!$method) {
            throw new \InvalidArgumentException("Shipping method {$command->methodId} not found");
        }

        $session = $this->sessionRepository->findBySessionId($command->sessionId)
            ?? new ShippingSession($command->sessionId, $command->userId);

        $session->selectMethod($method);
        $this->sessionRepository->save($session);

        // Publish event
        $this->eventPublisher->publish('shipping-events', [
            'event_type' => 'ShippingMethodSelected',
            'session_id' => $command->sessionId,
            'user_id' => $command->userId,
            'method_id' => $command->methodId,
            'method_name' => $method->name,
            'shipping_cost' => $method->price->amountInCents,
            'correlation_id' => $command->correlationId,
            'occurred_at' => date('c'),
        ]);
    }
}
