<?php

declare(strict_types=1);

namespace Nocart\Promotion\Domain\Repository;

use Nocart\Promotion\Domain\Aggregate\Promotion;

interface PromotionRepositoryInterface
{
    /** @return Promotion[] */
    public function getAvailablePromotions(): array;

    public function findByCode(string $code): ?Promotion;

    public function findById(string $id): ?Promotion;
}
