<?php

declare(strict_types=1);

namespace Nocart\Shipping\Application\Command;

use Nocart\Shipping\Domain\Aggregate\ShippingSession;
use Nocart\Shipping\Domain\Repository\ShippingSessionRepositoryInterface;
use Nocart\Shipping\Domain\ValueObject\Address;
use Nocart\SharedKernel\Infrastructure\Messaging\EventPublisherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SetShippingAddressHandler
{
    public function __construct(
        private ShippingSessionRepositoryInterface $sessionRepository,
        private EventPublisherInterface $eventPublisher
    ) {
    }

    public function __invoke(SetShippingAddressCommand $command): void
    {
        $address = new Address(
            $command->street,
            $command->city,
            $command->postalCode,
            $command->country,
            $command->phoneNumber
        );

        $session = $this->sessionRepository->findBySessionId($command->sessionId)
            ?? new ShippingSession($command->sessionId, $command->userId);

        $session->setAddress($address);
        $this->sessionRepository->save($session);

        // Publish event
        $this->eventPublisher->publish('shipping-events', [
            'event_type' => 'ShippingAddressSet',
            'session_id' => $command->sessionId,
            'user_id' => $command->userId,
            'address' => $address->toArray(),
            'correlation_id' => $command->correlationId,
            'occurred_at' => date('c'),
        ]);
    }
}
