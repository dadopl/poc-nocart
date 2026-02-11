<?php

declare(strict_types=1);

namespace Nocart\Shipping\Application\Query;

use Nocart\Shipping\Domain\Aggregate\ShippingSession;
use Nocart\Shipping\Domain\Repository\ShippingSessionRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetShippingSessionHandler
{
    public function __construct(
        private ShippingSessionRepositoryInterface $sessionRepository
    ) {
    }

    public function __invoke(GetShippingSessionQuery $query): array
    {
        $session = $this->sessionRepository->findBySessionId($query->sessionId);

        if (!$session) {
            return [
                'selected_method' => null,
                'address' => null,
                'shipping_cost' => 0,
            ];
        }

        return $session->toArray();
    }
}
