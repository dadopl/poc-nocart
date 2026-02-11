<?php

declare(strict_types=1);

namespace Nocart\Services\Application\Query;

use Nocart\Services\Domain\Repository\AdditionalServiceRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetAvailableServicesHandler
{
    public function __construct(
        private AdditionalServiceRepositoryInterface $serviceRepository
    ) {
    }

    public function __invoke(GetAvailableServicesQuery $query): array
    {
        if ($query->standaloneOnly) {
            $services = $this->serviceRepository->getStandalone();
        } else {
            $services = $this->serviceRepository->getAll();
        }

        return array_map(
            fn($service) => $service->toArray(),
            $services
        );
    }
}
