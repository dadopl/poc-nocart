<?php

declare(strict_types=1);

namespace Nocart\Cart\Infrastructure\Catalog;

final class StaticProductCatalog
{
    /** @var array<int, array{name: string, price: float, weight: float, category: string}> */
    private const PRODUCTS = [
        123 => [
            'name' => 'Laptop Dell XPS 15',
            'price' => 5999.00,
            'weight' => 2.0,
            'category' => 'electronics',
        ],
        456 => [
            'name' => 'Gwarancja 36 miesięcy',
            'price' => 299.00,
            'weight' => 0.0,
            'category' => 'warranty',
        ],
        789 => [
            'name' => 'Torba na laptop',
            'price' => 149.00,
            'weight' => 0.5,
            'category' => 'accessory',
        ],
        999 => [
            'name' => 'Lodówka Samsung',
            'price' => 3999.00,
            'weight' => 80.0,
            'category' => 'agd',
        ],
    ];

    /** @var array<string, array{name: string, price: float}> */
    private const SERVICES = [
        'sms-notif' => [
            'name' => 'Powiadomienie SMS',
            'price' => 2.00,
        ],
        'carrying' => [
            'name' => 'Wniesienie i rozpakowanie',
            'price' => 99.00,
        ],
        'express' => [
            'name' => 'Dostawa express',
            'price' => 29.99,
        ],
    ];

    /** @return array{name: string, price: float, weight: float, category: string}|null */
    public function getProduct(int $offerId): ?array
    {
        return self::PRODUCTS[$offerId] ?? null;
    }

    /** @return array{name: string, price: float}|null */
    public function getService(string $serviceId): ?array
    {
        return self::SERVICES[$serviceId] ?? null;
    }

    public function getProductName(int $offerId): string
    {
        return self::PRODUCTS[$offerId]['name'] ?? 'Unknown Product';
    }

    public function getProductPrice(int $offerId): float
    {
        return self::PRODUCTS[$offerId]['price'] ?? 0.0;
    }

    public function getServiceName(string $serviceId): string
    {
        return self::SERVICES[$serviceId]['name'] ?? 'Unknown Service';
    }

    public function getServicePrice(string $serviceId): float
    {
        return self::SERVICES[$serviceId]['price'] ?? 0.0;
    }
}

