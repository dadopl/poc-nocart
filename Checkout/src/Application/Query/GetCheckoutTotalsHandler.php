<?php

declare(strict_types=1);

namespace Nocart\Checkout\Application\Query;

use Nocart\Checkout\Domain\Repository\CheckoutOrderRepositoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetCheckoutTotalsHandler
{
    public function __construct(
        private CheckoutOrderRepositoryInterface $checkoutOrderRepository,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function __invoke(GetCheckoutTotalsQuery $query): array
    {
        error_log("CHECKOUT DEBUG: GetCheckoutTotals called for session_id={$query->sessionId}");

        $this->logger?->info('GetCheckoutTotals called', [
            'session_id' => $query->sessionId,
        ]);

        $order = $this->checkoutOrderRepository->findBySessionId($query->sessionId);

        error_log("CHECKOUT DEBUG: Order found=" . ($order !== null ? 'YES' : 'NO'));
        if ($order !== null) {
            error_log("CHECKOUT DEBUG: Order totals=" . json_encode($order->getTotals()));
        }

        $this->logger?->info('CheckoutOrder lookup result', [
            'session_id' => $query->sessionId,
            'found' => $order !== null,
            'totals' => $order?->getTotals(),
        ]);

        if ($order === null) {
            return $this->emptyTotals();
        }

        return $order->getTotals();
    }

    /**
     * @return array{subtotal: array{amount: int, currency: string}, shipping_cost: array{amount: int, currency: string}, promotion_discount: array{amount: int, currency: string}, services_cost: array{amount: int, currency: string}, grand_total: array{amount: int, currency: string}}
     */
    private function emptyTotals(): array
    {
        return [
            'subtotal' => ['amount' => 0, 'currency' => 'PLN'],
            'shipping_cost' => ['amount' => 0, 'currency' => 'PLN'],
            'promotion_discount' => ['amount' => 0, 'currency' => 'PLN'],
            'services_cost' => ['amount' => 0, 'currency' => 'PLN'],
            'grand_total' => ['amount' => 0, 'currency' => 'PLN'],
        ];
    }
}
