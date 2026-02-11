<?php

declare(strict_types=1);

namespace Nocart\Payment\Application\Query;

use Nocart\Payment\Domain\Repository\PaymentMethodRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetAvailablePaymentMethodsHandler
{
    public function __construct(
        private PaymentMethodRepositoryInterface $methodRepository
    ) {
    }

    public function __invoke(GetAvailablePaymentMethodsQuery $query): array
    {
        $methods = $this->methodRepository->getAvailableMethods();

        return array_map(
            fn($method) => $method->toArray(),
            $methods
        );
    }
}
