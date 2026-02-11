<?php

declare(strict_types=1);

namespace Nocart\Shipping\Application\MessageHandler;

use Nocart\SharedKernel\Infrastructure\Messenger\Message\ExternalEventMessage;
use Nocart\Shipping\Domain\Repository\ShippingSessionRepositoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handler dla eventów zewnętrznych z Kafka.
 * Shipping Service nasłuchuje na cart-events aby:
 * - Przeliczyć koszty dostawy gdy zmienia się koszyk
 * - Zweryfikować dostępność metod dostawy dla nowych produktów
 */
#[AsMessageHandler]
final readonly class CartEventHandler
{
    public function __construct(
        private ShippingSessionRepositoryInterface $sessionRepository,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function __invoke(ExternalEventMessage $message): void
    {
        // Tylko obsługuj eventy z koszyka
        if (!str_starts_with($message->eventName, 'cart.')) {
            return;
        }

        $sessionId = $message->payload['session_id'] ?? $message->aggregateId;

        if ($sessionId === null || $sessionId === '') {
            $this->logger?->warning('Missing session_id in cart event', [
                'event_name' => $message->eventName,
            ]);
            return;
        }

        $this->logger?->info('Processing cart event in Shipping', [
            'event_name' => $message->eventName,
            'session_id' => $sessionId,
        ]);

        match ($message->eventName) {
            'cart.item_added' => $this->handleItemAdded($sessionId, $message->payload),
            'cart.item_removed' => $this->handleItemRemoved($sessionId, $message->payload),
            'cart.item_quantity_changed' => $this->handleQuantityChanged($sessionId, $message->payload),
            'cart.cleared' => $this->handleCartCleared($sessionId),
            default => null,
        };
    }

    private function handleItemAdded(string $sessionId, array $payload): void
    {
        // Można przeliczyć koszty dostawy na podstawie nowej wagi/rozmiaru
        // Na razie logujemy tylko
        $this->logger?->debug('Cart item added - may need to recalculate shipping', [
            'session_id' => $sessionId,
            'offer_id' => $payload['offer_id'] ?? null,
        ]);
    }

    private function handleItemRemoved(string $sessionId, array $payload): void
    {
        $this->logger?->debug('Cart item removed - may need to recalculate shipping', [
            'session_id' => $sessionId,
            'item_id' => $payload['item_id'] ?? null,
        ]);
    }

    private function handleQuantityChanged(string $sessionId, array $payload): void
    {
        $this->logger?->debug('Cart quantity changed - may need to recalculate shipping', [
            'session_id' => $sessionId,
            'item_id' => $payload['item_id'] ?? null,
            'new_quantity' => $payload['new_quantity'] ?? null,
        ]);
    }

    private function handleCartCleared(string $sessionId): void
    {
        // Gdy koszyk wyczyszczony - usuń sesję shipping
        $this->sessionRepository->delete($sessionId);

        $this->logger?->info('Cart cleared - shipping session deleted', [
            'session_id' => $sessionId,
        ]);
    }
}
