<?php

declare(strict_types=1);

namespace Nocart\Services\Infrastructure\Persistence;

use Nocart\Services\Domain\Repository\AdditionalServiceRepositoryInterface;
use Nocart\Services\Domain\ValueObject\AdditionalService;
use Nocart\Services\Domain\ValueObject\Money;

final class InMemoryAdditionalServiceRepository implements AdditionalServiceRepositoryInterface
{
    /** @var AdditionalService[] */
    private array $services;

    public function __construct()
    {
        $this->services = [
            new AdditionalService(
                id: 1,
                name: 'Extended Warranty 36m',
                price: Money::fromFloat(99.00),
                category: 'warranty',
                applicableCategories: ['electronics', 'appliances']
            ),
            new AdditionalService(
                id: 2,
                name: 'Installation Service',
                price: Money::fromFloat(150.00),
                category: 'installation',
                applicableCategories: ['appliances', 'electronics']
            ),
            new AdditionalService(
                id: 3,
                name: 'SMS Notifications',
                price: Money::fromFloat(5.00),
                category: 'notification',
                applicableCategories: [] // All categories
            ),
            new AdditionalService(
                id: 4,
                name: 'Delivery to Room',
                price: Money::fromFloat(50.00),
                category: 'delivery_helper',
                applicableCategories: ['furniture', 'appliances']
            ),
            new AdditionalService(
                id: 5,
                name: 'Assembly Service',
                price: Money::fromFloat(200.00),
                category: 'assembly',
                applicableCategories: ['furniture']
            ),
        ];
    }

    public function getAll(): array
    {
        return $this->services;
    }

    public function getStandalone(): array
    {
        return array_values(array_filter(
            $this->services,
            fn($service) => empty($service->applicableCategories)
        ));
    }

    public function getForCategory(string $category): array
    {
        return array_filter(
            $this->services,
            fn($service) => $service->isApplicableToCategory($category)
        );
    }

    public function getById(int $id): ?AdditionalService
    {
        foreach ($this->services as $service) {
            if ($service->id === $id) {
                return $service;
            }
        }

        return null;
    }
}
