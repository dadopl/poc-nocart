<?php

declare(strict_types=1);

namespace Nocart\Promotion\Application\Query;

use Nocart\Promotion\Domain\Repository\PromotionRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetAvailablePromotionsHandler
{
    public function __construct(
        private PromotionRepositoryInterface $promotionRepository
    ) {
    }

    public function __invoke(GetAvailablePromotionsQuery $query): array
    {
        $promotions = $this->promotionRepository->getAvailablePromotions();

        return array_map(
            fn($promotion) => [
                'promotion' => $promotion->toArray(),
                'applicable' => true,
            ],
            $promotions
        );
    }
}
