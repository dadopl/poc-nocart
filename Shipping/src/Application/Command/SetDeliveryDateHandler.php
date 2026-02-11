<?php

declare(strict_types=1);

namespace Nocart\Shipping\Application\Command;

use Nocart\Shipping\Domain\Aggregate\ShippingSession;
use Nocart\Shipping\Domain\Repository\ShippingSessionRepositoryInterface;
use Nocart\SharedKernel\Infrastructure\Messaging\EventPublisherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SetDeliveryDateHandler
{
    public function __construct(
        private ShippingSessionRepositoryInterface $sessionRepository,
        private EventPublisherInterface $eventPublisher
    ) {
    }

    public function __invoke(SetDeliveryDateCommand $command): void
    {
        $session = $this->sessionRepository->findBySessionId($command->sessionId)
            ?? new ShippingSession($command->sessionId, $command->userId);

        $session->setDeliveryDate($command->deliveryDate, $command->express);
        $this->sessionRepository->save($session);

        // Publish event
        $this->eventPublisher->publish('shipping-events', [
            'event_type' => 'DeliveryDateSet',
            'session_id' => $command->sessionId,
            'user_id' => $command->userId,
            'delivery_date' => $command->deliveryDate,
            'is_express' => $command->express,
            'correlation_id' => $command->correlationId,
            'occurred_at' => date('c'),
        ]);
    }
}
