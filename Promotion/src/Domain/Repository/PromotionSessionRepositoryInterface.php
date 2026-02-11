<?php

declare(strict_types=1);

namespace Nocart\Promotion\Domain\Repository;

use Nocart\Promotion\Domain\Aggregate\PromotionSession;

interface PromotionSessionRepositoryInterface
{
    public function findBySessionId(string $sessionId): ?PromotionSession;

    public function save(PromotionSession $session): void;
}
