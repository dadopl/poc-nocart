<?php

declare(strict_types=1);

namespace Nocart\Services\Domain\Repository;

use Nocart\Services\Domain\Aggregate\ServicesSession;

interface ServicesSessionRepositoryInterface
{
    public function findBySessionId(string $sessionId): ?ServicesSession;

    public function save(ServicesSession $session): void;
}
