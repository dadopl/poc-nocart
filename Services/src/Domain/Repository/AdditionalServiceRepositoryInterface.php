<?php

declare(strict_types=1);

namespace Nocart\Services\Domain\Repository;

use Nocart\Services\Domain\ValueObject\AdditionalService;

interface AdditionalServiceRepositoryInterface
{
    /** @return AdditionalService[] */
    public function getAll(): array;

    /** @return AdditionalService[] */
    public function getStandalone(): array;

    /** @return AdditionalService[] */
    public function getForCategory(string $category): array;

    public function getById(int $id): ?AdditionalService;
}
