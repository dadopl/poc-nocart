<?php

declare(strict_types=1);

namespace Nocart\Cart\Domain\Repository;

use Nocart\Cart\Domain\Aggregate\Cart;
use Nocart\SharedKernel\Domain\ValueObject\UserId;

interface CartRepositoryInterface
{
    public function findByUserId(UserId $userId): ?Cart;

    public function save(Cart $cart): void;

    public function delete(UserId $userId): void;
}

