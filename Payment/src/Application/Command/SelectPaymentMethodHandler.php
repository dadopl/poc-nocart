<?php

declare(strict_types=1);

namespace Nocart\Payment\Application\Command;

use Nocart\Payment\Domain\Aggregate\PaymentSession;
use Nocart\Payment\Domain\Repository\PaymentMethodRepositoryInterface;
use Nocart\Payment\Domain\Repository\PaymentSessionRepositoryInterface;
use Nocart\SharedKernel\Infrastructure\Messaging\EventPublisherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SelectPaymentMethodHandler
{
    public function __construct(
        private PaymentMethodRepositoryInterface $methodRepository,
        private PaymentSessionRepositoryInterface $sessionRepository,
        private EventPublisherInterface $eventPublisher
    ) {
    }

    public function __invoke(SelectPaymentMethodCommand $command): void
    {
        $method = $this->methodRepository->getById($command->methodId);
        if (!$method) {
            throw new \InvalidArgumentException("Payment method {$command->methodId} not found");
        }

        $session = $this->sessionRepository->findBySessionId($command->sessionId)
            ?? new PaymentSession($command->sessionId, $command->userId);

        $session->selectMethod($method);
        $this->sessionRepository->save($session);

        // Publish event
        $this->eventPublisher->publish('payment-events', [
            'event_type' => 'PaymentMethodSelected',
            'session_id' => $command->sessionId,
            'user_id' => $command->userId,
            'method_id' => $command->methodId,
            'method_name' => $method->name,
            'fee' => $method->fee->amountInCents,
            'correlation_id' => $command->correlationId,
            'occurred_at' => date('c'),
        ]);
    }
}
