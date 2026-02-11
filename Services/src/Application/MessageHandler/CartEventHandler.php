<?php

declare(strict_types=1);

namespace Nocart\Services\Application\MessageHandler;

use Nocart\Services\Domain\Repository\ServicesSessionRepositoryInterface;
use Nocart\SharedKernel\Infrastructure\Messenger\Message\ExternalEventMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handler dla eventów zewnętrznych z Kafka.
 * Services Service nasłuchuje na cart-events aby:
 * - Przeliczyć dostępne usługi gdy zmienia się koszyk
 * - Automatycznie usunąć usługi powiązane z usuniętymi produktami
 */
#[AsMessageHandler]
final readonly class CartEventHandler
{
    public function __construct(
        private ServicesSessionRepositoryInterface $sessionRepository,
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

        $this->logger?->info('Processing cart event in Services', [
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
        // Nowy produkt - można obliczyć dostępne usługi dla niego
        $this->logger?->debug('Cart item added - calculating available services', [
            'session_id' => $sessionId,
            'offer_id' => $payload['offer_id'] ?? null,
        ]);
    }

    private function handleItemRemoved(string $sessionId, array $payload): void
    {
        // Produkt usunięty - usuń powiązane usługi
        $itemId = $payload['item_id'] ?? null;

        if ($itemId === null) {
            return;
        }

        $session = $this->sessionRepository->findBySessionId($sessionId);

        if ($session !== null) {
            // Usuń usługi powiązane z tym produktem
            $session->removeServicesForItem($itemId);
            $this->sessionRepository->save($session);
        }

        $this->logger?->debug('Cart item removed - removed associated services', [
            'session_id' => $sessionId,
            'item_id' => $itemId,
        ]);
    }

    private function handleQuantityChanged(string $sessionId, array $payload): void
    {
        $this->logger?->debug('Cart quantity changed', [
            'session_id' => $sessionId,
            'item_id' => $payload['item_id'] ?? null,
        ]);
    }

    private function handleCartCleared(string $sessionId): void
    {
        // Gdy koszyk wyczyszczony - usuń sesję usług
        $this->sessionRepository->delete($sessionId);

        $this->logger?->info('Cart cleared - services session deleted', [
            'session_id' => $sessionId,
        ]);
    }
}
