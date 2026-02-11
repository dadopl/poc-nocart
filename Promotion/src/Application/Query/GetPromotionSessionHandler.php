<?php

declare(strict_types=1);

namespace Nocart\Promotion\Application\Query;

use Nocart\Promotion\Domain\Repository\PromotionSessionRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetPromotionSessionHandler
{
    public function __construct(
        private PromotionSessionRepositoryInterface $sessionRepository
    ) {
    }

    public function __invoke(GetPromotionSessionQuery $query): array
    {
        $session = $this->sessionRepository->findBySessionId($query->sessionId);

        if (!$session) {
            return [
                'applied_promotions' => [],
                'total_discount' => 0,
            ];
        }

        return $session->toArray();
    }
}
