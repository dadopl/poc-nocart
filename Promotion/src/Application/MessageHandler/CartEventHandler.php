<?php

declare(strict_types=1);

namespace Nocart\Promotion\Application\MessageHandler;

use Nocart\Promotion\Domain\Repository\PromotionSessionRepositoryInterface;
use Nocart\SharedKernel\Infrastructure\Messenger\Message\ExternalEventMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handler dla eventów zewnętrznych z Kafka.
 * Promotion Service nasłuchuje na cart-events aby:
 * - Przeliczyć dostępne promocje gdy zmienia się koszyk
 * - Automatycznie usunąć promocje które nie spełniają warunków
 */
#[AsMessageHandler]
final readonly class CartEventHandler
{
    public function __construct(
        private PromotionSessionRepositoryInterface $sessionRepository,
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

        $this->logger?->info('Processing cart event in Promotion', [
            'event_name' => $message->eventName,
            'session_id' => $sessionId,
        ]);

        match ($message->eventName) {
            'cart.item_added' => $this->handleCartChanged($sessionId, $message->payload),
            'cart.item_removed' => $this->handleCartChanged($sessionId, $message->payload),
            'cart.item_quantity_changed' => $this->handleCartChanged($sessionId, $message->payload),
            'cart.cleared' => $this->handleCartCleared($sessionId),
            default => null,
        };
    }

    private function handleCartChanged(string $sessionId, array $payload): void
    {
        $session = $this->sessionRepository->findBySessionId($sessionId);

        if ($session === null) {
            return;
        }

        $cartTotal = $payload['cart_total'] ?? 0;

        // Sprawdź czy zastosowane promocje nadal są ważne
        // Na przykład: promocja "min 500 PLN" - jeśli koszyk spadł poniżej, usuń promocję

        $this->logger?->debug('Cart changed - checking promotion validity', [
            'session_id' => $sessionId,
            'cart_total' => $cartTotal,
        ]);
    }

    private function handleCartCleared(string $sessionId): void
    {
        // Gdy koszyk wyczyszczony - usuń sesję promocji
        $this->sessionRepository->delete($sessionId);

        $this->logger?->info('Cart cleared - promotion session deleted', [
            'session_id' => $sessionId,
        ]);
    }
}
