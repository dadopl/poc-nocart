<?php

declare(strict_types=1);

namespace Nocart\Shipping\Application\Query;

use Nocart\Shipping\Domain\Repository\ShippingMethodRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetAvailableShippingMethodsHandler
{
    public function __construct(
        private ShippingMethodRepositoryInterface $methodRepository
    ) {
    }

    public function __invoke(GetAvailableShippingMethodsQuery $query): array
    {
        $methods = $this->methodRepository->getAvailableMethods();

        return array_map(
            fn($method) => $method->toArray(),
            $methods
        );
    }
}
