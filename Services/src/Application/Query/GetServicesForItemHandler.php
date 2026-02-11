<?php

declare(strict_types=1);

namespace Nocart\Services\Application\Query;

use Nocart\Services\Domain\Repository\AdditionalServiceRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetServicesForItemHandler
{
    public function __construct(
        private AdditionalServiceRepositoryInterface $serviceRepository
    ) {
    }

    public function __invoke(GetServicesForItemQuery $query): array
    {
        $services = $this->serviceRepository->getForCategory($query->category);

        return array_map(
            fn($service) => $service->toArray(),
            $services
        );
    }
}
