<?php

declare(strict_types=1);

namespace Nocart\Checkout\Application\MessageHandler;

use Nocart\Checkout\Domain\Aggregate\CheckoutOrder;
use Nocart\Checkout\Domain\Repository\CheckoutOrderRepositoryInterface;
use Nocart\SharedKernel\Infrastructure\Messenger\Message\ExternalEventMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handler dla eventów zewnętrznych z Kafka.
 * Buduje lokalną projekcję CheckoutOrder na podstawie eventów z innych mikroserwisów.
 */
#[AsMessageHandler]
final readonly class ExternalEventHandler
{
    public function __construct(
        private CheckoutOrderRepositoryInterface $repository,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function __invoke(ExternalEventMessage $message): void
    {
        $eventName = $message->eventName;
        $sessionId = $this->extractSessionId($message);

        error_log("CHECKOUT DEBUG: Processing event={$eventName}, sessionId={$sessionId}, aggregateId={$message->aggregateId}");
        error_log("CHECKOUT DEBUG: Payload=" . json_encode($message->payload));

        $this->logger?->info('Processing external event', [
            'event_name' => $eventName,
            'extracted_session_id' => $sessionId,
            'aggregate_id' => $message->aggregateId,
            'payload_keys' => array_keys($message->payload),
            'payload' => $message->payload,
        ]);

        if ($sessionId === null) {
            error_log("CHECKOUT DEBUG: Could not extract session_id!");
            $this->logger?->warning('Could not extract session_id from event', [
                'event_name' => $eventName,
                'payload' => $message->payload,
            ]);
            return;
        }

        $order = $this->repository->findBySessionId($sessionId)
            ?? CheckoutOrder::create($sessionId);

        error_log("CHECKOUT DEBUG: Order found/created for sessionId={$sessionId}");

        $this->logger?->info('CheckoutOrder state', [
            'session_id' => $sessionId,
            'is_new' => $order->getSessionId() === $sessionId && empty($order->getTotals()['subtotal']['amount']),
        ]);

        // Idempotentność - sprawdź czy event był już przetworzony
        if ($message->eventId !== '' && $order->wasEventProcessed($message->eventId)) {
            $this->logger?->debug('Event already processed, skipping', [
                'event_id' => $message->eventId,
                'event_name' => $eventName,
            ]);
            return;
        }

        $this->applyEvent($order, $message);

        if ($message->eventId !== '') {
            $order->markEventProcessed($message->eventId);
        }

        error_log("CHECKOUT DEBUG: Saving order for sessionId={$sessionId}, totals=" . json_encode($order->getTotals()));
        $this->repository->save($order);
        error_log("CHECKOUT DEBUG: Order saved successfully");

        $this->logger?->info('External event processed', [
            'event_id' => $message->eventId,
            'event_name' => $eventName,
            'session_id' => $sessionId,
        ]);
    }

    private function extractSessionId(ExternalEventMessage $message): ?string
    {
        // Różne eventy mogą mieć session_id w różnych miejscach
        return $message->payload['session_id']
            ?? $message->payload['cart_id']
            ?? $message->aggregateId
            ?? null;
    }

    private function applyEvent(CheckoutOrder $order, ExternalEventMessage $message): void
    {
        $eventName = $message->eventName;
        $payload = $message->payload;

        error_log("CHECKOUT DEBUG: applyEvent called with eventName={$eventName}");

        match (true) {
            // Cart events (obsługuje oba formaty: CartItemAdded i cart.item_added)
            str_contains($eventName, 'ItemAdded') || str_starts_with($eventName, 'cart.item_added') => $order->applyCartItemAdded($payload),
            str_contains($eventName, 'ItemRemoved') || str_starts_with($eventName, 'cart.item_removed') => $order->applyCartItemRemoved($payload),
            str_contains($eventName, 'QuantityChanged') || str_starts_with($eventName, 'cart.item_quantity_changed') => $order->applyCartItemQuantityChanged($payload),
            str_contains($eventName, 'CartCleared') || str_starts_with($eventName, 'cart.cleared') => $order->applyCartCleared($payload),

            // Shipping events
            str_contains($eventName, 'MethodSelected') && str_contains($eventName, 'Shipping') || str_starts_with($eventName, 'shipping.method_selected') => $order->applyShippingMethodSelected($payload),
            str_contains($eventName, 'AddressProvided') || str_starts_with($eventName, 'shipping.address_provided') => $order->applyShippingAddressProvided($payload),
            str_contains($eventName, 'DeliveryDateSelected') || str_starts_with($eventName, 'shipping.delivery_date_selected') => $order->applyShippingDeliveryDateSelected($payload),

            // Promotion events
            str_contains($eventName, 'PromotionApplied') || str_starts_with($eventName, 'promotion.applied') => $order->applyPromotionApplied($payload),
            str_contains($eventName, 'PromotionRemoved') || str_starts_with($eventName, 'promotion.removed') => $order->applyPromotionRemoved($payload),
            str_contains($eventName, 'PromoCodeApplied') || str_starts_with($eventName, 'promotion.code_applied') => $order->applyPromoCodeApplied($payload),

            // Services events
            str_contains($eventName, 'ServiceSelected') || str_starts_with($eventName, 'services.selected') => $order->applyServiceSelected($payload),
            str_contains($eventName, 'ServiceRemoved') || str_starts_with($eventName, 'services.removed') => $order->applyServiceRemoved($payload),

            // Payment events
            str_contains($eventName, 'PaymentMethodSelected') || str_starts_with($eventName, 'payment.method_selected') => $order->applyPaymentMethodSelected($payload),
            str_contains($eventName, 'PaymentInitialized') || str_starts_with($eventName, 'payment.initialized') => $order->applyPaymentInitialized($payload),
            str_contains($eventName, 'PaymentSucceeded') || str_starts_with($eventName, 'payment.succeeded') => $order->applyPaymentSucceeded($payload),
            str_contains($eventName, 'PaymentFailed') || str_starts_with($eventName, 'payment.failed') => $order->applyPaymentFailed($payload),

            default => error_log("CHECKOUT DEBUG: Unknown event type: {$eventName}"),
        };
    }
}
