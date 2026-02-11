<?php

declare(strict_types=1);

namespace Nocart\Cart\Domain\Exception;

use Nocart\SharedKernel\Domain\Exception\NotFoundException;
use Nocart\SharedKernel\Domain\ValueObject\UserId;

final class CartNotFoundException extends NotFoundException
{
    public static function forUser(UserId $userId): self
    {
        return new self('Cart', $userId->toString());
    }
}

